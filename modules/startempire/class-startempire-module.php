<?php
/**
 * Location: modules/startempire/class-startempire-module.php
 * Dependencies: Module_Base, Startempire_Protocol
 * Variables/Classes: Startempire_Module, $protocol
 * Purpose: Core module for Startempire Network's custom WebSocket protocol implementation. Handles network authentication, message routing, and integration with Ring Leader's data distribution system.
 */

namespace SEWN\WebSockets\Modules;

use SEWN\WebSockets\Module_Base;
use SEWN\WebSockets\Protocols\Startempire_Protocol;

class Startempire_Module extends Module_Base {
    private $protocol;
    
    public function get_module_slug(): string {
        return 'startempire';
    }
    
    public function metadata(): array {
        return [
            'module_slug' => 'startempire',
            'name' => __('Startempire Protocol', 'sewn-ws'),
            'version' => '1.0.0',
            'description' => __('Core network communication protocol', 'sewn-ws'),
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
            'menu_title' => 'Network Protocol',
            'capability' => 'manage_network_websockets',
            'settings' => [
                [
                    'name' => 'sewn_ws_protocol_version',
                    'label' => __('Protocol Version', 'sewn-ws'),
                    'type' => 'select',
                    'options' => [
                        'v1' => 'Version 1.0',
                        'v2' => 'Version 2.0 (Beta)'
                    ],
                    'sanitize' => 'sanitize_text_field',
                    'section' => 'protocol_settings'
                ]
            ],
            'sections' => [
                [
                    'id' => 'protocol_settings',
                    'title' => __('Protocol Configuration', 'sewn-ws'),
                    'callback' => [$this, 'render_settings_section']
                ]
            ]
        ];
    }

    public function init() {
        $this->protocol = new Startempire_Protocol();
        $this->protocol->register();
        
        add_action('sewn_ws_module_ready', [$this, 'register_hooks']);
    }

    public function register_hooks() {
        add_filter('sewn_ws_auth_validation', [$this, 'validate_network_tokens']);
    }

    public function validate_network_tokens($token) {
        return apply_filters('sewn_ringleader_validate_token', $token);
    }

    public function render_settings_section() {
        echo '<p>'.__('Configure core network protocol settings', 'sewn-ws').'</p>';
    }

    public function get_required_capabilities() {
        return ['manage_network_websockets'];
    }
}
