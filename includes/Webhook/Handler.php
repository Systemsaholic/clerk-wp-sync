<?php
namespace ClerkWPSync\Webhook;

use Svix\Webhook;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use ClerkWPSync\User\Sync as UserSync;

if (!defined('ABSPATH')) {
    exit;
}

class Handler {
    private $svix_webhook;

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_webhook_endpoint']);
        
        $webhook_secret = get_option('clerk_wp_sync_webhook_secret');
        if (!empty($webhook_secret)) {
            try {
                $this->svix_webhook = new Webhook($webhook_secret);
            } catch (\Exception $e) {
                add_action('admin_notices', function() use ($e) {
                    ?>
                    <div class="notice notice-error">
                        <p><?php echo esc_html(sprintf(
                            __('Clerk WP Sync: Error initializing webhook: %s', 'clerk-wp-sync'),
                            $e->getMessage()
                        )); ?></p>
                    </div>
                    <?php
                });
            }
        }
    }

    public function register_webhook_endpoint() {
        register_rest_route('clerk-wp-sync/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => [$this, 'verify_webhook'],
        ]);
    }

    public function verify_webhook(WP_REST_Request $request): bool|WP_Error {
        if (!$this->svix_webhook) {
            return new WP_Error(
                'missing_secret',
                __('Webhook secret not configured', 'clerk-wp-sync'),
                ['status' => 401]
            );
        }

        try {
            $payload = $request->get_body();
            $headers = [
                'svix-id' => $request->get_header('svix-id'),
                'svix-timestamp' => $request->get_header('svix-timestamp'),
                'svix-signature' => $request->get_header('svix-signature'),
            ];

            $this->svix_webhook->verify($payload, $headers);
            return true;
        } catch (\Exception $e) {
            return new WP_Error(
                'invalid_signature',
                $e->getMessage(),
                ['status' => 401]
            );
        }
    }

    public function handle_webhook(WP_REST_Request $request) {
        $body = $request->get_json_params();
        $event_type = $request->get_header('svix-event-type') ?? $body['type'] ?? null;

        if (!$event_type) {
            return new WP_Error(
                'invalid_request',
                __('Missing event type in header and payload', 'clerk-wp-sync'),
                ['status' => 400]
            );
        }

        if (empty($body)) {
            return new WP_Error(
                'invalid_request',
                __('Empty request body', 'clerk-wp-sync'),
                ['status' => 400]
            );
        }

        try {
            $user_sync = new UserSync();
            $result = match ($event_type) {
                'user.created' => $user_sync->handle_user_created($body),
                'user.updated' => $user_sync->handle_user_updated($body),
                'user.deleted' => $user_sync->handle_user_deleted($body),
                default => new WP_Error(
                    'invalid_event',
                    __('Invalid event type', 'clerk-wp-sync'),
                    ['status' => 400]
                ),
            };

            return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
        } catch (\Exception $e) {
            return new WP_Error('sync_error', $e->getMessage(), ['status' => 500]);
        }
    }
} 