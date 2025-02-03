<?php

namespace SEWN\WebSockets;

class Admin_UI {
    private static $instance = null;
    private $registry;
    private $connections = [];
    private $server_process = null;
    private $status_lock = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->registry = Module_Registry::get_instance();
        $this->registry->discover_modules();
        $this->registry->init_modules();
        
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add AJAX handlers
        add_action('wp_ajax_sewn_ws_control', [$this, 'handle_server_control']);
        add_action('wp_ajax_sewn_ws_get_stats', [$this, 'handle_ajax']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function register_settings() {
        register_setting('sewn_ws_settings', 'sewn_ws_port', [
            'type' => 'integer',
            'description' => 'WebSocket server port',
            'default' => 8080,
            'sanitize_callback' => 'absint'
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
        echo '<button class="button-primary" id="start-server">Start</button>';
        echo '<button class="button-secondary" id="stop-server">Stop</button>';
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
        try {
            check_ajax_referer('sewn_ws_control', 'nonce');
            
            $command = $_POST['command'] ?? '';
            $controller = new Server_Controller();
            
            if(!method_exists($controller, $command)) {
                throw new \Exception("Invalid server command: $command");
            }
            
            $result = $controller->$command();
            wp_send_json_success($result);
            
        } catch(\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'debug' => $this->get_debug_info()
            ]);
        }
    }

    public function handle_ajax() {
        check_ajax_referer('sewn_ws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $action = $_REQUEST['action'] ?? '';
        
        if ($action === 'sewn_ws_get_status') {
            $status = $this->get_server_status(); // Implement your status check
            wp_send_json_success(['status' => $status]);
        }
        
        if ($action === 'sewn_ws_get_stats') {
            $stats = [
                'connections' => count($this->connections),
                'memory' => memory_get_usage(true),
                'errors' => $this->error_count
            ];
            wp_send_json_success($stats);
        }
        
        $command = sanitize_text_field($_POST['command']);
        $response = [
            'status' => 'error',
            'message' => 'Unknown command'
        ];

        if ($this->status_lock) {
            wp_send_json_error('Another operation is in progress', 423);
        }

        $this->status_lock = true;
        
        try {
            switch ($command) {
                case 'start':
                    if ($this->is_server_running()) {
                        throw new Exception('Server already running');
                    }
                    
                    $this->server_process = new Server_Process();
                    $started = $this->server_process->start();
                    
                    if (!$started) {
                        throw new Exception('Failed to start server: ' 
                            . $this->server_process->get_last_error());
                    }
                    
                    $response = [
                        'status' => 'running',
                        'message' => 'Server started successfully'
                    ];
                    break;
                    
                case 'stop':
                    if ($this->server_process && $this->server_process->is_running()) {
                        $this->server_process->stop();
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

    public function enqueue_scripts() {
        $plugin_path = plugin_dir_path(dirname(__FILE__)) . 'assets/js/admin.js';
        
        wp_enqueue_script(
            'sewn-ws-admin',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            [],
            filemtime($plugin_path),
            true
        );
        
        // Add module type attribute
        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'sewn-ws-admin') {
                return str_replace(' src', ' type="module" src', $tag);
            }
            return $tag;
        }, 10, 2);
    
        // Localize script variables
        wp_localize_script('sewn-ws-admin', 'sewn_ws_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sewn_ws_admin_nonce'),
            'port' => get_option('sewn_ws_port', 3000)
        ]);
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
    
    private function cleanup_connections() {
        foreach ($this->connections as $conn) {
            if ($conn->isConnected()) {
                $conn->close();
            }
        }
        $this->connections = [];
    }
}