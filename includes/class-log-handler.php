<?php
/**
 * Location: includes/class-log-handler.php
 * Dependencies: WordPress Filesystem API, Admin AJAX Hooks
 * Variables/Classes: Log_Handler, $log_file, $max_log_size
 * 
 * Manages plugin logging system with automatic rotation and admin access controls. Provides AJAX endpoints for log retrieval and implements size-based log rotation to maintain system performance.
 */
namespace SEWN\WebSockets;

class Log_Handler {
    private $log_file;
    private $max_log_size = 10485760; // 10MB
    private $rotation_count = 5;
    
    public function __construct() {
        add_action('wp_ajax_sewn_ws_get_logs', [$this, 'handle_log_request']);
        add_action('wp_ajax_sewn_ws_clear_logs', [$this, 'handle_clear_logs']);
        $this->log_file = SEWN_WS_PATH . 'logs/server.log';
    }

    public function handle_log_request() {
        check_ajax_referer('sewn_ws_admin');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        try {
            $logs = $this->get_recent_logs();
            wp_send_json_success(['logs' => $logs]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function handle_clear_logs() {
        check_ajax_referer('sewn_ws_admin');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        try {
            $this->clear_logs();
            wp_send_json_success(['message' => 'Logs cleared successfully']);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function get_recent_logs($limit = 1000, $level = 'all') {
        if (!file_exists($this->log_file)) {
            return [];
        }

        $logs = [];
        $handle = fopen($this->log_file, 'r');
        
        if ($handle) {
            // Read from end of file
            fseek($handle, -1, SEEK_END);
            $lines = [];
            
            // Read backwards until we have enough lines or reach start
            while (count($lines) < $limit && ftell($handle) > 0) {
                $char = fgetc($handle);
                if ($char === "\n") {
                    $line = implode('', array_reverse($lines));
                    $log_entry = $this->parse_log_line($line);
                    if ($log_entry && ($level === 'all' || $log_entry['level'] === $level)) {
                        $logs[] = $log_entry;
                    }
                    $lines = [];
                } else {
                    $lines[] = $char;
                }
                fseek($handle, -2, SEEK_CUR);
            }
            
            fclose($handle);
        }

        return array_reverse($logs);
    }

    private function parse_log_line($line) {
        if (empty($line)) {
            return null;
        }

        // Expected format: [2024-01-20 10:30:45] [INFO] Message here
        $pattern = '/\[(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]\s\[([^\]]+)\]\s(.+)/';
        
        if (preg_match($pattern, $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'level' => strtolower($matches[2]),
                'message' => $matches[3]
            ];
        }

        return null;
    }

    public function clear_logs() {
        if (file_exists($this->log_file)) {
            if (!unlink($this->log_file)) {
                throw new \Exception('Failed to clear logs');
            }
            
            // Create new empty log file
            if (!touch($this->log_file)) {
                throw new \Exception('Failed to create new log file');
            }
            
            // Set proper permissions
            chmod($this->log_file, 0644);
        }
    }

    public function write_log($message, $level = 'info') {
        if (!is_dir(dirname($this->log_file))) {
            mkdir(dirname($this->log_file), 0755, true);
        }

        // Check if rotation is needed
        $this->maybe_rotate_logs();

        $timestamp = date('Y-m-d H:i:s');
        $level = strtoupper($level);
        $log_entry = sprintf("[%s] [%s] %s\n", $timestamp, $level, $message);

        if (file_put_contents($this->log_file, $log_entry, FILE_APPEND) === false) {
            throw new \Exception('Failed to write to log file');
        }
    }

    private function maybe_rotate_logs() {
        if (!file_exists($this->log_file)) {
            return;
        }

        if (filesize($this->log_file) > $this->max_log_size) {
            for ($i = $this->rotation_count; $i > 0; $i--) {
                $old_file = $this->log_file . '.' . ($i - 1);
                $new_file = $this->log_file . '.' . $i;
                
                if (file_exists($old_file)) {
                    rename($old_file, $new_file);
                }
            }

            rename($this->log_file, $this->log_file . '.1');
            touch($this->log_file);
            chmod($this->log_file, 0644);
        }
    }
} 