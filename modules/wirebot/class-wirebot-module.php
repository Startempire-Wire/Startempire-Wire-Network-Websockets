<?php
/**
 * Location: modules/wirebot/class-wirebot-module.php
 * Dependencies: Module_Base, Wirebot_Protocol
 * Variables/Classes: Wirebot_Module, $bot_handler
 * Purpose: Manages AI-powered WireBot integration with WebSocket communications and message processing. Handles natural language interactions, response filtering, and safety controls for network members.
 */

namespace SEWN\WebSockets\Modules\Wirebot;

use SEWN\WebSockets\Module_Base;
use SEWN\WebSockets\Protocol_Base;

class Wirebot_Module extends Module_Base {
    private $protocol;
    private $bot_handler;

    public function get_module_slug(): string {
        return 'wirebot';
    }

    public function init() {
        if (!$this->check_dependencies()) {
            return;
        }
        
        // Load protocol class from the same namespace
        require_once dirname(__FILE__) . '/class-wirebot-protocol.php';
        
        // Load handler class
        require_once dirname(__FILE__) . '/class-wirebot-handler.php';
        
        $this->protocol = new Wirebot_Protocol();
        $this->protocol->register();
        
        add_action('sewn_ws_module_ready', [$this, 'register_hooks']);
        $this->initialize_bot();
    }

    public function check_dependencies() {
        $protocol_file = dirname(__FILE__) . '/class-wirebot-protocol.php';
        $handler_file = dirname(__FILE__) . '/class-wirebot-handler.php';
        
        if (!file_exists($protocol_file)) {
            return [
                'error' => __('Wirebot Protocol file not found', 'sewn-ws')
            ];
        }
        
        if (!file_exists($handler_file)) {
            return [
                'error' => __('Wirebot Handler file not found', 'sewn-ws')
            ];
        }
        
        return true;
    }

    public function metadata(): array {
        return [
            'module_slug' => 'wirebot',
            'name' => __('Wirebot Integration', 'sewn-ws'),
            'version' => '1.1.0',
            'description' => __('AI-powered network assistant integration', 'sewn-ws'),
            'author' => 'Startempire Team',
            'dependencies' => ['screenshots']
        ];
    }

    public function requires(): array {
        return [
            [
                'name' => 'Screenshots Plugin',
                'class' => 'SEWN\Screenshots\Core\Image_Processor',
                'version' => '2.3.0'
            ]
        ];
    }

    public function admin_ui(): array {
        return [
            'menu_title' => __('WireBot Settings', 'sewn-ws'),
            'capability' => 'manage_options',
            'settings' => [
                [
                    'name' => 'sewn_ws_wirebot_model',
                    'label' => __('AI Model', 'sewn-ws'),
                    'type' => 'select',
                    'description' => __('Select the AI model to use for responses', 'sewn-ws'),
                    'section' => 'model_config',
                    'options' => [
                        'gpt-4' => 'GPT-4',
                        'claude-3' => 'Claude 3',
                        'local' => 'Local Model'
                    ],
                    'sanitize' => 'sanitize_text_field'
                ],
                [
                    'name' => 'sewn_ws_wirebot_safety',
                    'label' => __('Safety Level', 'sewn-ws'),
                    'type' => 'select',
                    'description' => __('Set content filtering level for AI responses', 'sewn-ws'),
                    'section' => 'safety_config',
                    'options' => [
                        'high' => 'High (Strict filtering)',
                        'medium' => 'Medium (Balanced)',
                        'low' => 'Low (Minimal filtering)'
                    ],
                    'sanitize' => 'sanitize_text_field'
                ],
                [
                    'name' => 'sewn_ws_wirebot_cache',
                    'label' => __('Response Caching', 'sewn-ws'),
                    'type' => 'checkbox',
                    'description' => __('Enable caching of AI responses', 'sewn-ws'),
                    'section' => 'performance',
                    'sanitize' => 'rest_sanitize_boolean'
                ],
                [
                    'name' => 'sewn_ws_wirebot_cache_ttl',
                    'label' => __('Cache Duration', 'sewn-ws'),
                    'type' => 'select',
                    'description' => __('How long to cache responses', 'sewn-ws'),
                    'section' => 'performance',
                    'options' => [
                        '3600' => '1 hour',
                        '86400' => '24 hours',
                        '604800' => '1 week'
                    ],
                    'sanitize' => 'absint'
                ]
            ],
            'sections' => [
                [
                    'id' => 'model_config',
                    'title' => __('Model Configuration', 'sewn-ws'),
                    'callback' => [$this, 'render_model_section']
                ],
                [
                    'id' => 'safety_config',
                    'title' => __('Safety Settings', 'sewn-ws'),
                    'callback' => [$this, 'render_safety_section']
                ],
                [
                    'id' => 'performance',
                    'title' => __('Performance Settings', 'sewn-ws'),
                    'callback' => [$this, 'render_performance_section']
                ]
            ]
        ];
    }

    private function initialize_bot() {
        $this->bot_handler = new Wirebot_Handler(
            get_option('sewn_ws_wirebot_model', 'claude-3'),
            get_option('sewn_ws_wirebot_safety', 'medium')
        );
    }

    public function register_hooks() {
        add_filter('sewn_ws_message_handlers', [$this, 'register_handlers']);
        add_action('sewn_ws_client_message', [$this, 'handle_client_message']);
        add_filter('sewn_ws_ai_response', [$this, 'filter_responses']);
    }

    public function register_handlers($handlers) {
        $handlers['wirebot'] = [$this->bot_handler, 'process_message'];
        return $handlers;
    }

    public function render_model_section() {
        echo '<p>' . esc_html__('Select and configure the AI model for WireBot responses.', 'sewn-ws') . '</p>';
    }

    public function render_safety_section() {
        echo '<p>' . esc_html__('Configure content safety and filtering settings for AI responses.', 'sewn-ws') . '</p>';
    }

    public function render_performance_section() {
        echo '<p>' . esc_html__('Adjust performance and caching settings for optimal response times.', 'sewn-ws') . '</p>';
    }

    public function activate() {
        if (!get_option('sewn_ws_wirebot_safety')) {
            update_option('sewn_ws_wirebot_safety', 'medium');
        }
    }
}
