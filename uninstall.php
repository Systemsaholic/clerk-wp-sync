<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$options = [
    'clerk_wp_sync_webhook_secret',
    'clerk_wp_sync_api_key',
    'clerk_wp_sync_default_role',
    'clerk_wp_sync_deletion_behavior',
    'clerk_wp_sync_reassign_user'
];

foreach ($options as $option) {
    delete_option($option);
}

$users = get_users([
    'meta_key' => 'clerk_id',
    'fields' => 'ID',
]);

foreach ($users as $user_id) {
    delete_user_meta($user_id, 'clerk_id');
}
?> 