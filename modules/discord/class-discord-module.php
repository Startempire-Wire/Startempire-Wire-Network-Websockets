<?php
/**
 * Discord Module - Reference Implementation
 *
 * This module serves as the reference implementation for all WebSocket modules.
 * It demonstrates the standard patterns, required methods, and best practices.
 * 
 * Module Lifecycle:
 * 1. Construction - Basic property initialization
 * 2. Registration - Module registered with system
 * 3. Initialization - Full module setup
 * 4. Activation - Module activated and configured
 * 5. Operation - Normal message handling
 * 6. Deactivation - Clean shutdown
 *
 * Integration Points:
 * - Ring Leader authentication
 * - WordPress role system
 * - WebSocket protocol handler
 * - Discord API client
 *
 * @package SEWN\WebSockets\Modules\Discord
 * @since 1.0.0
 */

namespace SEWN\WebSockets\Modules\Discord;

use SEWN\WebSockets\Config;
use SEWN\WebSockets\Module_Base;

/**
 * Discord Module Class
 *
 * Implements real-time Discord integration including:
 * - Chat synchronization
 * - Presence updates
 * - Role synchronization
 * - Streaming capabilities
 *
 * Required Configuration:
 * - Discord Bot Token
 * - Guild (Server) ID
 * - Webhook URL
 *
 * Example Usage:
 * ```php
 * $module = new Discord_Module();
 * $module->init();
 * ```
 *
 * @since 1.0.0
 */
class Discord_Module extends Module_Base {
    /**
     * Discord client instance
     *
     * @var Discord_Client
     */
    private $client;

    /**
     * Discord protocol instance
     *
     * @var Discord_Protocol
     */
    private $protocol;

    /**
     * Constructor
     * 
     * Initialize basic module properties. Full initialization happens in init().
     * 
     * @since 1.0.0
     */
    public function __construct() {
        parent::__construct();
        $this->slug = 'discord';
        error_log('[SEWN] Discord Module: Constructor called');
    }

    /**
     * Get module slug
     *
     * @since 1.0.0
     * @return string Module slug
     */
    public function get_module_slug(): string {
        return 'discord';
    }

    /**
     * Get module metadata
     *
     * Provides information about the module including:
     * - Name and version
     * - Description and author
     * - Required dependencies
     *
     * @since 1.0.0
     * @return array Module metadata
     */
    public function metadata(): array {
        return [
            'module_slug' => 'discord',
            'name' => __('Discord Integration', 'sewn-ws'),
            'version' => '1.0.0',
            'description' => __('Real-time Discord chat integration', 'sewn-ws'),
            'author' => 'Startempire Team',
            'dependencies' => ['ring-leader']
        ];
    }
    
    /**
     * Get module requirements
     *
     * Specifies required plugins and minimum versions.
     * Used during dependency checking before activation.
     *
     * @since 1.0.0
     * @return array Required dependencies
     */
    public function requires(): array {
        return [
            [
                'name' => 'Ring Leader Plugin',
                'class' => 'SEWN\RingLeader\Core\Auth_Handler',
                'version' => '1.4.0'
            ]
        ];
    }

    /**
     * Get module admin UI configuration
     *
     * Defines the admin interface including:
     * - Menu structure
     * - Settings fields
     * - Field validation
     * - Section organization
     *
     * @since 1.0.0
     * @return array Admin UI configuration
     */
    public function admin_ui(): array {
        return [
            'menu_title' => __('Discord Integration Settings', 'sewn-ws'),
            'capability' => 'manage_options',
            'settings' => [
                [
                    'name' => 'bot_token',
                    'label' => __('Bot Token', 'sewn-ws'),
                    'type' => 'password',
                    'description' => __('Discord bot token from the Developer Portal', 'sewn-ws'),
                    'sanitize' => 'sanitize_text_field',
                    'section' => 'discord_credentials'
                ],
                [
                    'name' => 'guild_id',
                    'label' => __('Server ID', 'sewn-ws'),
                    'type' => 'text',
                    'description' => __('Discord server/guild ID to connect to', 'sewn-ws'),
                    'sanitize' => 'absint',
                    'section' => 'discord_credentials'
                ],
                [
                    'name' => 'webhook_url',
                    'label' => __('Webhook URL', 'sewn-ws'),
                    'type' => 'url',
                    'description' => __('Discord webhook URL for message delivery', 'sewn-ws'),
                    'sanitize' => 'esc_url_raw',
                    'section' => 'discord_webhooks'
                ],
                [
                    'name' => 'streaming_enabled',
                    'label' => __('Enable Streaming', 'sewn-ws'),
                    'type' => 'checkbox',
                    'description' => __('Enable Discord streaming integration', 'sewn-ws'),
                    'sanitize' => 'rest_sanitize_boolean',
                    'section' => 'discord_features'
                ],
                [
                    'name' => 'role_sync_enabled',
                    'label' => __('Role Synchronization', 'sewn-ws'),
                    'type' => 'checkbox',
                    'description' => __('Sync Discord roles with WordPress user roles', 'sewn-ws'),
                    'sanitize' => 'rest_sanitize_boolean',
                    'section' => 'discord_features'
                ],
                [
                    'name' => 'notification_channel',
                    'label' => __('Notification Channel', 'sewn-ws'),
                    'type' => 'text',
                    'description' => __('Channel ID for system notifications', 'sewn-ws'),
                    'sanitize' => 'absint',
                    'section' => 'discord_webhooks'
                ]
            ],
            'sections' => [
                [
                    'id' => 'discord_credentials',
                    'title' => __('API Credentials', 'sewn-ws'),
                    'callback' => [$this, 'render_credentials_section']
                ],
                [
                    'id' => 'discord_webhooks',
                    'title' => __('Webhook Configuration', 'sewn-ws'),
                    'callback' => [$this, 'render_webhook_section']
                ],
                [
                    'id' => 'discord_features',
                    'title' => __('Feature Settings', 'sewn-ws'),
                    'callback' => [$this, 'render_features_section']
                ]
            ]
        ];
    }

