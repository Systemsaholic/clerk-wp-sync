<?php

namespace ClerkWPSync\Admin;

class Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('show_user_profile', [$this, 'add_clerk_user_fields']);
        add_action('edit_user_profile', [$this, 'add_clerk_user_fields']);

        if (!get_option('clerk_wp_sync_default_role')) {
            update_option('clerk_wp_sync_default_role', 'sales_rep');
        }
    }

    public function add_admin_menu() {
        add_options_page(
            __('Clerk WP Sync Settings', 'clerk-wp-sync'),
            __('Clerk WP Sync', 'clerk-wp-sync'),
            'manage_options',
            'clerk-wp-sync',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        $settings = [
            'clerk_wp_sync_webhook_secret',
            'clerk_wp_sync_api_key',
            'clerk_wp_sync_default_role',
            'clerk_wp_sync_deletion_behavior',
            'clerk_wp_sync_reassign_user'
        ];

        foreach ($settings as $setting) {
            register_setting('clerk_wp_sync', $setting);
        }
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['submit']) && check_admin_referer('clerk_wp_sync_settings')) {
            $this->save_settings();
        }

        include CLERK_WP_SYNC_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    private function save_settings() {
        $settings = [
            'clerk_wp_sync_webhook_secret' => 'sanitize_text_field',
            'clerk_wp_sync_api_key' => 'sanitize_text_field',
            'clerk_wp_sync_default_role' => 'sanitize_text_field',
            'clerk_wp_sync_deletion_behavior' => 'sanitize_text_field',
            'clerk_wp_sync_reassign_user' => 'absint'
        ];

        foreach ($settings as $key => $sanitize_callback) {
            if (isset($_POST[$key])) {
                update_option($key, $sanitize_callback($_POST[$key]));
            }
        }

        add_settings_error(
            'clerk_wp_sync_messages',
            'clerk_wp_sync_message',
            __('Settings Saved', 'clerk-wp-sync'),
            'updated'
        );
    }

    public function add_clerk_user_fields($user) {
        $meta_fields = [
            'clerk_id' => __('Clerk ID', 'clerk-wp-sync'),
            'clerk_created_at' => __('Clerk Created At', 'clerk-wp-sync'),
            'clerk_image_url' => __('Clerk Image URL', 'clerk-wp-sync')
        ];

        ?>
        <h2><?php _e('Clerk Information', 'clerk-wp-sync'); ?></h2>
        <table class="form-table">
            <?php foreach ($meta_fields as $key => $label): ?>
                <tr>
                    <th><label><?php echo esc_html($label); ?></label></th>
                    <td>
                        <?php
                        $value = get_user_meta($user->ID, $key, true);
                        if ($key === 'clerk_created_at' && $value) {
                            echo date('Y-m-d H:i:s', $value/1000);
                        } elseif ($key === 'clerk_image_url' && $value) {
                            echo '<img src="' . esc_url($value) . '" style="max-width:100px;"><br>';
                            echo esc_url($value);
                        } else {
                            echo esc_html($value);
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }
} 