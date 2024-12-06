<?php

namespace ClerkWPSync\Api;

class Client {
    private $api_key;
    private $base_url = 'https://api.clerk.com/v1';

    public function __construct() {
        $this->api_key = get_option('clerk_wp_sync_api_key');
    }

    public function update_user_metadata(string $clerk_id, array $metadata): bool {
        if (empty($this->api_key)) {
            return false;
        }

        $url = "{$this->base_url}/users/{$clerk_id}";
        
        $response = wp_remote_request($url, [
            'method' => 'PATCH',
            'headers' => [
                'Authorization' => "Bearer {$this->api_key}",
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'private_metadata' => $metadata
            ])
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        return wp_remote_retrieve_response_code($response) === 200;
    }
} 