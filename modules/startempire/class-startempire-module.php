<?php
/**
 * Location: modules/startempire/class-startempire-module.php
 * Dependencies: Module_Base, Startempire_Protocol
 * Variables/Classes: Startempire_Module, $protocol
 * Purpose: Core module for Startempire Network's custom WebSocket protocol implementation. Handles network authentication, message routing, and integration with Ring Leader's data distribution system.
 */

namespace SEWN\WebSockets\Modules\Startempire;

use SEWN\WebSockets\Module_Base;
use SEWN\WebSockets\Protocol_Base;

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
            'menu_title' => __('Network Protocol Settings', 'sewn-ws'),
            'capability' => 'manage_options',
            'settings' => [
                [
                    'name' => 'sewn_ws_protocol_version',
                    'label' => __('Protocol Version', 'sewn-ws'),
                    'type' => 'select',
                    'description' => __('Select the protocol version to use', 'sewn-ws'),
                    'section' => 'protocol_settings',
                    'options' => [
                        'v1' => 'Version 1.0',
                        'v2' => 'Version 2.0 (Beta)'
                    ],
                    'sanitize' => 'sanitize_text_field'
                ],
                [
                    'name' => 'sewn_ws_network_id',
                    'label' => __('Network ID', 'sewn-ws'),
                    'type' => 'text',
                    'description' => __('Unique identifier for this network node', 'sewn-ws'),
                    'section' => 'protocol_settings',
                    'sanitize' => 'sanitize_text_field'
                ],
                [
                    'name' => 'sewn_ws_network_key',
                    'label' => __('Network Key', 'sewn-ws'),
                    'type' => 'password',
                    'description' => __('Secret key for network authentication', 'sewn-ws'),
                    'section' => 'protocol_settings',
                    'sanitize' => 'sanitize_text_field'
                ],
                [
                    'name' => 'sewn_ws_content_sync',
                    'label' => __('Content Sync', 'sewn-ws'),
                    'type' => 'checkbox',
                    'description' => __('Enable real-time content synchronization', 'sewn-ws'),
                    'section' => 'features',
                    'sanitize' => 'rest_sanitize_boolean'
                ],
                [
                    'name' => 'sewn_ws_member_auth',
                    'label' => __('Member Authentication', 'sewn-ws'),
                    'type' => 'checkbox',
                    'description' => __('Enable member authentication features', 'sewn-ws'),
                    'section' => 'features',
                    'sanitize' => 'rest_sanitize_boolean'
                ],
                [
                    'name' => 'sewn_ws_data_distribution',
                    'label' => __('Data Distribution', 'sewn-ws'),
                    'type' => 'checkbox',
                    'description' => __('Enable network-wide data distribution', 'sewn-ws'),
                    'section' => 'features',
                    'sanitize' => 'rest_sanitize_boolean'
                ],
                [
                    'name' => 'sewn_ws_sync_interval',
                    'label' => __('Sync Interval', 'sewn-ws'),
                    'type' => 'select',
                    'description' => __('How often to sync network data', 'sewn-ws'),
                    'section' => 'performance',
                    'options' => [
                        '60' => __('Every minute', 'sewn-ws'),
                        '300' => __('Every 5 minutes', 'sewn-ws'),
                        '900' => __('Every 15 minutes', 'sewn-ws'),
                        '3600' => __('Every hour', 'sewn-ws')
                    ],
                    'sanitize' => 'absint'
                ],
                [
                    'name' => 'sewn_ws_cache_ttl',
                    'label' => __('Cache Duration', 'sewn-ws'),
                    'type' => 'select',
                    'description' => __('How long to cache network data', 'sewn-ws'),
                    'section' => 'performance',
                    'options' => [
                        '300' => __('5 minutes', 'sewn-ws'),
                        '900' => __('15 minutes', 'sewn-ws'),
                        '3600' => __('1 hour', 'sewn-ws'),
                        '86400' => __('24 hours', 'sewn-ws')
                    ],
                    'sanitize' => 'absint'
                ]
            ],
            'sections' => [
                [
                    'id' => 'protocol_settings',
                    'title' => __('Protocol Configuration', 'sewn-ws'),
                    'callback' => [$this, 'render_protocol_section']
                ],
                [
                    'id' => 'features',
                    'title' => __('Feature Settings', 'sewn-ws'),
                    'callback' => [$this, 'render_features_section']
                ],
                [
                    'id' => 'performance',
                    'title' => __('Performance Settings', 'sewn-ws'),
                    'callback' => [$this, 'render_performance_section']
                ]
            ]
        ];
    }

    public function render_protocol_section() {
        echo '<p>' . esc_html__('Configure core network protocol settings and authentication.', 'sewn-ws') . '</p>';
    }

    public function render_features_section() {
        echo '<p>' . esc_html__('Enable or disable specific network protocol features.', 'sewn-ws') . '</p>';
    }

    public function render_performance_section() {
        echo '<p>' . esc_html__('Configure performance settings like caching and sync intervals.', 'sewn-ws') . '</p>';
    }

    public function init() {
        // Load protocol class from the same namespace
        require_once dirname(__FILE__) . '/class-startempire-protocol.php';
        
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

    public function get_required_capabilities() {
        return ['manage_network_websockets'];
    }
}
