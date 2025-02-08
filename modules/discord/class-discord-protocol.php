<?php
/**
 * Location: modules/discord/class-discord-protocol.php
 * Dependencies: Protocol_Base, Discord_Client
 * Variables/Classes: Discord_Protocol
 * Purpose: Implements Discord-specific WebSocket protocol for real-time chat and presence updates
 */

namespace SEWN\WebSockets\Modules\Discord;

use SEWN\WebSockets\Protocol_Base;

/**
 * @property Discord_Client $client
 */
class Discord_Protocol extends Protocol_Base {
    private $client;
    private $config;

    public function __construct(Discord_Client $client) {
        $this->client = $client;
    }

    public function register() {
        add_action('sewn_ws_register_protocols', [$this, 'register_protocol']);
        add_action('sewn_ws_init', [$this, 'init_config']);
        add_filter('sewn_ws_client_config', [$this, 'add_protocol_config']);
    }

    public function register_protocol($handler) {
        $handler->register_protocol('discord', $this);
    }

    public function init_config() {
        $this->config = [
            'version' => '1.0',
            'min_php' => '7.4',
            'bot_status' => $this->client->get_status()
        ];
    }

    public function handle_message($message, $context) {
        if (!$this->validate_message($message)) {
            return $this->handle_error('Invalid message format', $message);
        }

        $message_type = $message['type'] ?? 'unknown';
        $user_data = $context['user_data'] ?? [];

        switch ($message_type) {
            case 'chat':
                return $this->handle_chat_message($message, $user_data);
            case 'presence':
                return $this->handle_presence_update($message, $user_data);
            default:
                return $this->handle_error('Unsupported message type', $message);
        }
    }

    public function add_protocol_config($config) {
        $config['discord'] = [
            'enabled' => true,
            'version' => $this->config['version'],
            'features' => [
                'chat' => true,
                'presence' => true,
                'streaming' => true
            ],
            'bot_status' => $this->config['bot_status']
        ];
        return $config;
    }

    private function handle_chat_message($message, $user_data) {
        if (!isset($message['content'])) {
            return $this->handle_error('Missing message content', $message);
        }

        $result = $this->client->send_message([
            'content' => $message['content'],
            'username' => $user_data['display_name'] ?? 'Unknown User'
        ]);

        if (!$result['success']) {
            return $this->handle_error($result['error'], $message);
        }

        return $this->format_response('chat', [
            'message' => $message,
            'result' => $result['response']
        ]);
    }

    private function handle_presence_update($message, $user_data) {
        // Implement presence update logic here
        return $this->format_response('presence', [
            'status' => $message['status'] ?? 'unknown'
        ]);
    }
}