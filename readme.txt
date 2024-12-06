=== Clerk WP Sync ===
Contributors: alguertin
Tags: clerk, users, authentication, sync
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Synchronize users between Clerk.com and WordPress - Growth by automation

== Description ==

Clerk WP Sync provides seamless user synchronization between Clerk.com and WordPress. When users are created, updated, or deleted in Clerk, those changes are automatically reflected in WordPress.

Features:
* Automatic user creation when new users sign up through Clerk
* User profile updates sync from Clerk to WordPress
* Configurable user deletion behavior
* Customizable default user roles
* Secure webhook implementation using SVIX

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/clerk-wp-sync`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the plugin settings under Settings > Clerk WP Sync
4. Add your Clerk webhook secret and API key
5. Configure the webhook URL in your Clerk dashboard

== Frequently Asked Questions ==

= What happens when a user is deleted in Clerk? =
You can configure this behavior in the plugin settings:
* Delete the WordPress user (with content reassignment options)
* Unlink the user from Clerk while keeping the WordPress account

= Is this plugin secure? =
Yes, we use SVIX for secure webhook handling and verify all incoming requests using cryptographic signatures.

== Changelog ==

= 1.0.0 =
* Initial release

== Support ==

For support, please visit [Systemsaholic](https://systemsaholic.com). 