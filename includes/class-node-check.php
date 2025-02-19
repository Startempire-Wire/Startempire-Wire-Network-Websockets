<?php
/**
 * LOCATION: includes/class-node-check.php
 * DEPENDENCIES: Process_Manager, Server_Controller
 * VARIABLES: SEWN_WS_NODE_SERVER (path constant), SEWN_WS_DEFAULT_PORT
 * CLASSES: Node_Check (environment validator)
 * 
 * Verifies Node.js runtime compatibility and service availability. Essential for maintaining WebSocket server
 * stability across the distributed Startempire Wire Network. Implements network authentication system health checks.
 */

 namespace SEWN\WebSockets;

class Node_Check {
    const REQUIRED_VERSION = '16.0.0';
    const NODE_PROCESS_NAME = 'sewn-ws-server.js';

    private $socket_file;
    
    public function __construct() {
        $this->socket_file = WP_CONTENT_DIR . '/sewn-ws.sock';
    }

    public static function meets_requirements() {
        error_log('[SEWN] Node check initiated');
        $status = self::get_status();
        error_log('[SEWN] Node status: ' . print_r($status, true));
        return $status['meets_requirements'];
    }

    public static function get_status() {
        $installed = self::is_installed();
        $version = $installed ? self::get_version() : '0.0.0';
        
        // Strip 'v' prefix if present
        $clean_version = ltrim($version, 'v');
        
        return [
            'version' => $clean_version,
            'active' => $installed,
            'meets_requirements' => version_compare($clean_version, self::REQUIRED_VERSION, '>=')
        ];
    }

    private static function is_installed() {
        exec('/usr/local/bin/node -v 2>/dev/null || /opt/homebrew/bin/node -v 2>/dev/null', $output, $result);
        return $result === 0;
    }

    private static function get_version() {
        $version = shell_exec('/usr/local/bin/node -v 2>/dev/null || /opt/homebrew/bin/node -v 2>/dev/null');
        return $version ? trim($version) : '0.0.0';
    }

    public static function check_version() {
        // Temporary implementation
        return true;
    }

    public function check_server_status() {
        try {
            $version = $this->get_node_version();
            $running = $this->is_server_running();
            $uptime = $running ? $this->get_server_uptime() : null;

            return [
                'version' => $version,
                'running' => $running,
                'uptime' => $uptime
            ];
        } catch (\Exception $e) {
            Error_Handler::log_error($e);
            return [
                'version' => 'unknown',
                'running' => false,
                'uptime' => null
            ];
        }
    }

    public function start_server() {
        if ($this->is_server_running()) {
            throw new Exception('Server is already running');
        }

        $node_script = SEWN_WS_PATH . 'server/index.js';
        $port = get_option('sewn_ws_port', SEWN_WS_DEFAULT_PORT);
        
        exec("node $node_script --port=$port > /dev/null 2>&1 & echo $!", $output);
        
        if (empty($output[0])) {
            throw new Exception('Failed to start server');
        }

        update_option('sewn_ws_server_pid', $output[0]);
        return true;
    }

    public function stop_server() {
        $pid = get_option('sewn_ws_server_pid');
        
        if (!$pid || !$this->is_server_running()) {
            return true; // Already stopped
        }

        exec("kill $pid");
        delete_option('sewn_ws_server_pid');
        return true;
    }

    public function restart_server() {
        $this->stop_server();
        sleep(1); // Wait for port to be released
        return $this->start_server();
    }

    private function is_server_running() {
        $pid = get_option('sewn_ws_server_pid');
        if (!$pid) return false;
        
        exec("ps -p $pid", $output);
        return count($output) > 1;
    }

    private function get_node_version() {
        exec('node --version', $output);
        return !empty($output[0]) ? $output[0] : 'unknown';
    }

    private function get_server_uptime() {
        $pid = get_option('sewn_ws_server_pid');
        if (!$pid) return null;
        
        // Get process start time on Linux/Unix systems
        exec("ps -o etime= -p $pid", $output);
        return !empty($output[0]) ? trim($output[0]) : null;
    }

    public static function get_connection_count() {
        $status = self::check_server_status();
        return $status['connections'] ?? 0;
    }

    public static function get_message_throughput() {
        // Implementation would query Node.js server
        return [
            'incoming' => get_transient('sewn_ws_msg_in_rate'),
            'outgoing' => get_transient('sewn_ws_msg_out_rate')
        ];
    }

    public static function get_active_rooms() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT room_name, COUNT(*) as connections 
             FROM {$wpdb->prefix}sewn_ws_connections 
             GROUP BY room_name"
        );
    }
} 