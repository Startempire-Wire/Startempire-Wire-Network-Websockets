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
     * Check if a port is available
     *
     * @param int $port Port to check
     * @return bool True if port is available
     */
    private function is_port_available($port) {
        $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if ($sock) {
            fclose($sock);
            return false;  // Port is in use
        }
        return true;  // Port is available
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

        // Clean up any stale files
        $this->cleanup_stale_files();

        $node_path = $this->get_node_path();
        if (!$node_path) {
            return [
                'success' => false,
                'message' => 'Node.js not found'
            ];
        }

        // Create required directories with proper permissions
        $dirs = [
            dirname($this->pid_file),
            dirname($this->log_file),
            SEWN_WS_PATH . 'logs',
            SEWN_WS_PATH . 'tmp'
        ];

        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                if (!wp_mkdir_p($dir)) {
                    return [
                        'success' => false,
                        'message' => "Failed to create directory: $dir"
                    ];
                }
                // Ensure directory is writable
                chmod($dir, 0755);
            }
        }

        // Get port configuration
        $port = get_option('sewn_ws_port', SEWN_WS_DEFAULT_PORT);
        
        // Ensure port is numeric
        $port = is_numeric($port) ? (int)$port : SEWN_WS_DEFAULT_PORT;

        // Check if port is available
        if (!$this->is_port_available($port)) {
            return [
                'success' => false,
                'message' => sprintf('Port %d is already in use. Please configure a different port in settings.', $port)
            ];
        }

        // Set up environment variables for Node process
        $env = [
            'WP_PLUGIN_DIR' => SEWN_WS_PATH,
            'WP_PORT' => (string)$port,  // Explicitly cast to string
            'WP_DEBUG' => defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false',
            'WP_PID_FILE' => $this->pid_file,
            'WP_LOG_FILE' => $this->log_file,
            'WP_STATS_FILE' => SEWN_WS_PATH . 'tmp/stats.json',
            'NODE_ENV' => defined('WP_DEBUG') && WP_DEBUG ? 'development' : 'production'
        ];
        
        // Build environment string
        $env_string = '';
        foreach ($env as $key => $value) {
            $env_string .= sprintf('%s=%s ', escapeshellarg($key), escapeshellarg($value));
        }

        // Build command with proper path handling
        $command = sprintf(
            'cd %s && %s %s %s > %s 2>&1 & echo $! > %s',
            escapeshellarg(SEWN_WS_NODE_SERVER),  // Change to Node server directory
            $env_string,
            escapeshellcmd($node_path),
            escapeshellarg('server.js'),
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

        // Wait briefly and verify process is still running
        sleep(1);
        if (!$this->is_running()) {
            // Read the last few lines of the log for error details
            $error_log = $this->get_log_tail(5);
            return [
                'success' => false,
                'message' => 'Server failed to start. Check error log.',
                'error_details' => $error_log
            ];
        }

        // Record server start in history
        $this->record_server_start();

        return [
            'success' => true,
            'message' => 'Server started successfully',
            'pid' => file_get_contents($this->pid_file)
        ];
    }

    /**
     * Clean up stale server files
     */
    private function cleanup_stale_files() {
        // Clean up PID file if process is not actually running
        if (file_exists($this->pid_file)) {
            $pid = (int) file_get_contents($this->pid_file);
            if (!posix_kill($pid, 0)) {
                unlink($this->pid_file);
            }
        }

        // Clean up stats file if it exists
        $stats_file = dirname($this->pid_file) . '/stats.json';
        if (file_exists($stats_file)) {
            $stats = json_decode(file_get_contents($stats_file), true);
            if (isset($stats['pid']) && !posix_kill($stats['pid'], 0)) {
                unlink($stats_file);
            }
        }
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
        
        // Save current stats before stopping
        $this->persist_final_stats();
        
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
     * Persist final stats before server stops
     */
    private function persist_final_stats() {
        // Get current stats
        $stats = $this->get_status();
        
        // Save memory usage
        update_option('sewn_ws_last_memory_usage', $stats['memory_usage'] ?? 0);
        
        // Save connection history
        $history = get_option('sewn_ws_connection_history', []);
        $history[] = [
            'time' => time(),
            'connections' => count(self::get_active_connections())
        ];
        // Keep last 100 points
        $history = array_slice($history, -SEWN_WS_HISTORY_MAX_POINTS);
        update_option('sewn_ws_connection_history', $history);
        
        // Save memory history
        $memory_history = get_option('sewn_ws_memory_history', []);
        $memory_history[] = [
            'time' => time(),
            'usage' => $stats['memory_usage'] ?? 0
        ];
        // Keep last 100 points
        $memory_history = array_slice($memory_history, -SEWN_WS_HISTORY_MAX_POINTS);
        update_option('sewn_ws_memory_history', $memory_history);
        
        // Save server history
        $server_history = get_option(SEWN_WS_SERVER_HISTORY_OPTION, []);
        $server_history[] = [
            'time' => time(),
            'action' => 'stop',
            'uptime' => $stats['uptime'],
            'memory' => $stats['memory_usage'] ?? 0,
            'connections' => count(self::get_active_connections())
        ];
        // Keep last 100 points
        $server_history = array_slice($server_history, -SEWN_WS_HISTORY_MAX_POINTS);
        update_option(SEWN_WS_SERVER_HISTORY_OPTION, $server_history);
        
        // Save last known stats
        update_option(SEWN_WS_LAST_STATS_OPTION, [
            'time' => time(),
            'memory' => $stats['memory_usage'] ?? 0,
            'uptime' => $stats['uptime'],
            'connections' => count(self::get_active_connections()),
            'message_rate' => self::get_message_throughput()['current'],
            'error_rate' => self::get_failure_rates()['last_hour']
        ]);
        
        // Save subscriber count and update peak
        update_option('sewn_ws_active_subscribers', count(self::get_active_connections()));
        $current_peak = get_option('sewn_ws_peak_subscribers', 0);
        $current_subscribers = count(self::get_active_connections());
        if ($current_subscribers > $current_peak) {
            update_option('sewn_ws_peak_subscribers', $current_subscribers);
        }
    }

    /**
     * Record server start in history
     */
    private function record_server_start() {
        $server_history = get_option(SEWN_WS_SERVER_HISTORY_OPTION, []);
        $server_history[] = [
            'time' => time(),
            'action' => 'start',
            'pid' => file_exists($this->pid_file) ? (int)file_get_contents($this->pid_file) : null,
            'port' => get_option('sewn_ws_port', SEWN_WS_DEFAULT_PORT)
        ];
        $server_history = array_slice($server_history, -SEWN_WS_HISTORY_MAX_POINTS);
        update_option(SEWN_WS_SERVER_HISTORY_OPTION, $server_history);
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
        
        // First check if process exists
        if (!posix_kill($pid, 0)) {
            $this->cleanup_stale_files();
            return false;
        }

        // Then verify it's our Node.js process
        $command = sprintf('ps -p %d -o command=', $pid);
        $output = shell_exec($command);
        
        return strpos($output, 'node') !== false && strpos($output, $this->server_script) !== false;
    }

    /**
     * Get server status with enhanced stats
     */
    public function get_status() {
        $running = $this->is_running();
        $pid = $running ? (int) file_get_contents($this->pid_file) : null;
        
        // Try to get stats from stats file
        $stats = [];
        $stats_file = SEWN_WS_PATH . 'tmp/stats.json';
        if (file_exists($stats_file)) {
            $stats_content = file_get_contents($stats_file);
            if ($stats_content) {
                $stats = json_decode($stats_content, true) ?: [];
            }
        }
        
        return [
            'running' => $running,
            'pid' => $pid,
            'uptime' => $running ? $this->get_uptime($pid) : 0,
            'memory_usage' => $stats['memory'] ?? 0,
            'connections' => $stats['connections'] ?? 0,
            'message_rate' => $stats['messageRate'] ?? 0,
            'error_rate' => $stats['errorRate'] ?? 0,
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
            
            // 5. Default options - Use constants
            update_option('sewn_ws_port', SEWN_WS_DEFAULT_PORT);  // Changed from 8080
            update_option('sewn_ws_tls_enabled', false);
            update_option('sewn_ws_rate_limits', [
                'free' => 10,
                'freewire' => 30,
                'wire' => 100,
                'extrawire' => 500
            ]);

            // Create required directories with proper permissions
            $dirs = [
                SEWN_WS_PATH . 'logs',
                SEWN_WS_PATH . 'tmp',
                dirname(SEWN_WS_SERVER_CONFIG_FILE)
            ];

            foreach ($dirs as $dir) {
                if (!file_exists($dir)) {
                    wp_mkdir_p($dir);
                    chmod($dir, 0755);  // Ensure Node can write
                }
            }

            // Sync VM timezone with host
            date_default_timezone_set('America/Los_Angeles');
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
            'port' => get_option('sewn_ws_port', SEWN_WS_DEFAULT_PORT),  // Use constant
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

    /**
     * Get server history
     *
     * @return array Server history
     */
    public static function get_server_history() {
        $history = get_option(SEWN_WS_SERVER_HISTORY_OPTION, []);
        $retention = time() - (SEWN_WS_HISTORY_RETENTION_DAYS * 86400);
        
        // Filter out old entries
        $history = array_filter($history, function($entry) use ($retention) {
            return $entry['time'] >= $retention;
        });
        
        return $history;
    }

    /**
     * Get last known stats
     *
     * @return array Last known stats
     */
    public static function get_last_stats() {
        return get_option(SEWN_WS_LAST_STATS_OPTION, [
            'time' => 0,
            'memory' => 0,
            'uptime' => 0,
            'connections' => 0,
            'message_rate' => 0,
            'error_rate' => 0
        ]);
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
