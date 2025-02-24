<?php
/**
 * LOCATION: includes/class-server-controller.php
 * DEPENDENCIES: Process_Manager, Node_Check
 * VARIABLES: SEWN_WS_DEFAULT_PORT
 * CLASSES: Server_Controller (core service handler)
 * 
 * Orchestrates WebSocket server lifecycle operations and protocol handling. Maintains real-time synchronization
 * with Ring Leader plugin for network-wide message routing. Enforces membership-tier based connection limits.
 */

namespace SEWN\WebSockets;

class Server_Controller {
    private static $instance = null;
    private $node_binary = null;
    private $server_script;
    private $pid_file;
    private $constants_file;
    private $server_controller;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_ajax_sewn_ws_server_control', [$this, 'handle_server_control']);
        add_action('wp_ajax_sewn_ws_get_stats', [$this, 'handle_stats_request']);

        $this->server_script = SEWN_WS_NODE_SERVER . 'server.js';
        $this->pid_file = SEWN_WS_NODE_SERVER . 'server.pid';
        $this->constants_file = SEWN_WS_NODE_SERVER . 'wp-constants.json';
        
        // Initialize server controller
        $this->server_controller = $this;
    }

    /**
     * Generates the wp-constants.json file with all necessary WordPress constants
     * for the Node.js server to use.
     *
     * @return bool True if successful, false otherwise
     */
    private function generate_constants_file() {
        try {
            // Create constants array
            $constants = [
                'SEWN_WS_DEFAULT_PORT' => SEWN_WS_DEFAULT_PORT,
                'SEWN_WS_SERVER_CONTROL_PATH' => './tmp',
                'SEWN_WS_SERVER_PID_FILE' => './tmp/server.pid',
                'SEWN_WS_SERVER_LOG_FILE' => './logs/server.log',
                'SEWN_WS_NODE_SERVER' => './',
                'SEWN_WS_ENV_DEBUG_ENABLED' => defined('WP_DEBUG') && WP_DEBUG,
                'SEWN_WS_STATS_UPDATE_INTERVAL' => SEWN_WS_STATS_UPDATE_INTERVAL,
                'SEWN_WS_HISTORY_MAX_POINTS' => SEWN_WS_HISTORY_MAX_POINTS,
                'SEWN_WS_IS_LOCAL' => SEWN_WS_IS_LOCAL,
                'SEWN_WS_ENV_CONTAINER_MODE' => defined('SEWN_WS_ENV_CONTAINER_MODE') ? SEWN_WS_ENV_CONTAINER_MODE : false,
                'SEWN_WS_SSL_ENABLED' => !empty(SEWN_WS_ENV_OVERRIDABLE['SEWN_WS_ENV_SSL_CERT_PATH']) && !empty(SEWN_WS_ENV_OVERRIDABLE['SEWN_WS_ENV_SSL_KEY_PATH']),
                'SEWN_WS_SSL_CERT' => SEWN_WS_ENV_OVERRIDABLE['SEWN_WS_ENV_SSL_CERT_PATH'] ?? '',
                'SEWN_WS_SSL_KEY' => SEWN_WS_ENV_OVERRIDABLE['SEWN_WS_ENV_SSL_KEY_PATH'] ?? ''
            ];

            // Ensure node-server directory exists
            if (!file_exists(dirname($this->constants_file))) {
                if (!wp_mkdir_p(dirname($this->constants_file))) {
                    throw new \Exception('Failed to create node-server directory');
                }
            }

            // Write constants to file
            if (file_put_contents($this->constants_file, json_encode($constants, JSON_PRETTY_PRINT)) === false) {
                throw new \Exception('Failed to write constants file');
            }

            return true;
        } catch (\Exception $e) {
            error_log('WebSocket Server: Failed to generate constants file: ' . $e->getMessage());
            return false;
        }
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
            
            check_ajax_referer(\SEWN_WS_NONCE_ACTION, 'nonce');
            
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
                'details' => $status
            ]);
            
        } catch (\Exception $e) {
            error_log('WebSocket Server Control Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'ERROR'
            ], 500);
        } finally {
            $is_processing = false;
        }
    }

    private function execute_server_command($command) {
        switch ($command) {
            case 'start':
                return $this->start();
            case 'stop':
                return $this->stop();
            case 'restart':
                $this->stop();
                sleep(2); // Wait for server to fully stop
                return $this->restart();
            default:
                throw new \Exception('Invalid command');
        }
    }

    public function start() {
        try {
            if ($this->is_server_running()) {
                throw new \Exception('Server is already running');
            }

            // Generate constants file first
            if (!$this->generate_constants_file()) {
                throw new \Exception('Failed to generate wp-constants.json');
            }

            // Get Node.js binary path
            $this->node_binary = $this->get_node_binary();
            if (!$this->node_binary) {
                throw new \Exception('Node.js binary not found');
            }

            // Start the server
            $command = sprintf(
                '%s %s > %s 2>&1 & echo $! > %s',
                escapeshellcmd($this->node_binary),
                escapeshellarg($this->server_script),
                escapeshellarg(SEWN_WS_SERVER_LOG_FILE),
                escapeshellarg($this->pid_file)
            );

            $output = [];
            $return_var = 0;
            exec($command, $output, $return_var);

            if ($return_var !== 0) {
                throw new \Exception('Failed to start server process');
            }

            // Wait briefly to ensure process started
            usleep(100000); // 100ms

            if (!$this->is_server_running()) {
                throw new \Exception('Server process failed to start');
            }

            return true;
        } catch (\Exception $e) {
            error_log('WebSocket Server: Start failed: ' . $e->getMessage());
            return false;
        }
    }
    
    public function stop() {
        try {
            $server = new Server_Process();
            if ($server->stop()) {
                return [
                    'status' => 'stopped',
                    'message' => 'Server stopped successfully'
                ];
            }
            throw new \Exception('Failed to stop server: ' . $server->get_last_error());
        } catch (\Exception $e) {
            error_log('WebSocket Server Stop Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function restart() {
        $this->stop();
        sleep(2); // Wait for server to fully stop
        return $this->start();
    }

    private function is_server_running() {
        if (!file_exists($this->pid_file)) {
            return false;
        }

        $pid = (int) file_get_contents($this->pid_file);
        if (!$pid) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            exec("tasklist /FI \"PID eq $pid\" 2>&1", $output);
            return count($output) > 1;
        } else {
            exec("ps -p $pid", $output, $return_var);
            return $return_var === 0;
        }
    }

    public function get_server_status() {
        $running = $this->is_server_running();
        $pid = $running ? (int) file_get_contents($this->pid_file) : null;
        
        return [
            'running' => $running,
            'pid' => $pid,
            'uptime' => $running ? $this->get_server_uptime($pid) : 0,
            'memory' => $running ? $this->get_server_memory($pid) : 0
        ];
    }

    private function get_server_uptime($pid) {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows doesn't provide easy access to process start time
            return 0;
        } else {
            exec("ps -o etimes= -p $pid 2>&1", $output, $return_var);
            return $return_var === 0 ? (int) trim($output[0]) : 0;
        }
    }

    private function get_server_memory($pid) {
        if (PHP_OS_FAMILY === 'Windows') {
            exec("wmic process where ProcessId=$pid get WorkingSetSize 2>&1", $output);
            return isset($output[1]) ? (int) $output[1] : 0;
        } else {
            exec("ps -o rss= -p $pid 2>&1", $output, $return_var);
            return $return_var === 0 ? ((int) trim($output[0]) * 1024) : 0;
        }
    }

    private function get_node_binary() {
        // Check custom path first
        $custom_path = get_option('sewn_ws_node_path');
        if ($custom_path && file_exists($custom_path)) {
            return $custom_path;
        }

        // Log the node detection attempt
        error_log('Attempting to detect Node.js binary...');

        $paths = PHP_OS_FAMILY === 'Windows' 
            ? ['node.exe', 'C:\\Program Files\\nodejs\\node.exe', 'C:\\Program Files (x86)\\nodejs\\node.exe']
            : ['/usr/bin/node', '/usr/local/bin/node', '/opt/node/bin/node'];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                error_log("Node.js binary found at: {$path}");
                return $path;
            }
        }

        // Try which command on Unix systems
        if (PHP_OS_FAMILY !== 'Windows') {
            exec('which node 2>&1', $output, $return_var);
            if ($return_var === 0) {
                $path = trim($output[0]);
                error_log("Node.js binary found using which: {$path}");
                return $path;
            }
        }

        error_log('Node.js binary not found in any standard location');
        return null;
    }

    public function handle_stats_request() {
        try {
            // Verify nonce first
            check_ajax_referer(SEWN_WS_NONCE_ACTION, 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error([
                    'message' => 'Insufficient permissions',
                    'code' => 'FORBIDDEN'
                ], 403);
                return;
            }

            // Get server status
            $status = $this->get_server_status();
            
            // If server is running, try to get additional stats
            if ($status['running'] && $status['pid']) {
                $stats = [
                    'uptime' => $this->get_server_uptime($status['pid']),
                    'memory' => $this->get_server_memory($status['pid']),
                    'connections' => 0, // Will be updated by WebSocket server
                    'errors' => 0       // Will be updated by WebSocket server
                ];
                $status = array_merge($status, $stats);
            }

            wp_send_json_success($status);

        } catch (\Exception $e) {
            error_log('WebSocket Stats Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'ERROR'
            ], 500);
        }
    }

    public function handle_channel_stats() {
        error_log("Channel stats request received");
        wp_send_json_success(['status' => 'ok']);
    }

    private function get_port() {
        return get_option('sewn_ws_port', 49200);
    }

    private function get_host() {
        return get_option('sewn_ws_host', 'localhost');
    }

    private function get_stats_file() {
        return $this->get_server_path('stats.json');
    }

    private function get_pid_file() {
        return $this->get_server_path('server.pid');
    }

    private function get_log_file() {
        return $this->get_server_path('server.log');
    }

    private function get_server_path($file) {
        return plugin_dir_path(dirname(__FILE__)) . 'node-server/' . $file;
    }

    private function get_server_env() {
        $env = [
            'WP_PORT' => $this->get_port(),
            'WP_HOST' => $this->get_host(),
            'WP_DEBUG' => defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false',
            'NODE_ENV' => defined('WP_DEBUG') && WP_DEBUG ? 'development' : 'production',
            'WP_PLUGIN_DIR' => plugin_dir_path(dirname(__FILE__)),
            'WP_STATS_FILE' => $this->get_stats_file(),
            'WP_PID_FILE' => $this->get_pid_file(),
            'WP_LOG_FILE' => $this->get_log_file(),
            'WP_PROXY_PATH' => '/websocket',
            'WP_JWT_SECRET' => defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : wp_salt('auth'),
            'WP_SITE_URL' => get_site_url()
        ];

        // Only add Redis URL if configured
        $redis_url = get_option('sewn_ws_redis_url', false);
        if ($redis_url) {
            $env['REDIS_URL'] = $redis_url;
        }

        return $env;
    }
} 