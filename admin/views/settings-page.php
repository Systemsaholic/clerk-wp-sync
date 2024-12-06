<?php
if (!defined('ABSPATH')) {
    exit;
}

$webhook_secret = get_option('clerk_wp_sync_webhook_secret');
$default_role = get_option('clerk_wp_sync_default_role', 'subscriber');
$deletion_behavior = get_option('clerk_wp_sync_deletion_behavior', 'delete');
$reassign_user = get_option('clerk_wp_sync_reassign_user');

$wp_roles = wp_roles();
$available_roles = $wp_roles->get_names();

$users = get_users([
    'fields' => ['ID', 'user_login', 'display_name'],
    'orderby' => 'display_name',
]);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <?php settings_errors('clerk_wp_sync_messages'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('clerk_wp_sync_settings'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="clerk_wp_sync_webhook_secret">
                        <?php esc_html_e('Webhook Secret', 'clerk-wp-sync'); ?>
                    </label>
                </th>
                <td>
                    <input type="password"
                           id="clerk_wp_sync_webhook_secret"
                           name="clerk_wp_sync_webhook_secret"
                           value="<?php echo esc_attr($webhook_secret); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php esc_html_e('Enter the webhook secret from your Clerk webhook settings.', 'clerk-wp-sync'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="clerk_wp_sync_api_key">
                        <?php esc_html_e('Secret Key', 'clerk-wp-sync'); ?>
                    </label>
                </th>
                <td>
                    <input type="password"
                           id="clerk_wp_sync_api_key"
                           name="clerk_wp_sync_api_key"
                           value="<?php echo esc_attr(get_option('clerk_wp_sync_api_key')); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php esc_html_e('Enter your Clerk Secret Key to enable syncing data back to Clerk.', 'clerk-wp-sync'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="clerk_wp_sync_default_role">
                        <?php esc_html_e('Default User Role', 'clerk-wp-sync'); ?>
                    </label>
                </th>
                <td>
                    <select id="clerk_wp_sync_default_role" name="clerk_wp_sync_default_role">
                        <?php foreach ($available_roles as $role => $name): ?>
                            <option value="<?php echo esc_attr($role); ?>" <?php selected($default_role, $role); ?>>
                                <?php echo esc_html(translate_user_role($name)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Select the default role for new users created via Clerk.', 'clerk-wp-sync'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="clerk_wp_sync_deletion_behavior">
                        <?php esc_html_e('User Deletion Behavior', 'clerk-wp-sync'); ?>
                    </label>
                </th>
                <td>
                    <select id="clerk_wp_sync_deletion_behavior" name="clerk_wp_sync_deletion_behavior">
                        <option value="delete" <?php selected($deletion_behavior, 'delete'); ?>>
                            <?php esc_html_e('Delete User', 'clerk-wp-sync'); ?>
                        </option>
                        <option value="unlink" <?php selected($deletion_behavior, 'unlink'); ?>>
                            <?php esc_html_e('Unlink from Clerk', 'clerk-wp-sync'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Choose how to handle users when they are deleted in Clerk.', 'clerk-wp-sync'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="clerk_wp_sync_reassign_user">
                        <?php esc_html_e('Reassign Content To', 'clerk-wp-sync'); ?>
                    </label>
                </th>
                <td>
                    <select id="clerk_wp_sync_reassign_user" name="clerk_wp_sync_reassign_user">
                        <option value=""><?php esc_html_e('— No user —', 'clerk-wp-sync'); ?></option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($reassign_user, $user->ID); ?>>
                                <?php echo esc_html(sprintf('%s (%s)', $user->display_name, $user->user_login)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Select a user to reassign content to when deleting users.', 'clerk-wp-sync'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Webhook Information', 'clerk-wp-sync'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Webhook URL', 'clerk-wp-sync'); ?></th>
                <td>
                    <code><?php echo esc_url(rest_url('clerk-wp-sync/v1/webhook')); ?></code>
                    <p class="description">
                        <?php esc_html_e('Use this URL in your Clerk dashboard to configure the webhook.', 'clerk-wp-sync'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>