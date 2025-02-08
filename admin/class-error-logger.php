<?php

namespace SEWN\WebSockets\Admin;

class Error_Logger {
    private static $instance = null;
    private $log_file;
    private $max_log_size = 5242880; // 5MB
    private $rotate_count = 5;
    private $last_log_id = 0;

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
        $this->last_log_id = (int) get_option('sewn_ws_last_log_id', 0);
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

        // Create index.php to prevent directory listing
        $index = $log_dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.");
        }
    }

    public function log($message, $context = [], $level = 'error') {
        if ($this->should_rotate_logs()) {
            $this->rotate_logs();
        }

        // Increment log ID
        $this->last_log_id++;
        update_option('sewn_ws_last_log_id', $this->last_log_id);

        $entry = $this->format_log_entry($message, $context, $level);
        
        error_log($entry . PHP_EOL, 3, $this->log_file);

        // Store recent errors in transient for dashboard display
        $recent_errors = get_transient('sewn_ws_recent_errors') ?: [];
        array_unshift($recent_errors, [
            'id' => $this->last_log_id,
            'message' => $message,
            'context' => $context,
            'level' => $level,
            'time' => current_time('mysql'),
            'timestamp' => time()
        ]);
        $recent_errors = array_slice($recent_errors, 0, 100); // Keep last 100 errors
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
            'memory_usage' => $this->format_bytes(memory_get_usage(true)),
            'peak_memory' => $this->format_bytes(memory_get_peak_usage(true)),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'is_local' => $this->is_local_environment(),
            'log_id' => $this->last_log_id
        ];

        return sprintf(
            "[%s] [%s] [ID:%d] %s | Context: %s | System: %s",
            $timestamp,
            strtoupper($level),
            $this->last_log_id,
            $message,
            $context_json,
            json_encode($system_state)
        );
    }

    private function format_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
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
        $body .= "Log ID: " . $this->last_log_id . "\n";
        
        wp_mail($admin_email, $subject, $body);
    }

    private function is_local_environment() {
        $environment = get_option('sewn_ws_environment', []);
        return isset($environment['type']) && $environment['type'] !== 'production';
    }

    public function get_recent_errors($limit = 100) {
        $errors = get_transient('sewn_ws_recent_errors') ?: [];
        return array_slice($errors, 0, $limit);
    }

    public function clear_logs() {
        // Clear log files
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

        // Reset log ID counter
        $this->last_log_id = 0;
        update_option('sewn_ws_last_log_id', 0);

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

        // Format for export
        $formatted_logs = [];
        foreach ($logs as $log_content) {
            $lines = explode("\n", $log_content);
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $formatted_logs[] = $this->format_log_line_for_export($line);
            }
        }

        return implode("\n", array_reverse($formatted_logs)); // Most recent first
    }

    private function format_log_line_for_export($line) {
        // Extract components using regex
        if (preg_match('/\[(.*?)\] \[(.*?)\] \[ID:(\d+)\] (.*?) \| Context: (.*?) \| System: (.*)/', $line, $matches)) {
            $timestamp = $matches[1];
            $level = $matches[2];
            $id = $matches[3];
            $message = $matches[4];
            $context = json_decode($matches[5], true);
            $system = json_decode($matches[6], true);

            // Format in a more readable way
            return sprintf(
                "Time: %s\nLevel: %s\nID: %s\nMessage: %s\nContext: %s\nSystem State: %s\n%s",
                $timestamp,
                $level,
                $id,
                $message,
                json_encode($context, JSON_PRETTY_PRINT),
                json_encode($system, JSON_PRETTY_PRINT),
                str_repeat('-', 80)
            );
        }

        // Return original line if it doesn't match expected format
        return $line;
    }
} 