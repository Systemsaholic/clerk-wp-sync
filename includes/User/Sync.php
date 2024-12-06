<?php
/**
 * User Sync Class
 *
 * @package ClerkWPSync
 * @author Al Guertin
 * @copyright 2024 Systemsaholic
 */

namespace ClerkWPSync\User;

use WP_Error;
use ClerkWPSync\Api\Client as ClerkApi;

class Sync {
    private $clerk_api;

    public function __construct() {
        $this->clerk_api = new ClerkApi();
    }

    public function handle_user_created($webhook_data): array|WP_Error {
        $data = $webhook_data['data'] ?? null;
        if (!$data) {
            return new WP_Error('invalid_data', __('Missing user data in webhook payload', 'clerk-wp-sync'));
        }

        $email = $data['email_addresses'][0]['email_address'] ?? '';
        $first_name = $data['first_name'] ?? '';
        $last_name = $data['last_name'] ?? '';
        $clerk_id = $data['id'] ?? '';
        $username = $data['username'] ?? $email;

        if (!$email || !$clerk_id) {
            return new WP_Error('invalid_data', __('Missing required user data', 'clerk-wp-sync'));
        }

        // Check if user exists
        $existing_user = get_user_by('email', $email);
        if ($existing_user) {
            $this->update_clerk_metadata($existing_user->ID, $data);
            $this->clerk_api->update_user_metadata($clerk_id, ['wp_id' => $existing_user->ID]);
            return [
                'message' => __('Existing user linked to Clerk', 'clerk-wp-sync'),
                'user_id' => $existing_user->ID
            ];
        }

        // Get and verify role
        $default_role = get_option('clerk_wp_sync_default_role', 'sales_rep');
        $role_object = get_role($default_role);
        if (!$role_object) {
            add_role('sales_rep', __('Sales Representative', 'clerk-wp-sync'), [
                'read' => true,
                'edit_posts' => true,
                'delete_posts' => true,
            ]);
            $default_role = 'sales_rep';
        }

        // Create user
        $user_data = [
            'user_login' => $username,
            'user_email' => $email,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'user_pass'  => wp_generate_password(),
            'role'       => $default_role
        ];

        $user_id = wp_insert_user($user_data);
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Ensure role is set
        $user = new \WP_User($user_id);
        if (!in_array($default_role, $user->roles)) {
            $user->add_role($default_role);
        }

        $this->update_clerk_metadata($user_id, $data);
        $this->clerk_api->update_user_metadata($clerk_id, ['wp_id' => $user_id]);

        do_action('clerk_wp_sync_user_created', $user_id, $data);

        return [
            'message' => __('User created successfully', 'clerk-wp-sync'),
            'user_id' => $user_id
        ];
    }

    public function handle_user_updated($webhook_data): array|WP_Error {
        $data = $webhook_data['data'] ?? null;
        if (!$data) {
            return new WP_Error('invalid_data', __('Missing user data in webhook payload', 'clerk-wp-sync'));
        }

        $clerk_id = $data['id'] ?? '';
        $matching_users = get_users([
            'meta_key' => 'clerk_id',
            'meta_value' => $clerk_id,
            'meta_compare' => '=',
            'number' => 1
        ]);

        if (empty($matching_users)) {
            return new WP_Error('not_found', __('User not found', 'clerk-wp-sync'));
        }

        $user = $matching_users[0];
        $user_id = $user->ID;

        // Update user data
        $user_data = [
            'ID' => $user_id,
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
        ];

        // Update email if changed
        $new_email = $data['email_addresses'][0]['email_address'] ?? '';
        if ($new_email && $new_email !== $user->user_email) {
            $user_data['user_email'] = $new_email;
        }

        $result = wp_update_user($user_data);
        if (is_wp_error($result)) {
            return $result;
        }

        $this->update_clerk_metadata($user_id, $data);

        return [
            'message' => __('User updated successfully', 'clerk-wp-sync'),
            'user_id' => $user_id
        ];
    }

