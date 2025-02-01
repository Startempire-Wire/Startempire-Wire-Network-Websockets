<?php

namespace SEWN\WebSockets;

class Admin_UI {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_items']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add AJAX handlers
        add_action('wp_ajax_sewn_ws_server_control', [$this, 'handle_ajax']);
        add_action('wp_ajax_sewn_ws_get_stats', [$this, 'handle_ajax']);
    }

    public function register_settings() {
        // Add Node.js path setting
        register_setting('sewn_ws_settings', 'sewn_ws_node_path', [
            'type' => 'string',
            'description' => 'Custom path to Node.js binary',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);

        add_settings_field(
            'sewn_ws_node_path',
            'Node.js Binary Path',
            [$this, 'render_node_path_field'],
            'sewn-ws-settings', // Your settings page slug
            'sewn_ws_server_settings' // Your settings section
        );
    }

    public function render_node_path_field() {
        $node_path = get_option('sewn_ws_node_path', '');
        ?>
        <input type="text" 
               id="sewn_ws_node_path" 
               name="sewn_ws_node_path" 
               value="<?php echo esc_attr($node_path); ?>" 
               class="regular-text">
        <p class="description">
            Enter the full path to your Node.js binary if auto-detection fails. 
            Leave empty to use auto-detection.
        </p>
        <?php
    }

    public function add_menu_items() {
        error_log('[SEWN] Attempting to register admin menu');
        
        if (!class_exists('SEWN\WebSockets\Node_Check') || !Node_Check::meets_requirements()) {
            error_log('[SEWN] Menu hidden - Node.js requirements not met');
            return;
        }

        error_log('[SEWN] Registering main menu item');
        add_menu_page(
            __('WebSocket Server', 'sewn-ws'),
            __('WebSocket', 'sewn-ws'),
            'manage_options',
            'sewn-ws',
            [$this, 'render_dashboard'],
            'dashicons-networking',
            80
        );

        add_submenu_page('sewn-ws', 
            __('Dashboard', 'sewn-ws'),
            __('Dashboard', 'sewn-ws'),
            'manage_options',
            'sewn-ws',
            [$this, 'render_dashboard']
        );

        add_submenu_page('sewn-ws',
            __('Settings', 'sewn-ws'),
            __('Settings', 'sewn-ws'),
            'manage_options',
            'sewn-ws-settings',
            [$this, 'render_settings']
        );
    }

    public function render_dashboard() {
        $node_check = new Node_Check();
        $node_status = $node_check->check_server_status();
        
        // Pass variables explicitly to view
        $data = [
            'node_status' => $node_status ?: ['version' => 'Unknown', 'running' => false],
            'connections' => Process_Manager::get_active_connections(),
            'error_logs' => Error_Handler::get_recent_errors()
        ];
        
        extract($data);
        include plugin_dir_path(__FILE__) . 'views/dashboard.php';

        add_meta_box('sewn-ws-controls', 'Server Controls', [$this, 'render_controls']);
        add_meta_box('sewn-ws-stats', 'Real-time Stats', [$this, 'render_stats']);
    }

    public function render_controls() {
        echo '<button class="button-primary" id="start-server">Start</button>';
        echo '<button class="button-secondary" id="stop-server">Stop</button>';
    }

    public function render_settings() {
        // Implementation of render_settings method
        include plugin_dir_path(__FILE__) . 'views/settings.php';
    }

    public function handle_ajax() {
        if ($_POST['action'] === 'sewn_ws_server_control') {
            check_ajax_referer('sewn_ws_control', 'nonce');
            
            $command = $_POST['command'];
            $server = new Server_Manager();
            
            if ($command === 'start') {
                $server->start();
            } elseif ($command === 'stop') {
                $server->stop();
            }
            
            wp_send_json_success();
        }
        
        if ($_GET['action'] === 'sewn_ws_get_stats') {
            $stats = get_transient('sewn_ws_stats');
            wp_send_json_success([
                'connections' => $stats['connections'] ?? 0,
                'bandwidth' => size_format($stats['bandwidth'] ?? 0, 2)
            ]);
        }
    }

    public function handle_ajax_server_control() {
        check_ajax_referer('sewn_ws_control', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        $command = sanitize_text_field($_POST['command']);
        $server = new Server_Manager();
        
        try {
            switch ($command) {
                case 'start':
                    $result = $server->start();
                    break;
                case 'stop':
                    $result = $server->stop();
                    break;
                case 'restart':
                    $result = $server->restart();
                    break;
                default:
                    throw new Exception('Invalid command');
            }
            
            wp_send_json_success(['status' => $result]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}