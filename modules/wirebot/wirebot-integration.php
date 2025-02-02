<?php
namespace SEWN\WebSockets\Modules\Wirebot;

use SEWN\WebSockets\Modules\Module_Base;
use SEWN\WebSockets\AI\Wirebot_Handler;

class Wirebot_Module extends Module_Base {
    private $bot_handler;

    public function metadata(): array {
        return [
            'name' => 'WireBot Integration',
            'slug' => 'wirebot',
            'description' => 'AI-powered network assistant integration',
            'version' => '1.1.0',
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
            'menu_title' => 'WireBot Settings',
            'capability' => 'manage_options',
            'settings' => [
                [
                    'name' => 'sewn_ws_wirebot_model',
                    'label' => 'AI Model',
                    'type' => 'select',
                    'options' => [
                        'gpt-4' => 'GPT-4',
                        'claude-3' => 'Claude 3',
                        'local' => 'Local Model'
                    ],
                    'sanitize' => 'sanitize_text_field',
                    'section' => 'model_config'
                ],
                [
                    'name' => 'sewn_ws_wirebot_safety',
                    'label' => 'Safety Level',
                    'type' => 'select',
                    'options' => [
                        'high' => 'High (Strict filtering)',
                        'medium' => 'Medium (Balanced)',
                        'low' => 'Low (Minimal filtering)'
                    ],
                    'sanitize' => 'sanitize_text_field',
                    'section' => 'safety_config'
                ]
            ],
            'sections' => [
                [
                    'id' => 'model_config',
                    'title' => 'Model Configuration',
                    'callback' => [$this, 'render_model_section']
                ],
                [
                    'id' => 'safety_config',
                    'title' => 'Safety Settings',
                    'callback' => [$this, 'render_safety_section']
                ]
            ]
        ];
    }

    public function init() {
        $this->check_dependencies();
        $this->initialize_bot();
        $this->register_hooks();
    }

    private function initialize_bot() {
        $this->bot_handler = new Wirebot_Handler(
            get_option('sewn_ws_wirebot_model', 'claude-3'),
            get_option('sewn_ws_wirebot_safety', 'medium')
        );
    }

    private function register_hooks() {
        add_filter('sewn_ws_message_handlers', [$this, 'register_handlers']);
        add_action('sewn_ws_client_message', [$this, 'handle_client_message']);
        add_filter('sewn_ws_ai_response', [$this, 'filter_responses']);
    }

    public function register_handlers($handlers) {
        $handlers['wirebot'] = [$this->bot_handler, 'process_message'];
        return $handlers;
    }

    public function render_model_section() {
        echo '<p>Select and configure the AI model for WireBot responses</p>';
    }

    public function render_safety_section() {
        echo '<p>Adjust content safety and filtering levels for AI responses</p>';
    }

    public function activate() {
        if (!get_option('sewn_ws_wirebot_safety')) {
            update_option('sewn_ws_wirebot_safety', 'medium');
        }
    }
}
