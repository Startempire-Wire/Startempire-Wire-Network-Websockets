<?php
namespace SEWN\WebSockets;

class Ajax_Handler {
    public function __construct() {
        add_action('wp_ajax_sewn_ws_get_status', [$this, 'handle_status_request']);
        add_action('wp_ajax_nopriv_sewn_ws_get_status', [$this, 'handle_unauthenticated']);
    }

    public function handle_status_request() {
        check_ajax_referer(
            defined('SEWN_WS_NONCE_ACTION') ? SEWN_WS_NONCE_ACTION : 'sewn_ws_nonce',
            'nonce'
        );

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $status = get_option(
            defined('SEWN_WS_OPTION_SERVER_STATUS') ? SEWN_WS_OPTION_SERVER_STATUS : 'sewn_ws_server_status',
            'stopped'
        );

        wp_send_json_success([
            'status' => $status,
            'timestamp' => time(),
            'environment' => defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : 'production'
        ]);
    }

    public function handle_unauthenticated() {
        wp_send_json_error('Authentication required', 401);
    }
}

// Initialize in your plugin bootstrap file
new Ajax_Handler(); 