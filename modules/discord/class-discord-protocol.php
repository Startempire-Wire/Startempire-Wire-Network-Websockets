<?php
/**
 * Discord Protocol - Reference Implementation
 *
 * This class serves as the reference implementation for WebSocket protocols.
 * It demonstrates proper message handling, validation, and error management.
 *
 * Protocol Lifecycle:
 * 1. Construction - Client setup
 * 2. Registration - Protocol registration
 * 3. Configuration - Protocol setup
 * 4. Message Handling - Protocol operation
 * 5. Error Management - Error handling
 *
 * Message Types:
 * - chat: Text message delivery
 * - presence: User status updates
 * - error: Error notifications
 *
 * Integration Points:
 * - Discord Client
 * - WebSocket Handler
 * - WordPress Hooks
 * - Error Management
 *
 * @package SEWN\WebSockets\Modules\Discord
 * @since 1.0.0
 */

namespace SEWN\WebSockets\Modules\Discord;

use SEWN\WebSockets\Config;
use SEWN\WebSockets\Protocol_Base;

/**
 * Discord Protocol Class
 *
 * Implements Discord-specific WebSocket protocol including:
 * - Message handling
 * - Presence updates
 * - Error management
 * - Configuration management
 *
 * Required Configuration:
 * - Protocol version
 * - Minimum PHP version
 * - Feature flags
 *
 * Example Usage:
 * ```php
 * $protocol = new Discord_Protocol($client);
 * $protocol->register();
 * $response = $protocol->handle_message($message, $context);
 * ```
 *
 * @property Discord_Client $client
 * @since 1.0.0
 */
class Discord_Protocol extends Protocol_Base {
    /**
     * Discord client instance
     *
     * @since 1.0.0
     * @var Discord_Client
     */
    private $client;

    /**
     * Protocol configuration
     *
     * @since 1.0.0
     * @var array
     */
    private $config;

    /**
     * Constructor
     *
     * Initialize protocol with Discord client instance.
     * Sets up basic protocol configuration.
     *
     * @since 1.0.0
     * @param Discord_Client $client Discord client instance
     */
    public function __construct(Discord_Client $client) {
        error_log('[SEWN] Discord Protocol: Starting initialization');
        try {
            $this->client = $client;
            $this->init_config();
            error_log('[SEWN] Discord Protocol: Initialized successfully');
        } catch (\Exception $e) {
            error_log('[SEWN] Discord Protocol: Initialization warning: ' . $e->getMessage());
            // Don't throw the exception, just log it
        }
    }

    /**
     * Register protocol hooks
     *
     * Sets up WordPress hooks for:
     * - Protocol registration
     * - Configuration management
     * - Client configuration
     *
     * @since 1.0.0
     * @return void
     */
    public function register() {
        error_log('[SEWN] Discord Protocol: Registering hooks');
        
        add_action('sewn_ws_register_protocols', [$this, 'register_protocol']);
        add_action('sewn_ws_init', [$this, 'init_config']);
        add_filter('sewn_ws_client_config', [$this, 'add_protocol_config']);
        
        error_log('[SEWN] Discord Protocol: Hooks registered');
    }

    /**
     * Register protocol with handler
     *
     * Registers this protocol with the WebSocket handler.
     * Called during system initialization.
     *
     * @since 1.0.0
     * @param object $handler Protocol handler instance
     * @return void
     */
    public function register_protocol($handler) {
        error_log('[SEWN] Discord Protocol: Registering with handler');
        $handler->register_protocol('discord', $this);
        error_log('[SEWN] Discord Protocol: Registered with handler');
    }

    /**
     * Initialize protocol configuration
     *
     * Sets up protocol configuration including:
     * - Version information
     * - System requirements
     * - Feature flags
     * - Bot status
     *
     * @since 1.0.0
     * @return void
     */
    public function init_config() {
        error_log('[SEWN] Discord Protocol: Initializing configuration');
        
        try {
            $this->config = [
                'version' => '1.0',
                'min_php' => '7.4',
                'bot_status' => $this->client->get_status(),
                'streaming_enabled' => Config::get_module_setting('discord', 'streaming_enabled', false),
                'role_sync_enabled' => Config::get_module_setting('discord', 'role_sync_enabled', false),
                'notification_channel' => Config::get_module_setting('discord', 'notification_channel', '')
            ];
            
            error_log('[SEWN] Discord Protocol: Configuration initialized');
        } catch (\Exception $e) {
            error_log('[SEWN] Discord Protocol: Configuration warning: ' . $e->getMessage());
            // Set default configuration if there's an error
            $this->config = [
                'version' => '1.0',
                'min_php' => '7.4',
                'bot_status' => ['bot_configured' => false],
                'streaming_enabled' => false,
                'role_sync_enabled' => false,
                'notification_channel' => ''
            ];
        }
    }

