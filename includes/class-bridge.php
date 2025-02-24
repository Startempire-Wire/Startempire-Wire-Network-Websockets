<?php
/**
 * Location: includes/
 * Dependencies: Auth class, WordPress Filesystem API, Firebase JWT
 * Variables: $socket_file, $auth
 * Classes: Bridge
 * 
 * Acts as communication bridge between WordPress and Node.js WebSocket server. Manages Unix socket communication, message broadcasting, and connection logging for real-time data exchange.
 */
namespace SEWN\WebSockets;

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Bridge {
    private $socket_file;
    private $auth;

    public function __construct() {
        $this->socket_file = WP_CONTENT_DIR . '/sewn-ws.sock';
        $this->auth = new Auth();
    }

    public function initialize() {
        add_action('wp_ajax_sewn_ws_authenticate', [$this, 'handle_authentication']);
        add_action('wp_ajax_sewn_ws_broadcast', [$this, 'handle_broadcast']);
        add_action('sewn_ws_client_connect', [$this, 'log_connection']);
        add_action('sewn_ws_client_disconnect', [$this, 'log_disconnection']);
    }

    public function handle_authentication() {
        check_ajax_referer(\SEWN_WS_NONCE_ACTION);
        
        try {
            // Verify admin status
            if (!current_user_can('manage_options')) {
                throw new \Exception('Unauthorized: Admin privileges required');
            }

            $user_id = get_current_user_id();
            
            // Initialize WP Auth Bridge
            $auth_bridge = new SEWN_WS_WP_Auth();
            
            // Generate admin token
            $token = $auth_bridge->generate_socket_token($user_id);
            
            wp_send_json_success([
                'token' => $token,
                'user_id' => $user_id,
                'expires' => time() + HOUR_IN_SECONDS,
                'isAdmin' => true
            ]);
        } catch (\Exception $e) {
            Error_Handler::log_error($e);
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function handle_broadcast() {
        check_ajax_referer(\SEWN_WS_NONCE_ACTION);
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $message = sanitize_text_field($_POST['message'] ?? '');
        $channel = sanitize_text_field($_POST['channel'] ?? 'global');

        try {
            $this->send_to_socket_server('broadcast', [
                'message' => $message,
                'channel' => $channel
            ]);
            wp_send_json_success();
        } catch (\Exception $e) {
            Error_Handler::log_error($e);
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function log_connection($client_id) {
        // Log connection to WordPress
        do_action('sewn_ws_log_event', 'connection', [
            'client_id' => $client_id,
            'timestamp' => current_time('mysql')
        ]);
    }

    public function log_disconnection($client_id) {
        // Log disconnection to WordPress
        do_action('sewn_ws_log_event', 'disconnection', [
            'client_id' => $client_id,
            'timestamp' => current_time('mysql')
        ]);
    }

    private function send_to_socket_server($event, $data) {
        if (!file_exists($this->socket_file)) {
            throw new \Exception('Socket server not running');
        }

        $socket = stream_socket_client("unix://{$this->socket_file}");
        if (!$socket) {
            throw new \Exception('Failed to connect to socket server');
        }

        $payload = json_encode([
            'event' => $event,
            'data' => $data,
            'timestamp' => time()
        ]);

        fwrite($socket, $payload . "\n");
        $response = fgets($socket);
        fclose($socket);

        return json_decode($response, true);
    }

    public function validate_token($token) {
        try {
            // Initialize WP Auth Bridge
            $auth_bridge = new SEWN_WS_WP_Auth();
            
            // Decode and validate the token
            $decoded = JWT::decode($token, new Key(SECURE_AUTH_KEY, 'HS256'));
            
            // Verify token is not expired
            if ($decoded->exp < time()) {
                throw new \Exception('Token has expired');
            }
            
            // Verify admin status for admin namespace
            if ($decoded->isAdmin && !current_user_can('manage_options')) {
                throw new \Exception('Invalid admin privileges');
            }
            
            return [
                'valid' => true,
                'user_id' => $decoded->wp_user,
                'isAdmin' => $decoded->isAdmin,
                'tier' => $decoded->tier,
                'capabilities' => $decoded->capabilities
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }
} 