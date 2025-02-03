<?php

namespace SEWN\WebSockets;

class Admin_UI {
    private static $instance = null;
    private $registry;
    private $connections = [];

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
        
        add_action('admin_menu', [$this, 'add_menu_items']);
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

    public function add_menu_items() {
        // Main menu
        add_menu_page(
            __('WebSocket Server', 'sewn-ws'),
            __('WebSocket Server', 'sewn-ws'),
            'manage_options',
            'sewn-ws-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-networking'
        );

        // Dashboard submenu
        add_submenu_page(
            'sewn-ws-dashboard',
            __('Dashboard', 'sewn-ws'),
            __('Dashboard', 'sewn-ws'),
            'manage_options',
            'sewn-ws-dashboard',
            [$this, 'render_dashboard']
        );

        // Settings submenu
        add_submenu_page(
            'sewn-ws-dashboard',
            __('Settings', 'sewn-ws'),
            __('Settings', 'sewn-ws'),
            'manage_options',
            'sewn-ws-settings',
            [$this, 'render_settings']
        );

        // Modules submenu
        add_submenu_page(
            'sewn-ws-dashboard',
            __('Modules', 'sewn-ws'),
            __('Modules', 'sewn-ws'),
            'manage_options',
            'sewn-ws-modules',
            [$this, 'render_modules_page']
        );

        // Fixed hidden submenu registration
        add_submenu_page(
            'sewn-ws-dashboard', // Parent slug instead of null
            __('Module Settings', 'sewn-ws'),
            '', // Empty menu title
            'manage_options',
            'sewn-ws-module-settings',
            [$this, 'render_module_settings_page']
        );
    }

    public function render_dashboard() {
        $node_check = new Node_Check();
        $node_status = $node_check->check_server_status();
        
        $data = [
            'node_status' => $node_status ?: ['version' => 'Unknown', 'running' => false],
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
        try {
            // Add WordPress AJAX action verification
            check_ajax_referer('sewn_ws_nonce', 'nonce');
            
            // Validate required parameters
            if(empty($_POST['command'])) {
                throw new \Exception('Missing command parameter');
            }

            $command = sanitize_key($_POST['command']);
            $server = new Server_Manager();
            
            // Log attempt
            error_log("Server control command received: " . $command);
            error_log("User agent: " . $_SERVER['HTTP_USER_AGENT']);
            error_log("IP address: " . $_SERVER['REMOTE_ADDR']);

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
                    throw new Exception("Invalid command: $command");
            }
            
            wp_send_json_success([
                'status' => $result,
                'debug' => [
                    'node_version' => exec('node -v'),
                    'npm_version' => exec('npm -v'),
                    'system_user' => exec('whoami'),
                    'node_dir' => SEWN_WS_NODE_DIR,
                    'dir_exists' => file_exists(SEWN_WS_NODE_DIR),
                    'dir_perms' => substr(sprintf('%o', fileperms(SEWN_WS_NODE_DIR)), -4)
                ]
            ]);
            
        } catch (Exception $e) {
            $errorData = [
                'message' => $e->getMessage(),
                'debug' => [
                    'php_version' => phpversion(),
                    'wp_version' => get_bloginfo('version'),
                    'server_software' => $_SERVER['SERVER_SOFTWARE'],
                    'node_path' => defined('SEWN_WS_NODE_DIR') ? SEWN_WS_NODE_DIR : 'undefined',
                    'current_user' => get_current_user_id(),
                    'user_caps' => array_keys(wp_get_current_user()->allcaps)
                ]
            ];
            error_log("AJAX Error: " . print_r($errorData, true));
            wp_send_json_error($errorData);
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'sewn-ws-admin',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            ['jquery'],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/admin.js'),
            true
        );

        wp_localize_script('sewn-ws-admin', 'sewn_ws_admin', [
            'nonce' => wp_create_nonce('sewn_ws_control'),
            'ajax_url' => admin_url('admin-ajax.php')
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