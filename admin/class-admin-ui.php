<?php

/**
 * LOCATION: admin/class-admin-ui.php
 * DEPENDENCIES: Module_Registry, Process_Manager, Server_Controller
 * VARIABLES: SEWN_WS_ADMIN_MENU_SLUG, SEWN_WS_TEXT_DOMAIN
 * CLASSES: Admin_UI (manages admin interface)
 * 
 * Provides the primary administration interface for WebSocket server management. Handles server lifecycle operations,
 * real-time status monitoring, and integration with network authentication systems. Designed to support the distributed
 * architecture described in Startempire Wire Network's core documentation.
 */

namespace SEWN\WebSockets\Admin;

use SEWN\WebSockets\WebSocketServer;
use SEWN\WebSockets\Module_Base;
use SEWN\WebSockets\Module_Registry;
use SEWN\WebSockets\Node_Check;
use SEWN\WebSockets\Process_Manager;
use SEWN\WebSockets\Server_Controller;
use SEWN\WebSockets\Server_Process;
use Exception;

class Admin_UI {
    private static $instance = null;
    private $registry;
    private $connections = [];
    private $server_process = null;
    private $status_lock = false;
    private $server_controller;
    private $websocket_server;
    private $error_count = 0;
    private $rate_limiter;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        // Initialize core dependencies
        if (class_exists('\SEWN\WebSockets\Module_Registry')) {
            $this->registry = Module_Registry::get_instance();
            $this->registry->discover_modules();
            $this->registry->init_modules();
        } else {
            error_log('[SEWN] Module_Registry not available');
        }
        
