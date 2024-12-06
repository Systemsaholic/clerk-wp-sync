<?php
/**
 * Plugin Name: Clerk WP Sync
 * Plugin URI: https://github.com/systemsaholic/clerk-wp-sync
 * Description: Synchronizes users between Clerk.com and WordPress
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Al Guertin
 * Author URI: https://systemsaholic.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clerk-wp-sync
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CLERK_WP_SYNC_VERSION', '1.0.0');
define('CLERK_WP_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLERK_WP_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CLERK_WP_SYNC_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once CLERK_WP_SYNC_PLUGIN_DIR . 'vendor/autoload.php';

final class Clerk_WP_Sync_Plugin {
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    private function init() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init_components'));
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'clerk-wp-sync',
            false,
            dirname(CLERK_WP_SYNC_PLUGIN_BASENAME) . '/languages'
        );
    }

    public function init_components() {
        new \ClerkWPSync\Webhook\Handler();
        if (is_admin()) {
            new \ClerkWPSync\Admin\Admin();
        }
    }

    public static function activate() {
        if (!get_role('sales_rep')) {
            add_role('sales_rep', __('Sales Representative', 'clerk-wp-sync'), [
                'read' => true,
                'edit_posts' => true,
                'delete_posts' => true,
            ]);
        }

        if (!get_option('clerk_wp_sync_webhook_secret')) {
            update_option('clerk_wp_sync_webhook_secret', wp_generate_password(32, true, true));
        }

        update_option('clerk_wp_sync_default_role', 'sales_rep');
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}

register_activation_hook(__FILE__, array('Clerk_WP_Sync_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('Clerk_WP_Sync_Plugin', 'deactivate'));

function clerk_wp_sync() {
    return Clerk_WP_Sync_Plugin::instance();
}

clerk_wp_sync(); 