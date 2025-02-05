<?php
/**
 * Location: modules/startempire/class-startempire-protocol.php
 * Dependencies: Protocol_Base, WebSocket server
 * Variables/Classes: Startempire_Protocol, $protocol_handler
 * Purpose: Defines the Startempire Network's primary WebSocket communication protocol and configuration. Ensures compatibility with network membership tiers and Ring Leader data distribution requirements.
 */

namespace SEWN\WebSockets\Protocols;

use SEWN\WebSockets\Protocol_Base;

class Startempire_Protocol extends Protocol_Base {
    private $protocol_handler;

    public function register() {
        add_action('sewn_ws_register_protocols', [$this, 'register_protocol']);
        add_action('sewn_ws_init', [$this, 'init_protocol_handler']);
        add_filter('sewn_ws_client_config', [$this, 'add_protocol_config']);
    }

    public function register_protocol($handler) {
        $handler->register_protocol('startempire', $this);
    }

    public function init_protocol_handler() {
        $this->protocol_handler = [
            'version' => defined('SEWN_PROTOCOL_VERSION') ? SEWN_PROTOCOL_VERSION : '2.0',
            'auth_type' => 'ring-leader'
        ];
    }

    public function handle_message($message, $context) {
        return [
            'status' => 'received',
            'protocol' => 'startempire',
            'handler_version' => $this->protocol_handler['version']
        ];
    }

    public function add_protocol_config($config) {
        $config['startempire'] = [
            'enabled' => !empty($this->protocol_handler),
            'version' => $this->protocol_handler['version'] ?? 'unconfigured'
        ];
        return $config;
    }
} 