        // Initialize Server Controller
        if (class_exists('\SEWN\WebSockets\Server_Controller')) {
            try {
                $this->server_controller = Server_Controller::get_instance();
                if (!$this->server_controller) {
                    throw new \Exception('Failed to initialize Server Controller');
                }
            } catch (\Exception $e) {
                error_log('[SEWN] Server Controller initialization failed: ' . $e->getMessage());
            }
        } else {
            error_log('[SEWN] Server_Controller class not found');
        }
        
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add AJAX handlers
        add_action('wp_ajax_sewn_ws_server_control', [$this, 'handle_server_control']);
        add_action('wp_ajax_sewn_ws_get_stats', [$this, 'handle_ajax']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_settings() {
        register_setting('sewn_ws_settings', 'sewn_ws_port', [
            'type' => 'integer',
            'description' => 'WebSocket server port',
            'default' => 8080,
            'sanitize_callback' => 'absint'
        ]);

        register_setting('sewn_ws_settings', 'sewn_ws_dev_mode', [
            'type' => 'boolean',
            'description' => 'Enable development mode',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);

        add_settings_section(
            'server_config',
            __('Server Configuration', 'sewn-ws'),
            null,
            'sewn-ws-settings'
        );

        add_settings_field(
            'sewn_ws_port',
            __('WebSocket Port', 'sewn-ws'),
            function() {
                $port = get_option('sewn_ws_port', 8080);
                echo "<input type='number' name='sewn_ws_port' value='$port' min='1024' max='65535'>";
            },
            'sewn-ws-settings',
            'server_config'
        );

        add_settings_field(
            'sewn_ws_dev_mode',
            __('Development Mode', 'sewn-ws'),
            function() {
                $dev_mode = get_option('sewn_ws_dev_mode', false);
                echo '<label>';
                echo "<input type='checkbox' name='sewn_ws_dev_mode' value='1' " . checked($dev_mode, true, false) . ">";
                echo __('Enable development mode (allows HTTP WebSocket connections)', 'sewn-ws');
                echo '</label>';
                if ($dev_mode) {
                    echo '<p class="description" style="color: #d63638;">';
                    echo __('Warning: Development mode is enabled. This should only be used in local development environments.', 'sewn-ws');
                    echo '</p>';
                }
            },
            'sewn-ws-settings',
            'server_config'
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

    public function render_dashboard() {
        $node_check = new Node_Check();
        $node_status = $node_check->check_server_status();
        
        // Add these MVP-critical metrics
        $health_metrics = [
            'message_queue' => Process_Manager::get_message_queue_depth(),
            'connection_failure_rate' => Process_Manager::get_failure_rates(),
            'bandwidth_usage' => Process_Manager::get_bandwidth_usage()
        ];
        
        $data = [
            'node_status' => $node_status,
            'health_metrics' => $health_metrics, // New metrics
            'connections' => Process_Manager::get_active_connections(),
            'message_rates' => Process_Manager::get_message_throughput()
        ];
        
        include plugin_dir_path(__DIR__) . 'admin/views/dashboard.php';

        add_meta_box('sewn-ws-controls', 'Server Controls', [$this, 'render_controls']);
        add_meta_box('sewn-ws-stats', 'Real-time Stats', [$this, 'render_stats']);
    }

    public function render_controls() {
        $status = $this->server_controller ? $this->server_controller->get_server_status() : ['running' => false];
        $status_text = $status['running'] ? 'running' : 'stopped';
        
        echo '<div class="sewn-ws-controls-wrapper">';
        echo '<div class="sewn-ws-status ' . esc_attr($status_text) . '">';
        echo '<span class="status-dot"></span>';
        echo '<span class="status-text">' . esc_html(ucfirst($status_text)) . '</span>';
        echo '</div>';
        
        echo '<div class="sewn-ws-buttons">';
        echo '<button class="button-primary" data-action="start">Start Server</button>';
        echo '<button class="button-secondary" data-action="stop">Stop Server</button>';
        echo '<button class="button-secondary" data-action="restart">Restart Server</button>';
        echo '</div>';
        echo '</div>';
    }

    public function render_settings() {
        // Implementation of render_settings method
        include plugin_dir_path(__FILE__) . 'views/settings.php';
    }

    public function render_modules_page() {
        $modules = $this->registry->get_modules();
        $registry = $this->registry; // Pass registry to view
        include plugin_dir_path(__DIR__) . 'admin/views/modules-list.php';
    }

    public function render_module_settings_page() {
        $module_slug = isset($_GET['module']) ? sanitize_key($_GET['module']) : '';
        $module = $this->registry->get_module($module_slug);
        
        if (!$module || !$module instanceof Module_Base) {
            wp_die(__('Invalid module or module not loaded', 'sewn-ws'));
        }
        
        include plugin_dir_path(__DIR__) . 'admin/views/module-settings.php';
    }

    public function handle_server_control() {
        static $is_processing = false;
        
        try {
            error_log('WebSocket Server Control: Request received');
            error_log('POST data: ' . print_r($_POST, true));
            
            if ($is_processing) {
                error_log('WebSocket Server Control: Request already in progress');
                wp_send_json_error([
                    'message' => __('Another request is in progress', 'sewn-ws'),
                    'code' => 'LOCKED'
                ], 423);
                return;
            }
            
            $is_processing = true;
            
            check_ajax_referer(SEWN_WS_NONCE_ACTION, 'nonce');
            
            if (!current_user_can('manage_options')) {
                error_log('WebSocket Server Control: Insufficient permissions');
                wp_send_json_error([
                    'message' => __('Insufficient permissions to manage WebSocket server', 'sewn-ws'),
                    'code' => 'FORBIDDEN'
                ], 403);
                return;
            }
            
            if (!$this->server_controller) {
                error_log('WebSocket Server Control: Server controller not initialized');
                wp_send_json_error([
                    'message' => __('Server controller not initialized', 'sewn-ws'),
                    'code' => 'SERVER_ERROR'
                ], 500);
                return;
            }
            
            $command = $_POST['command'] ?? '';
            
            if(!method_exists($this->server_controller, $command)) {
                error_log('WebSocket Server Control: Invalid command - ' . $command);
                throw new \Exception("Invalid server command: $command");
            }
            
            error_log('WebSocket Server Control: Executing command - ' . $command);
            $result = $this->server_controller->$command();
            error_log('WebSocket Server Control: Command result - ' . print_r($result, true));
            
            // Get fresh server status
            $status = $this->server_controller->get_server_status();
            
            wp_send_json_success([
                'status' => $status['running'] ? 'running' : 'stopped',
                'result' => $result,
                'message' => sprintf(__('Server %s command executed successfully', 'sewn-ws'), $command)
            ]);
            
        } catch(\Exception $e) {
            error_log('WebSocket Server Control Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'ERROR',
                'debug' => [
                    'error_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ]);
        } finally {
            $is_processing = false;
        }
    }

    public function handle_ajax() {
        check_ajax_referer(SEWN_WS_NONCE_ACTION, 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $command = sanitize_text_field($_POST['command'] ?? '');
        
        if ($this->status_lock) {
            wp_send_json_error([
                'message' => __('Another operation is in progress', 'sewn-ws'),
                'code' => 'LOCKED'
            ], 423);
            return;
        }

        $this->status_lock = true;
        
        try {
            switch ($command) {
                case 'start':
                    if ($this->is_server_running()) {
                        throw new Exception('Server already running');
                    }
                    
                    // Create WebSocketServer only when starting
                    $this->websocket_server = new WebSocketServer();
                    $started = $this->websocket_server->run();
                    
                    if (!$started) {
                        throw new Exception('Failed to start server');
                    }
                    
                    $response = [
                        'status' => 'running',
                        'message' => 'Server started successfully'
                    ];
                    break;
                    
                case 'stop':
                    if ($this->websocket_server) {
                        $this->websocket_server->stop();
                        $this->websocket_server = null;
                        $this->cleanup_connections();
                    }
                    $response = [
                        'status' => 'stopped', 
                        'message' => 'Server stopped'
                    ];
                    break;
                    
                default:
                    throw new Exception('Invalid command');
            }
            
            $this->status_lock = false;
            wp_send_json_success($response);
            
        } catch (Exception $e) {
            $this->status_lock = false;
            $this->cleanup_connections();
            wp_send_json_error([
                'message' => $e->getMessage(),
                'logs' => $this->get_recent_server_logs()
            ], 500);
        }
    }

    private function is_server_running() {
        // Implement actual server status check
        return file_exists('/tmp/websocket.pid');
    }
    
    private function get_recent_server_logs() {
        return file_get_contents('/var/log/websocket.log');
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, SEWN_WS_ADMIN_MENU_SLUG) === false) {
            return;
        }

        wp_enqueue_style(
            'sewn-ws-admin',
            plugins_url('assets/css/admin.css', dirname(__FILE__)),
            array(),
            SEWN_WS_VERSION
        );
        
        // Add Socket.IO client library first
        wp_register_script(
            'socket-io',
            'https://cdn.socket.io/4.7.4/socket.io.min.js',
            [],
            '4.7.4',
            true
        );
        
        // Get correct path to admin.js
        $js_path = plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js';
        
        // Register admin script with Socket.IO dependency
        wp_register_script(
            'sewn-ws-admin',
            $js_path,
            ['jquery', 'socket-io'], // Add socket-io as dependency
            SEWN_WS_VERSION,
            true
        );
        
        // Get development mode status
        $dev_mode = get_option('sewn_ws_dev_mode', false);
        $is_local = (
            strpos($_SERVER['HTTP_HOST'], '.local') !== false || 
            strpos($_SERVER['HTTP_HOST'], 'localhost') !== false
        );
        
        // Localize script data with constants and environment info
        wp_localize_script('sewn-ws-admin', 'sewn_ws_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(SEWN_WS_NONCE_ACTION),
            'port' => get_option('sewn_ws_port', 8080),
            'is_local' => $is_local,
            'dev_mode' => $dev_mode,
            'site_protocol' => is_ssl() ? 'https' : 'http',
            'constants' => [
                'STATUS_RUNNING' => SEWN_WS_SERVER_STATUS_RUNNING,
                'STATUS_STOPPED' => SEWN_WS_SERVER_STATUS_STOPPED,
                'STATUS_ERROR' => SEWN_WS_SERVER_STATUS_ERROR
            ],
            'i18n' => [
                'confirm_restart' => __('Are you sure you want to restart the WebSocket server?', 'sewn-ws'),
                'server_starting' => __('Server starting...', 'sewn-ws')
            ]
        ]);
        
        // Enqueue both scripts
        wp_enqueue_script('socket-io');
        wp_enqueue_script('sewn-ws-admin');
    }

    public function handle_server_action() {
        if (!current_user_can('manage_network')) {
            wp_send_json_error('Insufficient privileges');
        }
        
        if (!$this->rate_limiter->check('server_controls', 3, 'per_minute')) {
            wp_send_json_error('Too many requests');
        }
    }

    public function init_websocket_connections() {
        // Add connection cleanup at start
        $this->cleanup_connections();
        // Rest of existing code
    }
    
    public function cleanup_connections() {
        foreach ($this->connections as $connection) {
            $connection->close();
        }
        $this->connections = [];
    }

    public function render_server_status() {
        $status = $this->get_server_status();
        $status_class = $status === SEWN_WS_SERVER_STATUS_RUNNING ? 'success' : 'error';
        
        echo '<div class="sewn-ws-status ' . esc_attr($status_class) . '">';
        echo '<span class="status-dot"></span>';
        echo '<span class="status-text">' . esc_html(ucfirst($status)) . '</span>';
        echo '</div>';
    }

    public function init_admin_scripts() {
        add_action('admin_enqueue_scripts', function() {
            wp_enqueue_script('sewn-ws-admin-ui', 
                plugins_url('assets/js/admin-ui.js', dirname(__FILE__)),
                ['jquery'],
                filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/admin-ui.js'),
                true
            );
        });
    }

    private function get_debug_info() {
        return [
            'server_status' => $this->server_process ? 'running' : 'stopped',
            'connections' => count($this->connections),
            'memory_usage' => memory_get_usage(true),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        ];
    }

    private function get_server_status() {
        if (!$this->server_process) {
            return 'stopped';
        }
        
        return $this->server_process->is_running() ? 'running' : 'stopped';
    }

    public function render_status() {
        // Get server status using server_process property
        $server_status = 'unknown';
        if ($this->server_process && method_exists($this->server_process, 'is_running')) {
            $server_status = $this->server_process->is_running() ? 'running' : 'stopped';
        }
        
        // Get connection metrics using available data
        $connection_stats = [
            'active_connections' => count($this->connections),
            'error_count' => $this->error_count,
            'memory_usage' => memory_get_usage(true),
            'uptime' => 0  // Default to 0 if no process
        ];
        
        // Calculate uptime if server is running
        if ($server_status === 'running') {
            $pid_file = '/tmp/websocket.pid';
            if (file_exists($pid_file)) {
                $connection_stats['uptime'] = time() - filemtime($pid_file);
            }
        }
        
        // Include the status view template
        include plugin_dir_path(__DIR__) . 'admin/views/status.php';
    }
}