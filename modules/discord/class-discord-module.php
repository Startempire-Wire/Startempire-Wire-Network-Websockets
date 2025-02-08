<?php
/**
 * Location: modules/discord/class-discord-module.php
 * Dependencies: Module_Base, Discord_Protocol, Ring Leader plugin
 * Variables/Classes: Discord_Module, $client
 * Purpose: Handles Discord integration for WebSocket communications and authentication. Manages protocol registration, token validation, and role synchronization with Discord's API.
 */

namespace SEWN\WebSockets\Modules\Discord;

use SEWN\WebSockets\Module_Base;

class Discord_Module extends Module_Base {
    private $client;
    private $protocol;

    public function get_module_slug(): string {
        return 'discord';
    }

    public function metadata(): array {
        return [
            'module_slug' => 'discord',
            'name' => __('Discord Integration', 'sewn-ws'),
            'version' => '1.0.0',
            'description' => __('Real-time Discord chat integration', 'sewn-ws'),
            'author' => 'StartEmpire Team',
            'dependencies' => ['ring-leader']
        ];
    }
    
    public function requires(): array {
        return [
            [
                'name' => 'Ring Leader Plugin',
                'class' => 'SEWN\RingLeader\Core\Auth_Handler',
                'version' => '1.4.0'
            ]
        ];
    }

    public function admin_ui(): array {
        return [
            'menu_title' => __('Discord Integration Settings', 'sewn-ws'),
            'capability' => 'manage_options',
            'settings' => [
                [
                    'name' => 'sewn_ws_discord_bot_token',
                    'label' => __('Bot Token', 'sewn-ws'),
                    'type' => 'password',
                    'description' => __('Discord bot token from the Developer Portal', 'sewn-ws'),
                    'sanitize' => 'sanitize_text_field',
                    'section' => 'discord_credentials'
                ],
                [
                    'name' => 'sewn_ws_discord_guild_id',
                    'label' => __('Server ID', 'sewn-ws'),
                    'type' => 'text',
                    'description' => __('Discord server/guild ID to connect to', 'sewn-ws'),
                    'sanitize' => 'absint',
                    'section' => 'discord_credentials'
                ],
                [
                    'name' => 'sewn_ws_discord_webhook',
                    'label' => __('Webhook URL', 'sewn-ws'),
                    'type' => 'url',
                    'description' => __('Discord webhook URL for message delivery', 'sewn-ws'),
                    'sanitize' => 'esc_url_raw',
                    'section' => 'discord_webhooks'
                ],
                [
                    'name' => 'sewn_ws_discord_streaming',
                    'label' => __('Enable Streaming', 'sewn-ws'),
                    'type' => 'checkbox',
                    'description' => __('Enable Discord streaming integration', 'sewn-ws'),
                    'sanitize' => 'rest_sanitize_boolean',
                    'section' => 'discord_features'
                ],
                [
                    'name' => 'sewn_ws_discord_role_sync',
                    'label' => __('Role Synchronization', 'sewn-ws'),
                    'type' => 'checkbox',
                    'description' => __('Sync Discord roles with WordPress user roles', 'sewn-ws'),
                    'sanitize' => 'rest_sanitize_boolean',
                    'section' => 'discord_features'
                ],
                [
                    'name' => 'sewn_ws_discord_notification_channel',
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

    public function check_dependencies() {
        if (!class_exists('SEWN\RingLeader\Core\Auth_Handler')) {
            return [
                'error' => __('Discord Module requires Ring Leader Plugin v1.4.0+', 'sewn-ws')
            ];
        }
        return true;
    }

    public function init() {
        if (!$this->check_dependencies()) {
            return;
        }

        // Load required files
        require_once dirname(__FILE__) . '/class-discord-client.php';
        require_once dirname(__FILE__) . '/class-discord-protocol.php';
        
        $this->initialize_client();
        $this->initialize_protocol();
        $this->register_hooks();
    }

    private function initialize_client() {
        $this->client = new Discord_Client(
            get_option('sewn_ws_discord_bot_token'),
            get_option('sewn_ws_discord_guild_id'),
            get_option('sewn_ws_discord_webhook')
        );
    }

    private function initialize_protocol() {
        $this->protocol = new Discord_Protocol($this->client);
        $this->protocol->register();
    }

    private function register_hooks() {
        add_action('sewn_ws_register_protocols', [$this, 'register_protocols']);
        add_action('sewn_ws_client_connected', [$this, 'handle_client_connect']);
        add_filter('sewn_ws_auth_validation', [$this, 'validate_discord_tokens']);
    }

    public function register_protocols($handler) {
        $handler->register_protocol('discord', $this->protocol);
    }

    public function render_credentials_section() {
        echo '<p>' . esc_html__('Configure Discord API credentials from the', 'sewn-ws') . 
             ' <a href="https://discord.com/developers/applications" target="_blank">' . 
             esc_html__('Discord Developer Portal', 'sewn-ws') . '</a></p>';
    }

    public function render_webhook_section() {
        echo '<p>' . esc_html__('Configure incoming webhooks and notification channels for Discord integration.', 'sewn-ws') . '</p>';
    }

    public function render_features_section() {
        echo '<p>' . esc_html__('Enable and configure Discord integration features like streaming and role synchronization.', 'sewn-ws') . '</p>';
    }

    public function validate_discord_tokens($token) {
        return apply_filters('sewn_ringleader_validate_token', $token);
    }

    public function activate() {
        // Set default capabilities
        add_role('sewn_discord_bot', __('Discord Bot'), [
            'read' => true,
            'manage_discord' => true
        ]);
    }

    public function deactivate() {
        remove_role('sewn_discord_bot');
    }
}
