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

 use SEWN\WebSockets\Module_Base;
 use SEWN\WebSockets\Module_Registry;
 use SEWN\WebSockets\Node_Check;
 use SEWN\WebSockets\Process_Manager;
 use SEWN\WebSockets\Server_Controller;
 use SEWN\WebSockets\Server_Process; 

class Admin_UI {
    private static $instance = null;
    private $registry;
    private $connections = [];
    private $server_process = null;
    private $status_lock = false;
    private $server_controller;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        $this->registry = Module_Registry::get_instance();
        $this->server_controller = new Server_Controller();
        $this->registry->discover_modules();
        $this->registry->init_modules();
        
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add AJAX handlers
        add_action('wp_ajax_sewn_ws_control', [$this, 'handle_server_control']);
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
            
            if(!method_exists($this->server_controller, $command)) {
                throw new \Exception("Invalid server command: $command");
            }
            
            $result = $this->server_controller->$command();
            wp_send_json_success($result);
            
        } catch(\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'debug' => $this->get_debug_info()
            ]);
        }
    }

    public function handle_ajax() {
        check_ajax_referer(SEWN_WS_NONCE_ACTION, 'nonce');
        
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

    public function enqueue_assets($hook) {
        if (strpos($hook, SEWN_WS_ADMIN_MENU_SLUG) === false) {
            return;
        }

        wp_enqueue_style(
            'sewn-ws-admin',
            plugins_url('css/admin.css', dirname(__FILE__)),
            array(),
            SEWN_WS_VERSION
        );
        
        wp_enqueue_script(
            'sewn-ws-admin',
            plugins_url('js/admin.js', dirname(__FILE__)),
            array('jquery'),
            SEWN_WS_VERSION,
            true
        );
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

    public function enqueue_admin_assets() {
        // Explicit script registration with module type
        wp_register_script(
            'sewn-ws-admin-js',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            ['jquery'],
            SEWN_WS_VERSION,
            true
        );
        
        // Add module type attribute
        wp_script_add_data('sewn-ws-admin-js', 'type', 'module');

        wp_enqueue_script('sewn-ws-admin-js');
    }

    public function render_server_status() {
        $status = $this->get_server_status();
        $status_class = $status === SEWN_WS_SERVER_STATUS_RUNNING ? 'success' : 'error';
        
        echo '<div class="sewn-ws-status ' . esc_attr($status_class) . '">';
        echo '<span class="status-dot"></span>';
        echo '<span class="status-text">' . esc_html(ucfirst($status)) . '</span>';
        echo '</div>';
    }
}