<?php
/**
 * Discord Client - Reference Implementation
 *
 * This class serves as the reference implementation for external service clients.
 * It demonstrates proper configuration management, error handling, and API integration.
 *
 * Client Lifecycle:
 * 1. Construction - Configuration loading
 * 2. Validation - Config verification
 * 3. Connection - API connection setup
 * 4. Operation - Message handling
 * 5. Shutdown - Connection cleanup
 *
 * Integration Points:
 * - Discord REST API
 * - Discord Gateway API
 * - Discord Webhooks
 * - WordPress settings API
 *
 * @package SEWN\WebSockets\Modules\Discord
 * @since 1.0.0
 */

namespace SEWN\WebSockets\Modules\Discord;

use SEWN\WebSockets\Config;

/**
 * Discord Client Class
 *
 * Handles Discord API interactions including:
 * - Bot management
 * - Message delivery
 * - Webhook processing
 * - Status monitoring
 *
 * Required Configuration:
 * - Bot token (from Discord Developer Portal)
 * - Guild ID (Discord server ID)
 * - Webhook URL (for message delivery)
 *
 * Example Usage:
 * ```php
 * $client = new Discord_Client();
 * $response = $client->send_message([
 *     'content' => 'Hello Discord!',
 *     'username' => 'MyBot'
 * ]);
 * ```
 *
 * @since 1.0.0
 */
class Discord_Client {
    /**
     * Discord bot token
     *
     * @since 1.0.0
     * @var string
     */
    private $bot_token;

    /**
     * Discord guild ID
     *
     * @since 1.0.0
     * @var string
     */
    private $guild_id;

    /**
     * Discord webhook URL
     *
     * @since 1.0.0
     * @var string
     */
    private $webhook_url;

    /**
     * Discord API base URL
     *
     * @since 1.0.0
     * @var string
     */
    private $api_base = 'https://discord.com/api/v10';

    /**
     * Constructor
     *
     * Initialize client with configuration from Config class.
     * Logs warnings for missing configuration but allows initialization.
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Log initialization
        error_log('[SEWN] Discord Client: Starting initialization');

        try {
            // Get configuration using Config class
            error_log('[SEWN] Discord Client: Loading configuration');
            $this->bot_token = Config::get_module_setting('discord', 'bot_token', '');
            $this->guild_id = Config::get_module_setting('discord', 'guild_id', '');
            $this->webhook_url = Config::get_module_setting('discord', 'webhook_url', '');

            // Check configuration but don't block initialization
            error_log('[SEWN] Discord Client: Checking configuration');
            $this->validate_config();

            error_log('[SEWN] Discord Client: Initialized successfully');
        } catch (\Exception $e) {
            error_log('[SEWN] Discord Client: Initialization warning: ' . $e->getMessage());
            // Don't throw the exception, just log it
        }
    }

    /**
     * Validate client configuration
     *
     * Checks for required settings and logs warnings if missing:
     * - Bot token
     * - Guild ID
     * - Webhook URL
     *
     * @since 1.0.0
     * @return array Array of missing configurations
     */
    private function validate_config() {
        error_log('[SEWN] Discord Client: Starting configuration validation');
        
        $missing = [];
        
        if (empty($this->bot_token)) {
            $missing[] = 'bot_token';
        }

        if (empty($this->guild_id)) {
            $missing[] = 'guild_id';
        }

        if (empty($this->webhook_url)) {
            $missing[] = 'webhook_url';
        }
        
        if (!empty($missing)) {
            $warning = 'Missing configuration: ' . implode(', ', $missing);
            error_log('[SEWN] Discord Client: Configuration warning - ' . $warning);
        } else {
            error_log('[SEWN] Discord Client: Configuration validated successfully');
        }
        
        return $missing;
    }

