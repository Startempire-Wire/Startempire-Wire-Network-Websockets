<?php
/**
 * Location: modules/startempire/class-startempire-protocol.php
 * Dependencies: Protocol_Base
 * Variables/Classes: Startempire_Protocol
 * Purpose: Core protocol implementation for Startempire Wire Network's WebSocket communication.
 */

namespace SEWN\WebSockets\Modules\Startempire;

use SEWN\WebSockets\Protocol_Base;

class Startempire_Protocol extends Protocol_Base {
    private $config;

    public function register() {
        add_action('sewn_ws_register_protocols', [$this, 'register_protocol']);
        add_action('sewn_ws_init', [$this, 'init_config']);
        add_filter('sewn_ws_client_config', [$this, 'add_protocol_config']);
    }

    public function register_protocol($handler) {
        $handler->register_protocol('startempire', $this);
    }

    public function init_config() {
        $this->config = [
            'version' => '1.0',
            'min_php' => '7.4',
            'network_id' => get_option('sewn_ws_network_id', ''),
            'network_key' => get_option('sewn_ws_network_key', ''),
            'features' => [
                'content_sync' => true,
                'member_auth' => true,
                'data_distribution' => true
            ]
        ];
    }

    public function handle_message($message, $context) {
        $message_type = $message['type'] ?? 'unknown';
        $user_data = $context['user_data'] ?? [];

        if (!$this->validate_message($message)) {
            return $this->handle_error('Invalid message format');
        }

        switch ($message_type) {
            case 'content_sync':
                return $this->handle_content_sync($message, $user_data);
            case 'member_auth':
                return $this->handle_member_auth($message, $user_data);
            case 'data_distribution':
                return $this->handle_data_distribution($message, $user_data);
            default:
                return $this->handle_error('Unsupported message type');
        }
    }

    public function add_protocol_config($config) {
        $config['startempire'] = [
            'enabled' => true,
            'version' => $this->config['version'],
            'network_id' => $this->config['network_id'],
            'features' => $this->config['features']
        ];
        return $config;
    }

    private function handle_content_sync($message, $user_data) {
        if (!$this->verify_network_access($user_data)) {
            return $this->handle_error('Insufficient network access');
        }

        return $this->format_response('success', [
            'type' => 'content_sync',
            'data' => $message['data'] ?? []
        ]);
    }

    private function handle_member_auth($message, $user_data) {
        if (!isset($message['auth_data'])) {
            return $this->handle_error('Missing authentication data');
        }

        return $this->format_response('success', [
            'type' => 'member_auth',
            'auth_status' => 'verified',
            'member_data' => $user_data
        ]);
    }

    private function handle_data_distribution($message, $user_data) {
        if (!$this->verify_network_access($user_data)) {
            return $this->handle_error('Insufficient network access');
        }

        return $this->format_response('success', [
            'type' => 'data_distribution',
            'data' => $message['data'] ?? []
        ]);
    }

    private function verify_network_access($user_data) {
        return isset($user_data['network_access']) && $user_data['network_access'] === true;
    }
} 