    public function handle_user_deleted($data): array|WP_Error {
        $clerk_id = $data['data']['id'] ?? '';
        $matching_users = get_users([
            'meta_key' => 'clerk_id',
            'meta_value' => $clerk_id,
            'meta_compare' => '=',
            'number' => 1,
            'fields' => ['ID', 'user_email']
        ]);

        if (empty($matching_users)) {
            return new WP_Error('not_found', __('User not found', 'clerk-wp-sync'));
        }

        $user = $matching_users[0];
        $user_id = $user->ID;

        // Verify clerk_id matches
        $stored_clerk_id = get_user_meta($user_id, 'clerk_id', true);
        if ($stored_clerk_id !== $clerk_id) {
            return new WP_Error('clerk_id_mismatch', __('User Clerk ID mismatch', 'clerk-wp-sync'));
        }

        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $deletion_behavior = get_option('clerk_wp_sync_deletion_behavior', 'delete');

        if ($deletion_behavior === 'delete') {
            $reassign_id = get_option('clerk_wp_sync_reassign_user', null);
            $result = \wp_delete_user($user_id, $reassign_id);
            if (!$result) {
                return new WP_Error('deletion_failed', __('Failed to delete user', 'clerk-wp-sync'));
            }
        } else {
            delete_user_meta($user_id, 'clerk_id');
        }

        return [
            'message' => __('User processed successfully', 'clerk-wp-sync'),
            'user_id' => $user_id,
            'action' => $deletion_behavior
        ];
    }

    private function update_clerk_metadata($user_id, $data): void {
        // Basic metadata
        update_user_meta($user_id, 'clerk_id', $data['id'] ?? '');
        update_user_meta($user_id, 'clerk_created_at', $data['created_at'] ?? '');
        update_user_meta($user_id, 'clerk_updated_at', $data['updated_at'] ?? '');
        update_user_meta($user_id, 'clerk_image_url', $data['image_url'] ?? '');
        update_user_meta($user_id, 'clerk_profile_image_url', $data['profile_image_url'] ?? '');
        
        // Boolean flags
        update_user_meta($user_id, 'clerk_backup_code_enabled', $data['backup_code_enabled'] ?? false);
        update_user_meta($user_id, 'clerk_banned', $data['banned'] ?? false);
        update_user_meta($user_id, 'clerk_create_organization_enabled', $data['create_organization_enabled'] ?? false);
        update_user_meta($user_id, 'clerk_delete_self_enabled', $data['delete_self_enabled'] ?? false);
        update_user_meta($user_id, 'clerk_has_image', $data['has_image'] ?? false);
        update_user_meta($user_id, 'clerk_locked', $data['locked'] ?? false);
        update_user_meta($user_id, 'clerk_password_enabled', $data['password_enabled'] ?? false);
        update_user_meta($user_id, 'clerk_totp_enabled', $data['totp_enabled'] ?? false);
        update_user_meta($user_id, 'clerk_two_factor_enabled', $data['two_factor_enabled'] ?? false);

        // Timestamps
        update_user_meta($user_id, 'clerk_last_active_at', $data['last_active_at'] ?? '');
        update_user_meta($user_id, 'clerk_last_sign_in_at', $data['last_sign_in_at'] ?? '');
        update_user_meta($user_id, 'clerk_legal_accepted_at', $data['legal_accepted_at'] ?? '');
        update_user_meta($user_id, 'clerk_mfa_enabled_at', $data['mfa_enabled_at'] ?? '');
        update_user_meta($user_id, 'clerk_mfa_disabled_at', $data['mfa_disabled_at'] ?? '');

        // IDs
        update_user_meta($user_id, 'clerk_external_id', $data['external_id'] ?? '');
        update_user_meta($user_id, 'clerk_primary_email_address_id', $data['primary_email_address_id'] ?? '');
        update_user_meta($user_id, 'clerk_primary_phone_number_id', $data['primary_phone_number_id'] ?? '');
        update_user_meta($user_id, 'clerk_primary_web3_wallet_id', $data['primary_web3_wallet_id'] ?? '');

        // Arrays (stored as JSON)
        update_user_meta($user_id, 'clerk_email_addresses', json_encode($data['email_addresses'] ?? []));
        update_user_meta($user_id, 'clerk_phone_numbers', json_encode($data['phone_numbers'] ?? []));
        update_user_meta($user_id, 'clerk_web3_wallets', json_encode($data['web3_wallets'] ?? []));
        update_user_meta($user_id, 'clerk_external_accounts', json_encode($data['external_accounts'] ?? []));
        update_user_meta($user_id, 'clerk_passkeys', json_encode($data['passkeys'] ?? []));
        update_user_meta($user_id, 'clerk_saml_accounts', json_encode($data['saml_accounts'] ?? []));

        // Metadata objects
        update_user_meta($user_id, 'clerk_private_metadata', json_encode($data['private_metadata'] ?? []));
        update_user_meta($user_id, 'clerk_public_metadata', json_encode($data['public_metadata'] ?? []));
        update_user_meta($user_id, 'clerk_unsafe_metadata', json_encode($data['unsafe_metadata'] ?? []));
    }
} 