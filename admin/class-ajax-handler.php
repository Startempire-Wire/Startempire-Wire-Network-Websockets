<?php
/**
 * AJAX Handler Class
 *
 * @package Startempire_Wire_Network_Websockets
 * @subpackage Admin
 * @since 1.0.0
 */

namespace SEWN\WebSockets\Admin;

use SEWN\WebSockets\Stats_Handler;

/**
 * Handles AJAX requests for the admin interface.
 */
class Ajax_Handler {
    private static $instance = null;
    private $logger;
    private $monitor;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->logger = Error_Logger::get_instance();
        $this->monitor = Environment_Monitor::get_instance();

        add_action('wp_ajax_sewn_ws_clear_logs', [$this, 'clear_logs']);
        add_action('wp_ajax_sewn_ws_export_logs', [$this, 'export_logs']);
        add_action('wp_ajax_sewn_ws_check_environment', [$this, 'check_environment']);
        add_action('wp_ajax_sewn_ws_get_ssl_paths', [$this, 'get_ssl_paths']);
        add_action('wp_ajax_sewn_ws_toggle_debug', [$this, 'toggle_debug']);
        add_action('wp_ajax_sewn_ws_get_logs', [$this, 'get_logs']);
        add_action('wp_ajax_sewn_ws_get_stats', [$this, 'get_stats']);
    }

    /**
     * Toggle debug mode
     */
    public function toggle_debug() {
        check_ajax_referer('sewn_ws_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false;
        update_option('sewn_ws_debug_enabled', $enabled);

        if ($enabled) {
            $this->logger->log(
                'Debug mode enabled',
                array('user' => wp_get_current_user()->user_login),
                'info'
            );
        } else {
            $this->logger->log(
                'Debug mode disabled',
                array('user' => wp_get_current_user()->user_login),
                'info'
            );
        }

        wp_send_json_success();
    }

    /**
     * Get error logs with pagination
     */
    public function get_logs() {
        check_ajax_referer('sewn_ws_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $last_id = isset($_POST['last_id']) ? (int) $_POST['last_id'] : 0;
        $logs = $this->logger->get_recent_errors();

        // Filter logs newer than last_id
        $new_logs = array_filter($logs, function($log) use ($last_id) {
            return $log['id'] > $last_id;
        });

        // Sort logs by timestamp descending
        usort($new_logs, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        // Limit to 50 most recent logs
        $new_logs = array_slice($new_logs, 0, 50);

        wp_send_json_success($new_logs);
    }

    /**
     * Clear error logs
     */
    public function clear_logs() {
        check_ajax_referer('sewn_ws_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        try {
            $this->logger->clear_logs();
            $this->logger->log(
                'Logs cleared by user',
                array('user' => wp_get_current_user()->user_login),
                'info'
            );
            wp_send_json_success('Logs cleared successfully');
        } catch (\Exception $e) {
            wp_send_json_error('Failed to clear logs: ' . $e->getMessage());
        }
    }

    /**
     * Export error logs
     */
    public function export_logs() {
        check_ajax_referer('sewn_ws_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            $logs = $this->logger->export_logs();
            
            // Log the export
            $this->logger->log(
                'Logs exported by user',
                array('user' => wp_get_current_user()->user_login),
                'info'
            );

            // Set headers for download
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="websocket-server-logs-' . date('Y-m-d-H-i-s') . '.txt"');
            header('Content-Length: ' . strlen($logs));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            echo $logs;
            exit;
        } catch (\Exception $e) {
            wp_die('Failed to export logs: ' . $e->getMessage());
        }
    }

    public function check_environment() {
        check_ajax_referer('sewn_ws_check_environment', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        try {
            $result = $this->monitor->check_environment();
            $status = $this->monitor->get_environment_status();

            wp_send_json_success([
                'result' => $result,
                'status' => $status,
                'message' => __('Environment check completed successfully', 'sewn-ws')
            ]);
        } catch (\Exception $e) {
            $this->logger->log(
                'Environment check failed',
                ['error' => $e->getMessage()],
                'error'
            );
            wp_send_json_error('Environment check failed: ' . $e->getMessage());
        }
    }

    public function get_ssl_paths() {
        check_ajax_referer('sewn_ws_get_ssl_paths', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        try {
            $ssl_info = $this->monitor->get_ssl_info();
            
            wp_send_json_success([
                'paths' => $ssl_info['cert_paths'],
                'has_valid_cert' => $ssl_info['has_valid_cert'],
                'cert_details' => $ssl_info['cert_details']
            ]);
        } catch (\Exception $e) {
            $this->logger->log(
                'Failed to get SSL paths',
                ['error' => $e->getMessage()],
                'error'
            );
            wp_send_json_error('Failed to get SSL paths: ' . $e->getMessage());
        }
    }

    /**
     * Get current WebSocket statistics.
     *
     * @since 1.0.0
     * @return void
     */
    public function get_stats(): void {
        // Verify nonce and capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Get stats from handler
        $stats_handler = Stats_Handler::get_instance();
        $current_stats = $stats_handler->get_current_stats();

        wp_send_json_success($current_stats);
    }
}

// Initialize the AJAX handler
new Ajax_Handler(); 