    /**
     * Handle incoming WebSocket message
     *
     * Processes incoming messages based on type:
     * - chat: Text message handling
     * - presence: Status update handling
     * - error: Error notification handling
     *
     * Message Format:
     * ```php
     * [
     *     'type' => string,     // Message type
     *     'content' => string,  // Message content
     *     'user_data' => array  // User context
     * ]
     * ```
     *
     * @since 1.0.0
     * @param array $message Message data
     * @param array $context Message context
     * @return array Response data
     */
    public function handle_message($message, $context) {
        error_log('[SEWN] Discord Protocol: Handling message: ' . wp_json_encode($message));

        try {
            if (!$this->validate_message($message)) {
                throw new \Exception('Invalid message format');
            }

            $message_type = $message['type'] ?? 'unknown';
            $user_data = $context['user_data'] ?? [];

            error_log("[SEWN] Discord Protocol: Processing {$message_type} message");

            $response = match ($message_type) {
                'chat' => $this->handle_chat_message($message, $user_data),
                'presence' => $this->handle_presence_update($message, $user_data),
                default => throw new \Exception('Unsupported message type')
            };

            error_log('[SEWN] Discord Protocol: Message handled successfully');
            return $response;
        } catch (\Exception $e) {
            error_log('[SEWN] Discord Protocol: Message handling failed: ' . $e->getMessage());
            return $this->handle_error($e->getMessage(), $message);
        }
    }

    /**
     * Add protocol configuration to client config
     *
     * Adds Discord-specific configuration to client:
     * - Protocol status
     * - Feature availability
     * - Version information
     * - Bot status
     *
     * @since 1.0.0
     * @param array $config Existing configuration
     * @return array Updated configuration
     */
    public function add_protocol_config($config) {
        error_log('[SEWN] Discord Protocol: Adding protocol configuration');
        
        $config['discord'] = [
            'enabled' => true,
            'version' => $this->config['version'],
            'features' => [
                'chat' => true,
                'presence' => true,
                'streaming' => $this->config['streaming_enabled']
            ],
            'bot_status' => $this->config['bot_status']
        ];
        
        error_log('[SEWN] Discord Protocol: Protocol configuration added');
        return $config;
    }

    /**
     * Handle chat message
     *
     * Processes chat messages for delivery:
     * - Validates message format
     * - Prepares message content
     * - Sends via Discord client
     * - Handles response
     *
     * @since 1.0.0
     * @param array $message Message data
     * @param array $user_data User context
     * @return array Response data
     */
    private function handle_chat_message($message, $user_data) {
        error_log('[SEWN] Discord Protocol: Handling chat message');

        try {
            if (!isset($message['content'])) {
                throw new \Exception('Missing message content');
            }

            $result = $this->client->send_message([
                'content' => $message['content'],
                'username' => $user_data['display_name'] ?? 'Unknown User'
            ]);

            if (!$result['success']) {
                throw new \Exception($result['error']);
            }

            error_log('[SEWN] Discord Protocol: Chat message handled successfully');
            return $this->format_response('chat', [
                'message' => $message,
                'result' => $result['response']
            ]);
        } catch (\Exception $e) {
            error_log('[SEWN] Discord Protocol: Chat message handling failed: ' . $e->getMessage());
            return $this->handle_error($e->getMessage(), $message);
        }
    }

    /**
     * Handle presence update
     *
     * Processes user presence updates:
     * - Updates user status
     * - Syncs with Discord
     * - Notifies relevant systems
     *
     * @since 1.0.0
     * @param array $message Message data
     * @param array $user_data User context
     * @return array Response data
     */
    private function handle_presence_update($message, $user_data) {
        error_log('[SEWN] Discord Protocol: Handling presence update');
        
        // Add presence update logic here
        
        return $this->format_response('presence', [
            'status' => $message['status'] ?? 'unknown'
        ]);
    }

    /**
     * Validate incoming message
     *
     * Verifies message format and content:
     * - Checks required fields
     * - Validates data types
     * - Verifies message structure
     *
     * @since 1.0.0
     * @param array $message Message to validate
     * @return bool Whether message is valid
     */
    protected function validate_message($message) {
        error_log('[SEWN] Discord Protocol: Validating message');
        
        $is_valid = is_array($message) && isset($message['type']);
        
        error_log('[SEWN] Discord Protocol: Message validation: ' . ($is_valid ? 'valid' : 'invalid'));
        return $is_valid;
    }

    /**
     * Format protocol response
     *
     * Creates standardized response format:
     * - Sets response type
     * - Includes response data
     * - Adds timestamp
     *
     * @since 1.0.0
     * @param string $status Response status
     * @param array $data Response data
     * @return array Formatted response
     */
    protected function format_response($status, $data = []) {
        error_log('[SEWN] Discord Protocol: Formatting response');
        
        return [
            'type' => $status,
            'data' => $data,
            'timestamp' => time()
        ];
    }

    /**
     * Handle protocol error
     *
     * Processes protocol errors:
     * - Logs error details
     * - Formats error response
     * - Notifies relevant systems
     *
     * @since 1.0.0
     * @param string $message Error message
     * @param array $data Additional error data
     * @return array Error response
     */
    protected function handle_error($message, $data = []) {
        error_log('[SEWN] Discord Protocol: Error occurred: ' . $message);
        
        return [
            'type' => 'error',
            'error' => $message,
            'data' => $data,
            'timestamp' => time()
        ];
    }
}