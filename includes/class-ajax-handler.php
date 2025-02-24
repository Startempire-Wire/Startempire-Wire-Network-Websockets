<?php
/**
 * Location: includes/
 * Dependencies: WordPress AJAX API
 * Variables: None
 * Classes: Ajax_Handler
 * 
 * Manages AJAX endpoints for WebSocket server status checks and admin interactions. Provides secure status reporting through WordPress nonce verification and capability checks for administrative users.
 */
namespace SEWN\WebSockets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AJAX_Handler
 */
class AJAX_Handler {

    /**
     * Initialize the AJAX handler
     */
    public static function init() {
        add_action('wp_ajax_sewn_ws_refresh_nonce', [__CLASS__, 'refresh_nonce']);
        add_action('wp_ajax_sewn_ws_check_server_status', [__CLASS__, 'check_server_status']);
    }

    /**
     * Refresh the nonce
     */
    public static function refresh_nonce() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        wp_send_json_success([
            'nonce' => wp_create_nonce(SEWN_WS_NONCE_ACTION)
        ]);
    }

    /**
     * Check server status
     */
    public static function check_server_status() {
        if (!check_ajax_referer(\SEWN_WS_NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $server_status = new Server_Status();
        $status = $server_status->get_status();
        
        wp_send_json_success([
            'running' => $status['running'],
            'pid' => $status['pid'],
            'port' => $status['port'],
            'uptime' => $status['uptime'] ?? 0,
            'connections' => $status['connections'] ?? 0
        ]);
    }
}

// Initialize the AJAX handler
AJAX_Handler::init(); 