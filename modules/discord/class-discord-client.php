<?php
/**
 * Location: modules/discord/class-discord-client.php
 * Dependencies: None
 * Variables/Classes: Discord_Client
 * Purpose: Handles Discord API interactions, bot management, and webhook processing
 */

namespace SEWN\WebSockets\Modules\Discord;

class Discord_Client {
    private $bot_token;
    private $guild_id;
    private $webhook_url;
    private $api_base = 'https://discord.com/api/v10';

    public function __construct(string $bot_token = '', string $guild_id = '', string $webhook_url = '') {
        $this->bot_token = $bot_token;
        $this->guild_id = $guild_id;
        $this->webhook_url = $webhook_url;
    }

    /**
     * Send message to Discord channel via webhook
     *
     * @param array $message Message data
     * @return array Response data
     */
    public function send_message(array $message): array {
        if (empty($this->webhook_url)) {
            return [
                'success' => false,
                'error' => 'Webhook URL not configured'
            ];
        }

        $response = wp_remote_post($this->webhook_url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode([
                'content' => $message['content'] ?? '',
                'username' => $message['username'] ?? 'WireBot',
                'avatar_url' => $message['avatar_url'] ?? ''
            ])
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }

        return [
            'success' => true,
            'response' => json_decode(wp_remote_retrieve_body($response), true)
        ];
    }

    /**
     * Get Discord guild information
     *
     * @return array Guild data
     */
    public function get_guild_info(): array {
        if (empty($this->bot_token) || empty($this->guild_id)) {
            return [
                'success' => false,
                'error' => 'Bot token or Guild ID not configured'
            ];
        }

        $response = wp_remote_get("{$this->api_base}/guilds/{$this->guild_id}", [
            'headers' => [
                'Authorization' => "Bot {$this->bot_token}",
                'Content-Type' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }

        return [
            'success' => true,
            'guild' => json_decode(wp_remote_retrieve_body($response), true)
        ];
    }

    /**
     * Verify bot token is valid
     *
     * @return bool True if valid
     */
    public function verify_bot_token(): bool {
        if (empty($this->bot_token)) {
            return false;
        }

        $response = wp_remote_get("{$this->api_base}/users/@me", [
            'headers' => [
                'Authorization' => "Bot {$this->bot_token}",
                'Content-Type' => 'application/json'
            ]
        ]);

        return !is_wp_error($response) && 
               wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Get bot connection status
     *
     * @return array Status information
     */
    public function get_status(): array {
        return [
            'bot_configured' => !empty($this->bot_token),
            'guild_configured' => !empty($this->guild_id),
            'webhook_configured' => !empty($this->webhook_url),
            'bot_valid' => $this->verify_bot_token()
        ];
    }
} 