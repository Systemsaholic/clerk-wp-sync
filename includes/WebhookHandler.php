<?php
/**
 * Webhook Handler Class
 *
 * @package ClerkWPSync
 * @author Al Guertin
 * @copyright 2024 Systemsaholic
 */

namespace ClerkWPSync;

use Svix\Webhook;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class WebhookHandler
 */
class WebhookHandler {

    /**
     * @var Webhook
     */
    private $svix_webhook;

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_webhook_endpoint' ) );
        
        $webhook_secret = get_option( 'clerk_wp_sync_webhook_secret' );
        try {
            $this->svix_webhook = new Webhook( $webhook_secret );
        } catch ( \Exception $e ) {
            add_action( 'admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html( sprintf( 
                        __( 'Clerk WP Sync: Error initializing SVIX webhook: %s', 'clerk-wp-sync' ),
                        $e->getMessage()
                    ) ); ?></p>
                </div>
                <?php
            });
        }
    }

    /**
     * Register webhook endpoint
     */
    public function register_webhook_endpoint() {
        register_rest_route( 'clerk-wp-sync/v1', '/webhook', array(
            'methods'             => 'POST',
            'callback'           => array( $this, 'handle_webhook' ),
            'permission_callback' => array( $this, 'verify_webhook' ),
        ) );
    }

    /**
     * Verify webhook request using SVIX
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function verify_webhook( $request ) {
        if ( ! $this->svix_webhook ) {
            return new WP_Error(
                'svix_not_initialized',
                __( 'SVIX webhook verifier not initialized', 'clerk-wp-sync' ),
                array( 'status' => 500 )
            );
        }

        try {
            $payload = $request->get_body();
            $headers = array(
                'svix-id'        => $request->get_header( 'svix-id' ),
                'svix-timestamp' => $request->get_header( 'svix-timestamp' ),
                'svix-signature' => $request->get_header( 'svix-signature' ),
            );

            $this->svix_webhook->verify( $payload, $headers );
            return true;
            
        } catch ( \Exception $e ) {
            return new WP_Error(
                'invalid_signature',
                $e->getMessage(),
                array( 'status' => 401 )
            );
        }
    }

    /**
     * Handle webhook request
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_webhook( $request ) {
        $body = $request->get_json_params();
        $event_type = $request->get_header( 'svix-event-type' );

        if ( ! $event_type || ! $body ) {
            return new WP_Error(
                'invalid_request',
                __( 'Invalid webhook request', 'clerk-wp-sync' ),
                array( 'status' => 400 )
            );
        }

        try {
            $user_sync = new UserSync();

            switch ( $event_type ) {
                case 'user.created':
                    $result = $user_sync->handle_user_created( $body );
                    break;
                case 'user.updated':
                    $result = $user_sync->handle_user_updated( $body );
                    break;
                case 'user.deleted':
                    $result = $user_sync->handle_user_deleted( $body );
                    break;
                default:
                    return new WP_Error(
                        'invalid_event',
                        __( 'Invalid event type', 'clerk-wp-sync' ),
                        array( 'status' => 400 )
                    );
            }

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return new WP_REST_Response( $result, 200 );

        } catch ( \Exception $e ) {
            return new WP_Error(
                'sync_error',
                $e->getMessage(),
                array( 'status' => 500 )
            );
        }
    }
} 