    /**
     * Check module dependencies
     *
     * Verifies all required plugins and classes exist.
     * Returns warnings instead of blocking activation.
     *
     * @since 1.0.0
     * @return array|true True if dependencies met, array of warnings if not
     */
    public function check_dependencies() {
        error_log('[SEWN] Discord Module: Checking dependencies');
        
        $warnings = [];
        
        if (!class_exists('SEWN\RingLeader\Core\Auth_Handler')) {
            error_log('[SEWN] Discord Module: Ring Leader dependency not found - continuing with limited functionality');
            $warnings[] = [
                'type' => 'warning',
                'message' => __('Ring Leader Plugin not found. Some features will be limited.', 'sewn-ws')
            ];
        }
        
        error_log('[SEWN] Discord Module: Dependency check complete with ' . count($warnings) . ' warnings');
        
        // Return true if no warnings, otherwise return the warnings
        return empty($warnings) ? true : [
            'warnings' => $warnings,
            'can_activate' => true // Explicitly allow activation
        ];
    }

    /**
     * Initialize module
     *
     * Full module initialization including:
     * - Dependency checking
     * - Component initialization
     * - Hook registration
     * - Protocol setup
     *
     * @since 1.0.0
     * @return void
     */
    public function init() {
        // Log initialization start
        error_log('[SEWN] Discord Module: Starting initialization');

        try {
            // Check dependencies but don't block initialization
            $dependency_check = $this->check_dependencies();
            if ($dependency_check !== true) {
                error_log('[SEWN] Discord Module: Initializing with warnings: ' . print_r($dependency_check, true));
            }

            // Load required files
            $base_path = dirname(__FILE__);
            require_once $base_path . '/class-discord-client.php';
            require_once $base_path . '/class-discord-protocol.php';
            
            // Initialize components with limited functionality if needed
            try {
                $this->initialize_client();
            } catch (\Exception $e) {
                error_log('[SEWN] Discord Module: Client initialization warning: ' . $e->getMessage());
                // Continue initialization despite client error
            }

            try {
                $this->initialize_protocol();
            } catch (\Exception $e) {
                error_log('[SEWN] Discord Module: Protocol initialization warning: ' . $e->getMessage());
                // Continue initialization despite protocol error
            }

            try {
                $this->register_hooks();
            } catch (\Exception $e) {
                error_log('[SEWN] Discord Module: Hook registration warning: ' . $e->getMessage());
                // Continue despite hook registration error
            }

            // Log successful initialization
            error_log('[SEWN] Discord Module: Initialized with limited functionality');
        } catch (\Exception $e) {
            error_log('[SEWN] Discord Module: Initialization warning: ' . $e->getMessage());
            // Don't throw the exception, allow activation with limited functionality
        }
    }

    /**
     * Initialize Discord client
     *
     * Sets up the Discord API client with proper configuration.
     * Handles connection and authentication setup.
     *
     * @since 1.0.0
     * @return void
     */
    private function initialize_client() {
        try {
            $this->client = new Discord_Client();
            error_log('[SEWN] Discord Module: Client initialized');
        } catch (\Exception $e) {
            error_log('[SEWN] Discord Module: Client initialization warning: ' . $e->getMessage());
            throw $e; // Propagate to parent try-catch
        }
    }

    /**
     * Initialize Discord protocol
     *
     * Sets up the WebSocket protocol handler with:
     * - Message handling
     * - Event routing
     * - Error management
     *
     * @since 1.0.0
     * @return void
     */
    private function initialize_protocol() {
        try {
            if (!$this->client) {
                throw new \Exception('Cannot initialize protocol: Client not available');
            }
            $this->protocol = new Discord_Protocol($this->client);
            $this->protocol->register();
            error_log('[SEWN] Discord Module: Protocol initialized');
        } catch (\Exception $e) {
            error_log('[SEWN] Discord Module: Protocol initialization warning: ' . $e->getMessage());
            throw $e; // Propagate to parent try-catch
        }
    }