    /**
     * Send message to Discord channel via webhook
     *
     * Delivers a message to Discord using configured webhook.
     * Supports customizing username and avatar.
     *
     * Required message format:
     * ```php
     * [
     *     'content' => string,    // Message content
     *     'username' => string,   // Optional bot username
     *     'avatar_url' => string  // Optional avatar URL
     * ]
     * ```
     *
     * @since 1.0.0
     * @param array $message Message data
     * @return array Response data
     */
    public function send_message(array $message): array {
        error_log('[SEWN] Discord Client: Sending message: ' . wp_json_encode($message));

        try {
            // Validate required configuration for this operation
            if (empty($this->webhook_url)) {
                throw new \Exception('Cannot send message: Webhook URL not configured');
            }

            // Validate message format
            if (empty($message['content'])) {
                throw new \Exception('Message content is required');
            }

            // Prepare request body
            $body = [
                'content' => $message['content'],
                'username' => $message['username'] ?? 'Wirebot',
                'avatar_url' => $message['avatar_url'] ?? ''
            ];

            error_log('[SEWN] Discord Client: Sending webhook request');
            
            // Send webhook request
            $response = wp_remote_post($this->webhook_url, [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => wp_json_encode($body)
            ]);

            // Check for request errors
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            // Get response code
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                throw new \Exception('Discord API error: ' . $response_code);
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            error_log('[SEWN] Discord Client: Message sent successfully');

            return [
                'success' => true,
                'response' => $data
            ];
        } catch (\Exception $e) {
            error_log('[SEWN] Discord Client: Message send failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get Discord guild information
     *
     * Retrieves information about the configured Discord guild/server.
     * Requires valid bot token and guild ID.
     *
     * Response format:
     * ```php
     * [
     *     'success' => bool,
     *     'guild' => array|null,
     *     'error' => string|null
     * ]
     * ```
     *
     * @since 1.0.0
     * @return array Guild data
     */
    public function get_guild_info(): array {
        error_log('[SEWN] Discord Client: Fetching guild info');

        try {
            if (empty($this->bot_token) || empty($this->guild_id)) {
                throw new \Exception('Bot token or Guild ID not configured');
            }

            error_log('[SEWN] Discord Client: Sending guild info request');
            
            $response = wp_remote_get("{$this->api_base}/guilds/{$this->guild_id}", [
                'headers' => [
                    'Authorization' => "Bot {$this->bot_token}",
                    'Content-Type' => 'application/json'
                ]
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                throw new \Exception('Discord API error: ' . $response_code);
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            error_log('[SEWN] Discord Client: Guild info fetched successfully');

            return [
                'success' => true,
                'guild' => $data
            ];
        } catch (\Exception $e) {
            error_log('[SEWN] Discord Client: Failed to fetch guild info: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify bot token is valid
     *
     * Tests the configured bot token by making an API request.
     * Uses the /users/@me endpoint which requires authentication.
     *
     * @since 1.0.0
     * @return bool True if valid
     */
    public function verify_bot_token(): bool {
        error_log('[SEWN] Discord Client: Verifying bot token');

        try {
            if (empty($this->bot_token)) {
                throw new \Exception('Bot token not configured');
            }

            error_log('[SEWN] Discord Client: Sending token verification request');
            
            $response = wp_remote_get("{$this->api_base}/users/@me", [
                'headers' => [
                    'Authorization' => "Bot {$this->bot_token}",
                    'Content-Type' => 'application/json'
                ]
            ]);

            $is_valid = !is_wp_error($response) && 
                       wp_remote_retrieve_response_code($response) === 200;

            error_log('[SEWN] Discord Client: Bot token verification: ' . ($is_valid ? 'valid' : 'invalid'));

            return $is_valid;
        } catch (\Exception $e) {
            error_log('[SEWN] Discord Client: Bot token verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get bot connection status
     *
     * Checks the status of all Discord connections:
     * - Bot token configuration
     * - Guild configuration
     * - Webhook configuration
     * - Bot token validity
     *
     * @since 1.0.0
     * @return array Status information
     */
    public function get_status(): array {
        error_log('[SEWN] Discord Client: Getting connection status');

        $status = [
            'bot_configured' => !empty($this->bot_token),
            'guild_configured' => !empty($this->guild_id),
            'webhook_configured' => !empty($this->webhook_url),
            'bot_valid' => $this->verify_bot_token()
        ];

        error_log('[SEWN] Discord Client: Status check complete: ' . wp_json_encode($status));

        return $status;
    }

    /**
     * Save Discord configuration
     *
     * Updates Discord client configuration:
     * - Bot token
     * - Guild ID
     * - Webhook URL
     *
     * Validates and saves each provided setting.
     *
     * @since 1.0.0
     * @param array $config Configuration values
     * @return bool Whether save was successful
     */
    public function save_config(array $config): bool {
        error_log('[SEWN] Discord Client: Saving configuration');

        try {
            $success = true;
            
            // Track which settings are being updated
            $updated = [];
            
            if (isset($config['bot_token'])) {
                $success &= Config::set_module_setting('discord', 'bot_token', $config['bot_token']);
                $updated[] = 'bot_token';
            }
            
            if (isset($config['guild_id'])) {
                $success &= Config::set_module_setting('discord', 'guild_id', $config['guild_id']);
                $updated[] = 'guild_id';
            }
            
            if (isset($config['webhook_url'])) {
                $success &= Config::set_module_setting('discord', 'webhook_url', $config['webhook_url']);
                $updated[] = 'webhook_url';
            }

            if ($success) {
                error_log('[SEWN] Discord Client: Configuration saved successfully. Updated: ' . implode(', ', $updated));
            } else {
                error_log('[SEWN] Discord Client: Failed to save some configuration values');
            }
            
            return $success;
        } catch (\Exception $e) {
            error_log('[SEWN] Discord Client: Failed to save configuration: ' . $e->getMessage());
            return false;
        }
    }
} 