<?php

namespace SEWN\WebSockets\Admin;

class Error_Logger {
    private static $instance = null;
    private $log_file;
    private $max_log_size = 5242880; // 5MB
    private $rotate_count = 5;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = trailingslashit($upload_dir['basedir']) . 'sewn-ws/logs/error.log';
        $this->ensure_log_directory();
    }

    private function ensure_log_directory() {
        $log_dir = dirname($this->log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        // Create .htaccess to protect logs
        $htaccess = $log_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all");
        }
    }

    public function log($message, $context = [], $level = 'error') {
        if ($this->should_rotate_logs()) {
            $this->rotate_logs();
        }

        $entry = $this->format_log_entry($message, $context, $level);
        
        error_log($entry . PHP_EOL, 3, $this->log_file);

        // Store recent errors in transient for dashboard display
        $recent_errors = get_transient('sewn_ws_recent_errors') ?: [];
        array_unshift($recent_errors, [
            'message' => $message,
            'context' => $context,
            'level' => $level,
            'time' => current_time('mysql')
        ]);
        $recent_errors = array_slice($recent_errors, 0, 50); // Keep last 50 errors
        set_transient('sewn_ws_recent_errors', $recent_errors, DAY_IN_SECONDS);

        // Trigger admin notification for critical errors
        if ($level === 'critical') {
            $this->notify_admin($message, $context);
        }
    }

    private function format_log_entry($message, $context, $level) {
        $timestamp = current_time('mysql');
        $context_json = json_encode($context);
        
        // Add system state information
        $system_state = [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'is_local' => $this->is_local_environment()
        ];

        return sprintf(
            "[%s] [%s] %s | Context: %s | System: %s",
            $timestamp,
            strtoupper($level),
            $message,
            $context_json,
            json_encode($system_state)
        );
    }

    private function should_rotate_logs() {
        return file_exists($this->log_file) && filesize($this->log_file) > $this->max_log_size;
    }

    private function rotate_logs() {
        for ($i = $this->rotate_count; $i > 0; $i--) {
            $old_file = $this->log_file . '.' . ($i - 1);
            $new_file = $this->log_file . '.' . $i;
            
            if (file_exists($old_file)) {
                rename($old_file, $new_file);
            }
        }

        if (file_exists($this->log_file)) {
            rename($this->log_file, $this->log_file . '.1');
        }
    }

    private function notify_admin($message, $context) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] Critical WebSocket Server Error', $site_name);
        
        $body = "A critical error has occurred in the WebSocket server:\n\n";
        $body .= "Message: $message\n\n";
        $body .= "Context: " . print_r($context, true) . "\n\n";
        $body .= "Time: " . current_time('mysql') . "\n";
        $body .= "Environment: " . ($this->is_local_environment() ? 'Local' : 'Production') . "\n";
        
        wp_mail($admin_email, $subject, $body);
    }

    private function is_local_environment() {
        $environment = get_option('sewn_ws_environment', []);
        return isset($environment['type']) && $environment['type'] !== 'production';
    }

    public function get_recent_errors($limit = 50) {
        return get_transient('sewn_ws_recent_errors') ?: [];
    }

    public function clear_logs() {
        // Clear log file
        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }

        // Clear rotated logs
        for ($i = 1; $i <= $this->rotate_count; $i++) {
            $rotated_file = $this->log_file . '.' . $i;
            if (file_exists($rotated_file)) {
                unlink($rotated_file);
            }
        }

        // Clear recent errors transient
        delete_transient('sewn_ws_recent_errors');

        return true;
    }

    public function export_logs() {
        $logs = [];
        
        // Get main log
        if (file_exists($this->log_file)) {
            $logs[] = file_get_contents($this->log_file);
        }

        // Get rotated logs
        for ($i = 1; $i <= $this->rotate_count; $i++) {
            $rotated_file = $this->log_file . '.' . $i;
            if (file_exists($rotated_file)) {
                $logs[] = file_get_contents($rotated_file);
            }
        }

        return implode("\n", $logs);
    }
} 