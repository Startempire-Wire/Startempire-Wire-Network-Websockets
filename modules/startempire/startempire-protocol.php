<?php
namespace SEWN\WebSockets\Modules\Startempire;

use SEWN\WebSockets\Modules\Module_Base;
use SEWN\WebSockets\Protocols\Core_Protocol;

class Startempire_Module extends Module_Base {
    private $protocol_handler;

    public function metadata(): array {
        return [
            'name' => 'Startempire Protocol',
            'slug' => 'startempire',
            'description' => 'Core network communication protocol',
            'version' => '2.0.0',
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
        $base_config = parent::admin_ui();
        
        return array_merge($base_config, [
            'menu_title' => 'Network Protocol',
            'settings' => [
                [
                    'name' => 'sewn_ws_network_key',
                    'label' => 'API Key',
                    'type' => 'password',
                    'sanitize' => 'sanitize_key',
                    'section' => 'default'
                ],
                [
                    'name' => 'sewn_ws_protocol_version',
                    'label' => 'Protocol Version',
                    'type' => 'select',
                    'options' => [
                        'v1' => 'Version 1.0',
                        'v2' => 'Version 2.0 (Beta)'
                    ],
                    'sanitize' => 'sanitize_text_field',
                    'section' => 'advanced'
                ]
            ],
            'sections' => [
                [
                    'id' => 'advanced',
                    'title' => 'Advanced Protocol Settings',
                    'callback' => [$this, 'render_advanced_section']
                ]
            ]
        ]);
    }

    public function init() {
        $this->check_dependencies();
        $this->initialize_protocol();
        $this->register_hooks();
    }

    private function initialize_protocol() {
        $this->protocol_handler = new Core_Protocol(
            get_option('sewn_ws_network_key'),
            get_option('sewn_ws_protocol_version', 'v1')
        );
    }

    private function register_hooks() {
        add_action('sewn_ws_register_protocols', [$this, 'register_protocols']);
        add_filter('sewn_ws_auth_handler', [$this, 'handle_auth']);
        add_filter('sewn_ws_message_validator', [$this, 'validate_messages']);
    }

    public function register_protocols($handler) {
        $handler->register_protocol('startempire', $this->protocol_handler);
    }

    public function handle_auth($token) {
        return apply_filters('sewn_ringleader_validate_token', $token);
    }

    public function validate_messages($validator) {
        return $this->protocol_handler->validate_message_structure($validator);
    }

    public function render_advanced_section() {
        echo '<p>Configure advanced protocol settings for network communication</p>';
    }

    public function activate() {
        if (!get_option('sewn_ws_protocol_version')) {
            update_option('sewn_ws_protocol_version', 'v1');
        }
    }
}

// Initialize module
add_action('sewn_ws_init', function() {
    $protocol = new Startempire_Module();
    $protocol->init();
}); 