    /**
     * Register module hooks
     *
     * Sets up all WordPress action and filter hooks:
     * - Protocol registration
     * - Client connection handling
     * - Authentication validation
     *
     * @since 1.0.0
     * @return void
     */
    private function register_hooks() {
        error_log('[SEWN] Discord Module: Registering hooks');
        add_action('sewn_ws_register_protocols', [$this, 'register_protocols']);
        add_action('sewn_ws_client_connected', [$this, 'handle_client_connect']);
        add_filter('sewn_ws_auth_validation', [$this, 'validate_discord_tokens']);
        error_log('[SEWN] Discord Module: Hooks registered');
    }

    /**
     * Register protocol with handler
     *
     * Registers the Discord protocol with the WebSocket handler.
     * Called during system initialization.
     *
     * @since 1.0.0
     * @param object $handler Protocol handler instance
     * @return void
     */
    public function register_protocols($handler) {
        error_log('[SEWN] Discord Module: Registering protocol');
        $handler->register_protocol('discord', $this->protocol);
        error_log('[SEWN] Discord Module: Protocol registered');
    }

    /**
     * Render credentials section
     *
     * Displays the API credentials section in admin UI.
     * Includes help text and external links.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_credentials_section() {
        echo '<p>' . esc_html__('Configure Discord API credentials from the', 'sewn-ws') . 
             ' <a href="https://discord.com/developers/applications" target="_blank">' . 
             esc_html__('Discord Developer Portal', 'sewn-ws') . '</a></p>';
    }

    /**
     * Render webhook section
     *
     * Displays the webhook configuration section in admin UI.
     * Includes setup instructions and best practices.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_webhook_section() {
        echo '<p>' . esc_html__('Configure incoming webhooks and notification channels for Discord integration.', 'sewn-ws') . '</p>';
    }

    /**
     * Render features section
     *
     * Displays the features configuration section in admin UI.
     * Lists available features and their requirements.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_features_section() {
        echo '<p>' . esc_html__('Enable and configure Discord integration features like streaming and role synchronization.', 'sewn-ws') . '</p>';
    }

    /**
     * Validate Discord tokens
     *
     * Validates Discord authentication tokens through Ring Leader.
     * Used during client authentication.
     *
     * @since 1.0.0
     * @param string $token Token to validate
     * @return mixed Validated token
     */
    public function validate_discord_tokens($token) {
        error_log('[SEWN] Discord Module: Validating token');
        return apply_filters('sewn_ringleader_validate_token', $token);
    }

    /**
     * Module activation handler
     *
     * Performs module activation tasks:
     * - Sets up roles and capabilities
     * - Initializes default settings
     * - Verifies configuration
     *
     * Called when module is activated through admin UI.
     *
     * @since 1.0.0
     * @return void
     * @throws \Exception If activation fails
     */
    public function activate() {
        // Log activation start
        error_log('[SEWN] Discord Module: Starting activation');

        try {
            // Check dependencies first
            $dependency_check = $this->check_dependencies();
            if ($dependency_check !== true) {
                error_log('[SEWN] Discord Module: Activation failed - dependency check failed');
                throw new \Exception('Dependencies not met: ' . print_r($dependency_check, true));
            }

            // Set default capabilities
            error_log('[SEWN] Discord Module: Setting up roles');
            add_role('sewn_discord_bot', __('Discord Bot'), [
                'read' => true,
                'manage_discord' => true
            ]);

            // Initialize default settings
            error_log('[SEWN] Discord Module: Initializing default settings');
            $default_settings = [
                'streaming_enabled' => false,
                'role_sync_enabled' => false,
                'notification_channel' => ''
            ];

            foreach ($default_settings as $key => $value) {
                if (Config::get_module_setting('discord', $key) === null) {
                    error_log("[SEWN] Discord Module: Setting default value for {$key}");
                    Config::set_module_setting('discord', $key, $value);
                }
            }

            // Verify configuration
            error_log('[SEWN] Discord Module: Verifying configuration');
            $required_settings = ['bot_token', 'guild_id', 'webhook_url'];
            $missing_settings = [];
            
            foreach ($required_settings as $setting) {
                if (!Config::get_module_setting('discord', $setting)) {
                    $missing_settings[] = $setting;
                }
            }
            
            if (!empty($missing_settings)) {
                error_log('[SEWN] Discord Module: Missing required settings: ' . implode(', ', $missing_settings));
            }

            // Log successful activation
            error_log('[SEWN] Discord Module: Activated successfully');
        } catch (\Exception $e) {
            error_log('[SEWN] Discord Module: Activation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Module deactivation handler
     *
     * Cleans up module resources:
     * - Removes roles
     * - Cleans up settings
     * - Closes connections
     *
     * Called when module is deactivated through admin UI.
     *
     * @since 1.0.0
     * @return void
     */
    public function deactivate() {
        error_log('[SEWN] Discord Module: Starting deactivation');
        
        // Remove custom role
        remove_role('sewn_discord_bot');
        
        // Close any active connections
        if ($this->client) {
            // Add connection cleanup here
        }
        
        error_log('[SEWN] Discord Module: Deactivated successfully');
    }
}
