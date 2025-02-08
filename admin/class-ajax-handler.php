<?php

namespace SEWN\WebSockets\Admin;

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
    }

    public function clear_logs() {
        check_ajax_referer('sewn_ws_clear_logs', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        try {
            $this->logger->clear_logs();
            wp_send_json_success('Logs cleared successfully');
        } catch (\Exception $e) {
            $this->logger->log(
                'Failed to clear logs',
                ['error' => $e->getMessage()],
                'error'
            );
            wp_send_json_error('Failed to clear logs: ' . $e->getMessage());
        }
    }

    public function export_logs() {
        check_ajax_referer('sewn_ws_export_logs', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        try {
            $logs = $this->logger->export_logs();
            
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="websocket-server-logs.txt"');
            header('Content-Length: ' . strlen($logs));
            
            echo $logs;
            exit;
        } catch (\Exception $e) {
            $this->logger->log(
                'Failed to export logs',
                ['error' => $e->getMessage()],
                'error'
            );
            wp_send_json_error('Failed to export logs: ' . $e->getMessage());
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
} 