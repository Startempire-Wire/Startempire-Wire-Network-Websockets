<?php

/**
 * Location: includes/class-server-process.php
 * Dependencies: Process_Manager
 * Classes: Server_Process
 * 
 * Manages individual WebSocket server processes, including lifecycle management,
 * status monitoring, and error handling.
 */

namespace SEWN\WebSockets;

class Server_Process {
    private $pid = null;
    private $port;
    private $last_error = '';
    private $status = 'stopped';
    private $node_script;
    private $log_file;
    private $pid_file;
    private $stats_file;
    private $process_output;
    private $start_time;

    /**
     * Initialize a new server process instance
     * 
     * @param int $port Port number for WebSocket server
     */
    public function __construct($port = 8080) {
        $this->port = $port;
        $this->node_script = plugin_dir_path(dirname(__FILE__)) . 'node-server/server.js';
        $this->log_file = plugin_dir_path(dirname(__FILE__)) . 'node-server/server.log';
        $this->pid_file = plugin_dir_path(dirname(__FILE__)) . 'node-server/server.pid';
        $this->stats_file = plugin_dir_path(dirname(__FILE__)) . 'node-server/stats.json';
        
        // Create log directory if it doesn't exist
        $log_dir = dirname($this->log_file);
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }

    /**
     * Start the WebSocket server process
     * 
     * @return bool True if server started successfully
     */
    public function start() {
        if ($this->is_running()) {
            $this->last_error = 'Server already running';
            return false;
        }

        // Check Node.js installation
        if (!$this->check_node_installation()) {
            return false;
        }

        // Verify server script exists
        if (!file_exists($this->node_script)) {
            $this->last_error = sprintf('Node.js server script not found at: %s', $this->node_script);
            error_log($this->last_error);
            return false;
        }

        // Create log directory if it doesn't exist
        $log_dir = dirname($this->log_file);
        if (!file_exists($log_dir)) {
            if (!mkdir($log_dir, 0755, true)) {
                $this->last_error = sprintf('Failed to create log directory: %s', $log_dir);
                error_log($this->last_error);
                return false;
            }
        }

        // Prepare environment variables
        $env = [
            'NODE_ENV' => 'production',
            'WP_PLUGIN_DIR' => plugin_dir_path(dirname(__FILE__)),
            'WP_PORT' => $this->port,
            'WP_DEBUG' => defined('WP_DEBUG') && WP_DEBUG ? '1' : '0'
        ];

        // Add system environment variables
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'PATH') !== false) {
                $env[$key] = $value;
            }
        }

        // Prepare command
        $node_binary = $this->get_node_binary();
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['file', $this->log_file, 'a'],  // stdout
            2 => ['file', $this->log_file, 'a']   // stderr
        ];

        $cmd = [$node_binary, $this->node_script, '--port=' . $this->port];
        
        error_log('Starting WebSocket server with command: ' . implode(' ', $cmd));
        error_log('Working directory: ' . dirname($this->node_script));
        error_log('Environment variables: ' . print_r($env, true));

        // Start process
        $process = proc_open($cmd, $descriptors, $pipes, dirname($this->node_script), $env);

        if (!is_resource($process)) {
            $this->last_error = 'Failed to start server process';
            error_log($this->last_error);
            return false;
        }

        // Get process information
        $status = proc_get_status($process);
        if ($status === false || !$status['running']) {
            $this->last_error = 'Process failed to start';
            error_log($this->last_error);
            proc_close($process);
            return false;
        }

        $this->pid = $status['pid'];
        
        // Store PID
        file_put_contents($this->pid_file, $this->pid);
        $this->status = 'running';
        $this->start_time = time();

        // Initialize stats file
        $this->update_stats([
            'status' => 'running',
            'pid' => $this->pid,
            'port' => $this->port,
            'start_time' => $this->start_time,
            'connections' => 0,
            'memory_usage' => 0
        ]);

        error_log('WebSocket server started successfully with PID: ' . $this->pid);

        // Don't close process handle - let it run in background
        return true;
    }

    /**
     * Stop the WebSocket server process
     * 
     * @return bool True if server stopped successfully
     */
    public function stop() {
        if (!$this->is_running()) {
            return true;
        }

        $success = false;
        if (PHP_OS_FAMILY === 'Windows') {
            exec("taskkill /F /PID {$this->pid} 2>&1", $output, $return_var);
            $success = $return_var === 0;
        } else {
            // Try graceful shutdown first
            if (posix_kill($this->pid, SIGTERM)) {
                // Wait up to 5 seconds for graceful shutdown
                $timeout = 5;
                while ($timeout > 0 && $this->is_running()) {
                    sleep(1);
                    $timeout--;
                }
                $success = !$this->is_running();
            }
            
            // Force kill if still running
            if (!$success && posix_kill($this->pid, SIGKILL)) {
                $success = true;
            }
        }

        if ($success) {
            $this->cleanup();
            return true;
        }

        $this->last_error = 'Failed to stop server process';
        return false;
    }

    /**
     * Check if the server process is running
     * 
     * @return bool True if server is running
     */
    public function is_running() {
        if (!$this->pid) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            exec("tasklist /FI \"PID eq {$this->pid}\" 2>&1", $output);
            return count($output) > 1 && strpos($output[1], (string)$this->pid) !== false;
        } else {
            return posix_kill($this->pid, 0);
        }
    }

    /**
     * Get detailed server status
     * 
     * @return array Server status information
     */
    public function get_status() {
        $running = $this->is_running();
        $status = [
            'status' => $running ? 'running' : 'stopped',
            'pid' => $this->pid,
            'port' => $this->port,
            'uptime' => $running && $this->start_time ? time() - $this->start_time : 0,
            'last_error' => $this->last_error
        ];

        // Add stats from stats file if available
        if ($running && file_exists($this->stats_file)) {
            $stats = json_decode(file_get_contents($this->stats_file), true);
            if ($stats) {
                $status = array_merge($status, $stats);
            }
        }

        return $status;
    }

    /**
     * Update server statistics
     * 
     * @param array $stats Statistics to update
     */
    private function update_stats($stats) {
        file_put_contents($this->stats_file, json_encode($stats));
    }

    /**
     * Clean up server files
     */
    private function cleanup() {
        $this->pid = null;
        $this->status = 'stopped';
        $this->start_time = null;
        
        // Clean up files
        @unlink($this->pid_file);
        @unlink($this->stats_file);
    }

    /**
     * Get Node.js binary path
     * 
     * @return string|null Path to Node.js binary or null if not found
     */
    private function get_node_binary() {
        // Check custom path first
        $custom_path = get_option('sewn_ws_node_path');
        if ($custom_path && file_exists($custom_path)) {
            return $custom_path;
        }

        $paths = PHP_OS_FAMILY === 'Windows' 
            ? ['node.exe', 'C:\\Program Files\\nodejs\\node.exe']
            : ['/usr/bin/node', '/usr/local/bin/node'];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Try which command on Unix systems
        if (PHP_OS_FAMILY !== 'Windows') {
            exec('which node 2>&1', $output, $return_var);
            if ($return_var === 0) {
                return trim($output[0]);
            }
        }

        $this->last_error = 'Node.js binary not found';
        return null;
    }

    /**
     * Check Node.js installation
     * 
     * @return bool True if Node.js is properly installed
     */
    private function check_node_installation() {
        $node_path = $this->get_node_binary();
        if (!$node_path) {
            $this->last_error = 'Node.js is not installed or not found in PATH';
            return false;
        }

        // Check Node.js version
        exec("$node_path -v 2>&1", $output, $return_var);
        if ($return_var !== 0) {
            $this->last_error = 'Failed to determine Node.js version';
            return false;
        }

        $version = trim($output[0]);
        if (version_compare(substr($version, 1), '16.0.0', '<')) {
            $this->last_error = 'Node.js version 16.0.0 or higher is required';
            return false;
        }

        return true;
    }

    /**
     * Get environment variables for Node.js process
     * 
     * @return string Environment variables string
     */
    private function get_environment_variables() {
        $env = [
            'NODE_ENV' => 'production',
            'WP_PLUGIN_DIR' => plugin_dir_path(dirname(__FILE__)),
            'WP_PORT' => $this->port,
            'WP_DEBUG' => defined('WP_DEBUG') && WP_DEBUG ? '1' : '0'
        ];

        // Escape each environment variable properly
        $escaped_env = array_map(function($key, $value) {
            return sprintf('%s=%s', $key, escapeshellarg($value));
        }, array_keys($env), array_values($env));

        return implode(' ', $escaped_env);
    }

    /**
     * Get server process ID
     * 
     * @return int|null Process ID or null if not running
     */
    public function get_pid() {
        return $this->pid;
    }

    /**
     * Get last error message
     * 
     * @return string Last error message
     */
    public function get_last_error() {
        return $this->last_error;
    }
}