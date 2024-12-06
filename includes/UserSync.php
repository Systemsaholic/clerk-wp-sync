<?php
/**
 * User Sync Class
 *
 * @package ClerkWPSync
 * @author Al Guertin
 * @copyright 2024 Systemsaholic
 */

namespace ClerkWPSync;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class UserSync
 */
class UserSync {

    /**
     * Handle user created event from Clerk
     *
     * @param array $data User data from Clerk
     * @return array|WP_Error
     */
    public function handle_user_created( $data ) {
        // Extract user data
        $email = $data['email_addresses'][0]['email_address'] ?? '';
        $first_name = $data['first_name'] ?? '';
        $last_name = $data['last_name'] ?? '';
        $clerk_id = $data['id'] ?? '';

        if ( ! $email || ! $clerk_id ) {
            return new WP_Error(
                'invalid_data',
                __( 'Missing required user data', 'clerk-wp-sync' ),
                array( 'status' => 400 )
            );
        }

        // Check if user already exists
        if ( email_exists( $email ) ) {
            return new WP_Error(
                'user_exists',
                __( 'User already exists', 'clerk-wp-sync' ),
                array( 'status' => 400 )
            );
        }

        // Create user
        $user_data = array(
            'user_login' => $email,
            'user_email' => $email,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'user_pass'  => wp_generate_password(),
            'role'       => apply_filters( 'clerk_wp_sync_default_role', 'subscriber' )
        );

        $user_id = wp_insert_user( $user_data );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        // Store Clerk ID in user meta
        update_user_meta( $user_id, 'clerk_id', $clerk_id );

        /**
         * Fires after a user is created via Clerk
         *
         * @param int $user_id The user ID
         * @param array $data The raw data from Clerk
         */
        do_action( 'clerk_wp_sync_user_created', $user_id, $data );

        return array(
            'message' => __( 'User created successfully', 'clerk-wp-sync' ),
            'user_id' => $user_id
        );
    }

    /**
     * Handle user updated event from Clerk
     *
     * @param array $data User data from Clerk
     * @return array|WP_Error
     */
    public function handle_user_updated( $data ) {
        $clerk_id = $data['id'] ?? '';
        $email = $data['email_addresses'][0]['email_address'] ?? '';
        $first_name = $data['first_name'] ?? '';
        $last_name = $data['last_name'] ?? '';

        if ( ! $clerk_id ) {
            return new WP_Error(
                'invalid_data',
                __( 'Missing Clerk ID', 'clerk-wp-sync' ),
                array( 'status' => 400 )
            );
        }

        // Find user by Clerk ID
        $users = get_users( array(
            'meta_key'   => 'clerk_id',
            'meta_value' => $clerk_id,
            'number'     => 1
        ) );

        if ( empty( $users ) ) {
            return new WP_Error(
                'user_not_found',
                __( 'User not found', 'clerk-wp-sync' ),
                array( 'status' => 404 )
            );
        }

        $user = $users[0];
        $user_id = $user->ID;

        // Prepare user data
        $user_data = array(
            'ID'         => $user_id,
            'first_name' => $first_name,
            'last_name'  => $last_name,
        );

        // Update email only if it has changed
        if ( $email && $email !== $user->user_email ) {
            // Check if new email exists for another user
            if ( email_exists( $email ) && email_exists( $email ) !== $user_id ) {
                return new WP_Error(
                    'email_exists',
                    __( 'Email already exists for another user', 'clerk-wp-sync' ),
                    array( 'status' => 400 )
                );
            }
            $user_data['user_email'] = $email;
            $user_data['user_login'] = $email;
        }

        // Update user
        $result = wp_update_user( $user_data );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        /**
         * Fires after a user is updated via Clerk
         *
         * @param int $user_id The user ID
         * @param array $data The raw data from Clerk
         */
        do_action( 'clerk_wp_sync_user_updated', $user_id, $data );

        return array(
            'message' => __( 'User updated successfully', 'clerk-wp-sync' ),
            'user_id' => $user_id
        );
    }

    /**
     * Handle user deleted event from Clerk
     *
     * @param array $data User data from Clerk
     * @return array|WP_Error
     */
    public function handle_user_deleted( $data ) {
        $clerk_id = $data['id'] ?? '';

        if ( ! $clerk_id ) {
            return new WP_Error(
                'invalid_data',
                __( 'Missing Clerk ID', 'clerk-wp-sync' ),
                array( 'status' => 400 )
            );
        }

        // Find user by Clerk ID
        $users = get_users( array(
            'meta_key'   => 'clerk_id',
            'meta_value' => $clerk_id,
            'number'     => 1
        ) );

        if ( empty( $users ) ) {
            return new WP_Error(
                'user_not_found',
                __( 'User not found', 'clerk-wp-sync' ),
                array( 'status' => 404 )
            );
        }

        $user_id = $users[0]->ID;

        // Get deletion behavior from settings
        $deletion_behavior = get_option( 'clerk_wp_sync_deletion_behavior', 'delete' );

        if ( $deletion_behavior === 'delete' ) {
            // Delete user and reassign posts
            $reassign_id = get_option( 'clerk_wp_sync_reassign_user', null );
            require_once( ABSPATH . 'wp-admin/includes/user.php' );
            $result = wp_delete_user( $user_id, $reassign_id );

            if ( ! $result ) {
                return new WP_Error(
                    'deletion_failed',
                    __( 'Failed to delete user', 'clerk-wp-sync' ),
                    array( 'status' => 500 )
                );
            }
        } else {
            // Just remove the Clerk ID to unlink the user
            delete_user_meta( $user_id, 'clerk_id' );
        }

        /**
         * Fires after a user is deleted via Clerk
         *
         * @param int $user_id The user ID
         * @param array $data The raw data from Clerk
         * @param string $deletion_behavior The deletion behavior used
         */
        do_action( 'clerk_wp_sync_user_deleted', $user_id, $data, $deletion_behavior );

        return array(
            'message' => __( 'User processed successfully', 'clerk-wp-sync' ),
            'user_id' => $user_id,
            'action'  => $deletion_behavior
        );
    }
} 