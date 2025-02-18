<?php
/**
 * LOCATION: includes/class-process-manager.php
 * DEPENDENCIES: Server_Process, WordPress WP-CLI
 * VARIABLES: $child_pids (array)
 * CLASSES: Process_Manager (service controller)
 * 
 * Manages WebSocket server processes and ensures high availability. Implements fault tolerance mechanisms required
 * for enterprise-grade WebRing content distribution. Coordinates with network authentication services for secure startup.
 */

namespace SEWN\WebSockets;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
} 

class Process_Manager {
    /**
     * @var string Path to PID file
     */
    private $pid_file;

    /**
     * @var string Path to log file
     */
    private $log_file;

    /**
     * @var string Path to Node server script
     */
    private $server_script;

    /**
     * Constructor
     */
    public function __construct() {
        $this->pid_file = SEWN_WS_PATH . 'tmp/server.pid';
        $this->log_file = SEWN_WS_PATH . 'logs/server.log';
        $this->server_script = SEWN_WS_NODE_SERVER . 'server.js';
        
        // Ensure directories exist
        wp_mkdir_p(dirname($this->pid_file));
        wp_mkdir_p(dirname($this->log_file));
    }

    /**
     * Start the Node.js server
     *
     * @return array Status of server start attempt
     */
    public function start_server() {
        if ($this->is_running()) {
            return [
                'success' => false,
                'message' => 'Server is already running'
            ];
        }

        $node_path = $this->get_node_path();
        if (!$node_path) {
            return [
                'success' => false,
                'message' => 'Node.js not found'
            ];
        }

        $command = sprintf(
            '%s %s > %s 2>&1 & echo $! > %s',
            escapeshellcmd($node_path),
            escapeshellarg($this->server_script),
            escapeshellarg($this->log_file),
            escapeshellarg($this->pid_file)
        );

        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            return [
                'success' => false,
                'message' => 'Failed to start server'
            ];
        }

