<?php
/**
 * Location: modules/discord/class-discord-module.php
 * Dependencies: Module_Base, Discord_Protocol, Ring Leader plugin
 * Variables/Classes: Discord_Module, $client
 * Purpose: Handles Discord integration for WebSocket communications and authentication. Manages protocol registration, token validation, and role synchronization with Discord's API.
 */

namespace SEWN\WebSockets\Modules\Discord;

use SEWN\WebSockets\Module_Base;
use SEWN\WebSockets\Protocols\Discord_Protocol;

class Discord_Module extends Module_Base {
    private $client;

    public function metadata() {
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
            'menu_title' => 'Discord Settings',
            'capability' => 'manage_options',
            'settings' => [
                [
                    'name' => 'sewn_ws_discord_bot_token',
                    'label' => 'Bot Token',
                    'type' => 'password',
                    'sanitize' => 'sanitize_text_field',
                    'section' => 'discord_credentials'
                ],
                [
                    'name' => 'sewn_ws_discord_guild_id',
                    'label' => 'Server ID',
                    'type' => 'text',
                    'sanitize' => 'absint',
                    'section' => 'discord_credentials'
                ],
                [
                    'name' => 'sewn_ws_discord_webhook',
                    'label' => 'Webhook URL',
                    'type' => 'url',
                    'sanitize' => 'esc_url_raw',
                    'section' => 'discord_webhooks'
                ]
            ],
            'sections' => [
                [
                    'id' => 'discord_credentials',
                    'title' => 'API Credentials',
                    'callback' => [$this, 'render_credentials_section']
                ],
                [
                    'id' => 'discord_webhooks',
                    'title' => 'Webhook Configuration',
                    'callback' => [$this, 'render_webhook_section']
                ]
            ]
        ];
    }

    public function check_dependencies() {
        if (!class_exists('SEWN\RingLeader\Core\Auth_Handler')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                _e('Discord Module requires Ring Leader Plugin v1.4.0+', 'sewn-ws');
                echo '</p></div>';
            });
            return false;
        }
        return true;
    }

    public function init() {
        if (!$this->check_dependencies()) {
            return;
        }
        $this->initialize_client();
        $this->register_hooks();
    }

    private function initialize_client() {
        $this->client = new Discord_Client(
            get_option('sewn_ws_discord_bot_token'),
            get_option('sewn_ws_discord_guild_id'),
            get_option('sewn_ws_discord_webhook')
        );
    }

    private function register_hooks() {
        add_action('sewn_ws_register_protocols', [$this, 'register_protocols']);
        add_action('sewn_ws_client_connected', [$this, 'handle_client_connect']);
        add_filter('sewn_ws_auth_validation', [$this, 'validate_discord_tokens']);
    }

    public function register_protocols($handler) {
        $handler->register_protocol('discord', new Discord_Protocol($this->client));
    }

    public function render_credentials_section() {
        echo '<p>Configure Discord API credentials from <a href="https://discord.com/developers/applications" target="_blank">Discord Developer Portal</a></p>';
    }

    public function render_webhook_section() {
        echo '<p>Configure incoming webhooks for Discord channel integration</p>';
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
