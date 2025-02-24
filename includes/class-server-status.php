<?php

/**
 * Location: includes/class-server-status.php
 * Dependencies: Process checking via pgrep, WordPress options API
 * Classes: SEWN_WS_Server_Status
 * 
 * Provides real-time server health monitoring for WebSocket infrastructure. Integrates with system processes to track running status, active connections, and resource utilization for network diagnostics.
 */

namespace SEWN\WebSockets;

/**
 * Server Status Handler
 *
 * @package SEWN\WebSockets
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Server Status Class
 * 
 * Handles WebSocket server status monitoring and health checks
 */
class Server_Status {
    /**
     * @var Server_Status|null Singleton instance
     */
    private static $instance = null;

    /**
     * @var string Path to stats file
     */
    private $stats_file;

    /**
     * Get singleton instance
     * 
     * @return Server_Status
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->stats_file = SEWN_WS_PLUGIN_DIR . 'tmp/server-stats.json';
        wp_mkdir_p(dirname($this->stats_file));
    }

    /**
     * Get server status including PID, port, uptime and connections
     *
     * @return array Server status data
     */
    public function get_status() {
        $status = [
            'running' => false,
            'pid' => null,
            'port' => SEWN_WS_DEFAULT_PORT,
            'uptime' => 0,
            'connections' => 0
        ];

        $pid_file = SEWN_WS_PATH . '/node-server/server.pid';
        $stats_file = SEWN_WS_PATH . '/node-server/stats.json';

        if (file_exists($pid_file)) {
            $pid = trim(file_get_contents($pid_file));
            if ($pid && $this->is_process_running($pid)) {
                $status['running'] = true;
                $status['pid'] = (int)$pid;

                if (file_exists($stats_file)) {
                    $stats = json_decode(file_get_contents($stats_file), true);
                    if ($stats) {
                        $status['uptime'] = time() - ($stats['startTime'] ?? time());
                        $status['connections'] = $stats['connections'] ?? 0;
                        $status['port'] = $stats['port'] ?? SEWN_WS_DEFAULT_PORT;
                    }
                }
            } else {
                @unlink($pid_file);
            }
        }

        return $status;
    }

    /**
     * Check if a process is running
     *
     * @param int $pid Process ID
     * @return bool Whether process is running
     */
    private function is_process_running($pid) {
        if (!function_exists('posix_kill')) {
            return false;
        }

        try {
            return posix_kill($pid, 0);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get comprehensive server health status
     * 
     * @return array Server health information
     */
    public function get_server_health() {
        $process_manager = Process_Manager::get_instance();
        $port = get_option(SEWN_WS_OPTION_PORT, SEWN_WS_DEFAULT_PORT);
        
        // Check if port is in use
        $connection = @fsockopen('localhost', $port, $errno, $errstr, 1);
        $is_running = is_resource($connection);
        if ($is_running) {
            fclose($connection);
        }
        
        // Get server metrics
        $metrics = $this->get_server_metrics();
        
        return array_merge(
            [
                'running' => $is_running,
                'port' => $port,
                'pid' => $process_manager->get_current_pid(),
                'external' => false
            ],
            $metrics
        );
    }

    /**
     * Get server metrics from stats file
     * 
     * @return array Server metrics
     */
    public function get_server_metrics() {
        $default_metrics = [
            'uptime' => 0,
            'memory' => 0,
            'connections' => 0,
            'socket_status' => 'Disconnected'
        ];
        
        if (!file_exists($this->stats_file)) {
            return $default_metrics;
        }
        
        $stats = json_decode(file_get_contents($this->stats_file), true);
        if (!is_array($stats)) {
            return $default_metrics;
        }
        
        // Format metrics for display
        return [
            'uptime' => $this->format_uptime($stats['uptime'] ?? 0),
            'memory' => $this->format_memory($stats['memory'] ?? 0),
            'connections' => intval($stats['connections'] ?? 0),
            'socket_status' => $stats['socket_status'] ?? 'Unknown'
        ];
    }

    /**
     * Format memory size for display
     * 
     * @param int $bytes Memory size in bytes
     * @return string Formatted memory size
     */
    private function format_memory($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
    }

    /**
     * Format uptime for display
     * 
     * @param int $seconds Uptime in seconds
     * @return string Formatted uptime
     */
    private function format_uptime($seconds) {
        $seconds = intval($seconds);
        if ($seconds < 1) {
            return '0s';
        }
        
        $days = floor($seconds / 86400);
        $seconds %= 86400;
        $hours = floor($seconds / 3600);
        $seconds %= 3600;
        $minutes = floor($seconds / 60);
        $seconds %= 60;
        
        $uptime = '';
        if ($days > 0) $uptime .= $days . 'd ';
        if ($hours > 0) $uptime .= $hours . 'h ';
        if ($minutes > 0) $uptime .= $minutes . 'm ';
        if ($seconds > 0) $uptime .= $seconds . 's';
        
        return trim($uptime);
    }
} 