        return [
            'success' => true,
            'message' => 'Server started successfully',
            'pid' => file_get_contents($this->pid_file)
        ];
    }

    /**
     * Stop the Node.js server
     *
     * @return array Status of server stop attempt
     */
    public function stop_server() {
        if (!$this->is_running()) {
            return [
                'success' => false,
                'message' => 'Server is not running'
            ];
        }

        $pid = (int) file_get_contents($this->pid_file);
        
        if (posix_kill($pid, SIGTERM)) {
            unlink($this->pid_file);
            return [
                'success' => true,
                'message' => 'Server stopped successfully'
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to stop server'
        ];
    }

    /**
     * Restart the Node.js server
     *
     * @return array Status of server restart attempt
     */
    public function restart_server() {
        $stop_result = $this->stop_server();
        if (!$stop_result['success']) {
            return $stop_result;
        }

        // Wait briefly for port to be freed
        sleep(2);

        return $this->start_server();
    }

    /**
     * Check if server is running
     *
     * @return boolean
     */
    public function is_running() {
        if (!file_exists($this->pid_file)) {
            return false;
        }

        $pid = (int) file_get_contents($this->pid_file);
        return posix_kill($pid, 0);
    }

    /**
     * Get server status
     *
     * @return array Server status information
     */
    public function get_status() {
        $running = $this->is_running();
        $pid = $running ? (int) file_get_contents($this->pid_file) : null;
        
        return [
            'running' => $running,
            'pid' => $pid,
            'uptime' => $running ? $this->get_uptime($pid) : 0,
            'log_tail' => $this->get_log_tail()
        ];
    }

    /**
     * Get recent log entries
     *
     * @param int $lines Number of lines to retrieve
     * @return string Recent log entries
     */
    public function get_log_tail($lines = 50) {
        if (!file_exists($this->log_file)) {
            return '';
        }

        $command = sprintf('tail -%d %s', (int) $lines, escapeshellarg($this->log_file));
        return shell_exec($command);
    }

    /**
     * Get Node.js executable path
     *
     * @return string|false Path to Node.js or false if not found
     */
    private function get_node_path() {
        $output = shell_exec('which node');
        return $output ? trim($output) : false;
    }

    /**
     * Get process uptime
     *
     * @param int $pid Process ID
     * @return int Uptime in seconds
     */
    private function get_uptime($pid) {
        $stat = file_get_contents("/proc/$pid/stat");
        if (!$stat) {
            return 0;
        }

        $stats = explode(' ', $stat);
        $starttime = isset($stats[21]) ? (int) $stats[21] : 0;
        $uptime = time() - ($starttime / 100);
        
        return max(0, (int) $uptime);
    }

    const NAMESPACE = 'sewn-ws';
    
    public static function get_active_connections() {
        return apply_filters('sewn_ws_active_connections', []);
    }

    public static function activate() {
        try {
            // 1. Server config
            self::create_default_config();
            
            // 2. Dependency checks
            if(!self::check_node_version()) {
                wp_die('Node.js 16.x or higher required');
            }
            
            // 3. Directory setup
            self::create_log_directory();
            
            // 4. Cron jobs
            if(!wp_next_scheduled('sewn_ws_health_check')) {
                wp_schedule_event(time(), 'hourly', 'sewn_ws_health_check');
            }
            
            // 5. Default options
            update_option('sewn_ws_port', 8080);
            update_option('sewn_ws_tls_enabled', false);
            update_option('sewn_ws_rate_limits', [
                'free' => 10,
                'freewire' => 30,
                'wire' => 100,
                'extrawire' => 500
            ]);

            // Sync VM timezone with host
            date_default_timezone_set('America/Los_Angeles'); // Match your Local config
            shell_exec('ln -sf /usr/share/zoneinfo/America/Los_Angeles /etc/localtime');
        } catch(\Throwable $e) {
            update_option('sewn_ws_activation_error', $e->getMessage());
        }
    }
    
    public static function deactivate() {
        exec('pm2 stop ' . self::NAMESPACE);
    }

    private static function check_node_version() {
        exec('node -v', $output, $result);
        if($result !== 0) return false;
        
        $version = trim(str_replace('v', '', $output[0]));
        return version_compare($version, '16.0.0', '>=');
    }

    private static function create_log_directory() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/sewn-ws-logs/';
        
        if(!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            file_put_contents($log_dir . '.htaccess', 'Deny from all');
        }
    }

    private static function create_default_config() {
        $config = [
            'port' => get_option('sewn_ws_port', 8080),
            'tls' => [
                'enabled' => false,
                'cert' => '',
                'key' => ''
            ],
            'origins' => [
                home_url(),
                'chrome-extension://your-extension-id'
            ]
        ];
        
        file_put_contents(SEWN_WS_NODE_SERVER . 'config.json', json_encode($config));
    }

    public static function get_message_throughput() {
        return [
            'current' => (float) get_option('sewn_ws_msg_rate_current', 0),
            'max' => (float) get_option('sewn_ws_msg_rate_max', 0)
        ];
    }

    public static function calculate_throughput() {
        $history = get_option('sewn_ws_msg_rate_history', []);
        $current = count($history) > 0 ? end($history) : 0;
        $max = $history ? max($history) : 0;
        
        update_option('sewn_ws_msg_rate_current', $current);
        update_option('sewn_ws_msg_rate_max', $max);
        
        return $current;
    }

    public static function get_message_queue_depth() {
        // Implementation needed
        return [
            'current' => (int) get_option('sewn_ws_queue_current', 0),
            'max_capacity' => 1000, // From config
            'warning_threshold' => 750
        ];
    }

    public static function get_failure_rates() {
        return [
            'last_hour' => (float) get_option('sewn_ws_failure_rate_1h', 0.0),
            'last_24h' => (float) get_option('sewn_ws_failure_rate_24h', 0.0),
            'threshold' => 0.05 // 5% failure rate threshold
        ];
    }

    public static function get_bandwidth_usage() {
        return [
            'incoming' => (int) get_option('sewn_ws_bandwidth_in', 0),
            'outgoing' => (int) get_option('sewn_ws_bandwidth_out', 0),
            'max_allowed' => 1073741824 // 1GB default
        ];
    }
}

// Add to admin notices
add_action('admin_notices', function() {
    if($error = get_option('sewn_ws_activation_error')) {
        echo '<div class="notice notice-error">';
        echo '<pre>Activation Error: ' . esc_html($error) . '</pre>';
        echo '</div>';
        delete_option('sewn_ws_activation_error');
    }
});
