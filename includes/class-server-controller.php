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
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_ajax_sewn_ws_server_control', [$this, 'handle_server_control']);
        add_action('wp_ajax_sewn_ws_get_stats', [$this, 'handle_stats_request']);

        $this->server_script = SEWN_WS_PATH . 'server/server.js';
        $this->pid_file = SEWN_WS_PATH . 'server/server.pid';
    }

    public function handle_server_control() {
        try {
            // Verify nonce first
            check_ajax_referer(SEWN_WS_NONCE_ACTION, 'nonce');
            
            if (!current_user_can('manage_options')) {
                error_log('WebSocket Server: Unauthorized access attempt');
                wp_send_json_error([
                    'message' => 'Insufficient permissions',
                    'code' => 'FORBIDDEN',
                    'php_version' => PHP_VERSION,
                    'node_path' => $this->node_binary
                ], 403);
                return;
            }

            $command = sanitize_text_field($_POST['command'] ?? '');
            if (!in_array($command, ['start', 'stop', 'restart'])) {
                throw new \Exception('Invalid command: ' . $command);
            }

            // Initialize node binary path
            if (!$this->node_binary) {
                $this->node_binary = $this->get_node_binary();
            }

            if (!$this->node_binary) {
                throw new \Exception('Node.js binary not found. Please check server requirements.');
            }

            // Log the command attempt
            error_log("WebSocket Server: Attempting {$command} command");

            // Execute command and return response
            $result = $this->execute_server_command($command);
            
            // Log success
            error_log("WebSocket Server: {$command} command executed successfully");
            
            wp_send_json_success([
                'status' => $this->get_server_status(),
                'message' => "Server {$command} executed successfully",
                'php_version' => PHP_VERSION,
                'node_path' => $this->node_binary,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            error_log('WebSocket Server Control Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'ERROR',
                'php_version' => PHP_VERSION,
                'node_path' => $this->node_binary ?? 'undefined',
                'debug' => [
                    'error_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
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
            // Get port from settings
            $port = get_option('sewn_ws_port', SEWN_WS_DEFAULT_PORT);
            
            // Create new server process
            $server = new Server_Process($port);
            
            // Attempt to start the server
            if (!$server->start()) {
                throw new \Exception('Failed to start server: ' . $server->get_last_error());
            }
            
            return [
                'status' => 'running',
                'port' => $port,
                'pid' => $server->get_pid(),
                'message' => 'Server started successfully'
            ];
            
        } catch (\Exception $e) {
            error_log('WebSocket Server Start Error: ' . $e->getMessage());
            throw $e;
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
} 