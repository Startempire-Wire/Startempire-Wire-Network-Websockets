<?php
/**
 * Location: includes/
 * Dependencies: WordPress Filesystem API
 * Variables: LOG_FILE, MAX_ENTRIES
 * Classes: Error_Handler
 * 
 * Manages error logging and diagnostics for WebSocket server operations. Provides structured error storage, retrieval, and context-aware exception handling for system monitoring and debugging.
 */
namespace SEWN\WebSockets;

use SEWN\WebSockets\Exception;

class Error_Handler {
    const LOG_FILE = 'sewn-ws-errors.log';
    const MAX_ENTRIES = 50;

    public static function get_recent_errors() {
        $log_path = WP_CONTENT_DIR . '/' . self::LOG_FILE;
        
        if (!file_exists($log_path)) {
            return [];
        }

        $entries = file($log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $entries = array_slice($entries, -self::MAX_ENTRIES);
        
        return array_map(function($entry) {
            return json_decode($entry, true) ?? ['raw' => $entry];
        }, $entries);
    }

    public static function log_error(\Throwable $e) {
        $entry = [
            'timestamp' => current_time('mysql'),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTrace()
        ];

        if ($e instanceof Exception) {
            $entry['context'] = $e->getContext();
        }

        file_put_contents(
            WP_CONTENT_DIR . '/' . self::LOG_FILE,
            json_encode($entry) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
} 