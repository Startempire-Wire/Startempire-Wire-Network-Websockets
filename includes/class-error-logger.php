<?php
/**
 * Error Logger Class
 *
 * Handles logging functionality for the WebSocket plugin including debug logs,
 * error tracking, and log management.
 *
 * @package SEWN\WebSockets
 */

namespace SEWN\WebSockets;

if (!defined('ABSPATH')) exit;

class Error_Logger {
    /**
     * Singleton instance
     *
     * @var Error_Logger
     */
    private static $instance = null;

    /**
     * Log file path
     *
     * @var string
     */
    private $log_file;

    /**
     * Maximum log file size in bytes (5MB)
     *
     * @var int
     */
    private const MAX_LOG_SIZE = 5242880;

    /**
     * Maximum number of backup log files
     *
     * @var int
     */
    private const MAX_BACKUP_FILES = 5;

    /**
     * Get singleton instance
     *
     * @return Error_Logger
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = trailingslashit($upload_dir['basedir']) . 'sewn-ws-debug.log';
        
        // Create log file if it doesn't exist
        if (!file_exists($this->log_file)) {
            touch($this->log_file);
        }
    }

    /**
     * Log a message
     *
     * @param string $message The message to log
     * @param string $level The log level (error, warning, info)
     * @param array $context Additional context data
     * @return bool Whether the message was logged successfully
     */
    public function log($message, $level = 'info', $context = array()) {
        if (!$this->is_logging_enabled()) {
            return false;
        }

        $this->rotate_logs();

        $timestamp = current_time('mysql');
        $formatted_message = sprintf(
            "[%s] [%s] %s %s\n",
            $timestamp,
            strtoupper($level),
            $message,
            !empty($context) ? json_encode($context) : ''
        );

        return file_put_contents($this->log_file, $formatted_message, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * Get log entries with optional filtering
     *
     * @param array $filters Array of filters (level, start_date, end_date)
     * @param int $limit Maximum number of entries to return
     * @param int $offset Offset for pagination
     * @return array Array of log entries
     */
    public function get_logs($filters = array(), $limit = 100, $offset = 0) {
        if (!file_exists($this->log_file)) {
            return array();
        }

        $logs = array();
        $handle = fopen($this->log_file, 'r');
        if ($handle) {
            $count = 0;
            $current_offset = 0;

            while (($line = fgets($handle)) !== false) {
                if ($this->matches_filters($line, $filters)) {
                    if ($current_offset >= $offset) {
                        $logs[] = $this->parse_log_line($line);
                        $count++;

                        if ($count >= $limit) {
                            break;
                        }
                    }
                    $current_offset++;
                }
            }

            fclose($handle);
        }

        return $logs;
    }

    /**
     * Clear all logs
     *
     * @return bool Whether the logs were cleared successfully
     */
    public function clear_logs() {
        if (!file_exists($this->log_file)) {
            return true;
        }

        return file_put_contents($this->log_file, '') !== false;
    }

    /**
     * Export logs as a downloadable file
     *
     * @param string $format Export format (txt, json, csv)
     * @return string|bool Path to export file or false on failure
     */
    public function export_logs($format = 'txt') {
        if (!file_exists($this->log_file)) {
            return false;
        }

        $logs = $this->get_logs();
        if (empty($logs)) {
            return false;
        }

        $export_dir = wp_upload_dir()['basedir'] . '/sewn-ws-exports';
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }

        $filename = sprintf('sewn-ws-logs-%s.%s', date('Y-m-d-His'), $format);
        $export_file = $export_dir . '/' . $filename;

        switch ($format) {
            case 'json':
                $success = file_put_contents($export_file, json_encode($logs, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $fp = fopen($export_file, 'w');
                if ($fp) {
                    fputcsv($fp, array('Timestamp', 'Level', 'Message', 'Context'));
                    foreach ($logs as $log) {
                        fputcsv($fp, array(
                            $log['timestamp'],
                            $log['level'],
                            $log['message'],
                            json_encode($log['context'])
                        ));
                    }
                    fclose($fp);
                    $success = true;
                }
                break;
            default:
                $success = copy($this->log_file, $export_file);
        }

        return $success ? $export_file : false;
    }

    /**
     * Check if logging is enabled
     *
     * @return bool
     */
    private function is_logging_enabled() {
        return get_option('sewn_ws_debug_enabled', false);
    }

    /**
     * Rotate log files if size limit is reached
     */
    private function rotate_logs() {
        if (!file_exists($this->log_file)) {
            return;
        }

        $size = filesize($this->log_file);
        if ($size < self::MAX_LOG_SIZE) {
            return;
        }

        // Rotate existing backup files
        for ($i = self::MAX_BACKUP_FILES - 1; $i >= 1; $i--) {
            $current = $this->log_file . '.' . $i;
            $next = $this->log_file . '.' . ($i + 1);
            
            if (file_exists($current)) {
                rename($current, $next);
            }
        }

        // Move current log to .1
        rename($this->log_file, $this->log_file . '.1');
        touch($this->log_file);
    }

    /**
     * Parse a log line into structured data
     *
     * @param string $line Log line to parse
     * @return array Structured log data
     */
    private function parse_log_line($line) {
        preg_match('/\[(.*?)\]\s*\[(.*?)\]\s*(.*?)(?:\s+(\{.*\}))?\s*$/', $line, $matches);

        return array(
            'timestamp' => isset($matches[1]) ? $matches[1] : '',
            'level' => isset($matches[2]) ? strtolower($matches[2]) : '',
            'message' => isset($matches[3]) ? trim($matches[3]) : '',
            'context' => isset($matches[4]) ? json_decode($matches[4], true) : array()
        );
    }

    /**
     * Check if a log line matches the given filters
     *
     * @param string $line Log line to check
     * @param array $filters Filters to apply
     * @return bool Whether the line matches the filters
     */
    private function matches_filters($line, $filters) {
        $log_data = $this->parse_log_line($line);

        if (isset($filters['level']) && $log_data['level'] !== strtolower($filters['level'])) {
            return false;
        }

        if (isset($filters['start_date'])) {
            $log_time = strtotime($log_data['timestamp']);
            $start_time = strtotime($filters['start_date']);
            if ($log_time < $start_time) {
                return false;
            }
        }

        if (isset($filters['end_date'])) {
            $log_time = strtotime($log_data['timestamp']);
            $end_time = strtotime($filters['end_date']);
            if ($log_time > $end_time) {
                return false;
            }
        }

        return true;
    }

    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the instance
     */
    private function __wakeup() {}
} 