# Clerk WP Sync

WordPress plugin to synchronize users between Clerk.com and WordPress. Designed to work seamlessly with Groundhogg CRM but can be used independently for basic Clerk user synchronization.

## Features

- Automatic user creation when users sign up through Clerk
- User profile updates sync from Clerk to WordPress
- Configurable user deletion behavior
- Customizable default user roles
- Secure webhook implementation using SVIX
- Integration with Groundhogg CRM (optional)
  - Automatically assigns Sales Rep role when Groundhogg is active
  - Syncs user data with Groundhogg contacts

## Requirements

- PHP 7.4 or higher
- WordPress 5.8 or higher
- Composer
- Groundhogg CRM (optional, for enhanced CRM features)

## Installation

### Option 1: Direct Download (Recommended for Production)
1. Download the latest release from the [releases page](https://github.com/systemsaholic/clerk-wp-sync/releases)
2. Upload and activate through WordPress admin
3. Configure plugin settings

### Option 2: Development Installation
1. Clone the repository
2. Run `composer install`
3. Activate the plugin

## Configuration

1. Go to Settings > Clerk WP Sync
2. Enter your Clerk Webhook Secret
3. Enter your Clerk API Key
4. Configure default user role:
   - With Groundhogg: Defaults to Sales Rep role
   - Without Groundhogg: Defaults to Subscriber role
5. Configure deletion behavior
6. Save settings

## Integration with Groundhogg

When Groundhogg is active, the plugin will:
- Set new users' role to "Sales Rep" by default
- Maintain consistency with Groundhogg's user role system
- Enable seamless integration between Clerk authentication and Groundhogg CRM

## Standalone Usage

Without Groundhogg, the plugin functions as a standard Clerk-WordPress synchronization tool:
- Creates WordPress users from Clerk signups
- Keeps user data in sync between platforms
- Manages user deletion/unlinking
- Configurable default roles and behaviors

## License

GPL v2 or later
