<?php
/**
 * Location: includes/class-socket-manager.php
 * Dependencies: WordPress transients, server status checks
 * Classes: Socket_Manager
 * 
 * Orchestrates WebSocket connection validation and failure recovery mechanisms. Implements retry logic and server availability checks to maintain network reliability.
 */

namespace SEWN\WebSockets;

use SEWN\WebSockets\Process_Manager;
use SEWN\WebSockets\Server_Status;

// Define plugin constants if not already defined
if (!defined('SEWN_WS_PLUGIN_DIR')) {
    define('SEWN_WS_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
}

if (!defined('SEWN_WS_PLUGIN_URL')) {
    define('SEWN_WS_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));
}

if (!defined('SEWN_WS_OPTION_NODE_PATH')) {
    define('SEWN_WS_OPTION_NODE_PATH', 'sewn_ws_node_path');
}

class Socket_Manager {
    /**
     * @var Socket_Manager|null Instance of this class.
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Socket_Manager
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
        // Constructor logic here
    }

    /**
     * Initialize the manager
     *
     * @return void
     */
    public static function init() {
        $instance = self::get_instance();
        
        add_action('admin_post_sewn_ws_start_server', [$instance, 'handle_start_server']);
        add_action('admin_post_sewn_ws_stop_server', [$instance, 'handle_stop_server']);
        add_action('admin_enqueue_scripts', [$instance, 'enqueue_admin_scripts']);
        
        // Initialize server status
        if (get_option(SEWN_WS_OPTION_SERVER_STATUS) === false) {
            update_option(SEWN_WS_OPTION_SERVER_STATUS, SEWN_WS_SERVER_STATUS_STOPPED);
        }
    }

    /**
     * Enqueue admin scripts and initialize configuration
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'sewn-websockets') === false) {
            return;
        }

        // Enqueue Socket.IO client
        wp_enqueue_script(
            'socket.io-client',
            'https://cdn.socket.io/4.5.4/socket.io.min.js',
            [],
            '4.5.4',
            true
        );

        // Enqueue admin script
        wp_enqueue_script(
            'sewn-ws-admin',
            SEWN_WS_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery', 'socket.io-client'],
            SEWN_WS_VERSION,
            true
        );

        // Get server configuration
        $port = get_option(SEWN_WS_OPTION_PORT, SEWN_WS_DEFAULT_PORT);
        $is_ssl = is_ssl();
        $host = parse_url(get_site_url(), PHP_URL_HOST);
        
        // Check if we're in a local development environment
        $is_local_dev = (
            strpos($host, '.local') !== false || 
            strpos($host, '.test') !== false || 
            in_array($host, ['localhost', '127.0.0.1'])
        );

        // Always use WS for local development
        $ws_protocol = $is_local_dev ? 'ws' : ($is_ssl ? 'wss' : 'ws');

        // Initialize configuration
        $config = [
            'url' => sprintf(
                '%s://%s:%d',
                $ws_protocol,
                $host,
                $port
            ),
            'isLocalDev' => $is_local_dev,
            'pageProtocol' => $is_ssl ? 'https:' : 'http:',
            'wsProtocol' => $ws_protocol . ':',
            'token' => wp_create_nonce('sewn_ws_socket_auth'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'maxReconnectAttempts' => 5,
            'reconnectDelay' => 1000,
            'timeout' => 5000
        ];

        // Localize script
        wp_localize_script('sewn-ws-admin', 'SEWN_WS_CONFIG', $config);
        wp_localize_script('sewn-ws-admin', 'sewn_ws_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sewn_ws_admin'),
            'i18n' => [
                'connecting' => __('Connecting...', 'sewn-ws'),
                'connected' => __('Connected', 'sewn-ws'),
                'disconnected' => __('Disconnected', 'sewn-ws'),
                'error' => __('Error', 'sewn-ws'),
                'retry' => __('Retrying...', 'sewn-ws')
            ]
        ]);
    }

    /**
     * Handle server start request
     */
    public function handle_start_server() {
        check_admin_referer(SEWN_WS_NONCE_ACTION);
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', SEWN_WS_TEXT_DOMAIN));
        }

        $result = $this->start_server();
        $status = $result ? SEWN_WS_SERVER_STATUS_RUNNING : SEWN_WS_SERVER_STATUS_ERROR;
        
        update_option(SEWN_WS_OPTION_SERVER_STATUS, $status);
        
        wp_redirect(add_query_arg([
            'page' => SEWN_WS_ADMIN_MENU_SLUG,
            'status' => $status
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Check if the server is running and get its status
     * 
     * @return array Server status information
     */
    public function get_server_status() {
        $process_manager = Process_Manager::get_instance();
        $server_status = Server_Status::get_instance();
        
        // Get comprehensive server health status
        $health = $server_status->get_server_health();
        
        // If server is not running, try to detect external process
        if (!$health['running']) {
            $port = get_option(SEWN_WS_OPTION_PORT, SEWN_WS_DEFAULT_PORT);
            $pid = $process_manager->detect_running_server();
            
            if ($pid) {
                // Update PID file and process status
                $process_manager->update_process_status($pid);
                
                // Get updated health status
                $health = $server_status->get_server_health();
                $health['external'] = true;
            }
        }
        
        return $health;
    }

    /**
     * Start the WebSocket server
     * 
     * @return bool True if server started successfully, false otherwise
     */
    public function start_server() {
        $process_manager = Process_Manager::get_instance();
        
        // Check if server is already running
        if ($this->get_server_status()['running']) {
            return true;
        }
        
        // Get server configuration
        $port = get_option(SEWN_WS_OPTION_PORT, SEWN_WS_DEFAULT_PORT);
        $node_path = get_option(SEWN_WS_OPTION_NODE_PATH, 'node');
        $server_script = SEWN_WS_PLUGIN_DIR . 'node-server/server.js';
        
        // Build command with environment variables
        $command = sprintf(
            '%s %s --port=%d --plugin-dir=%s --wp-content-dir=%s 2>&1',
            escapeshellcmd($node_path),
            escapeshellarg($server_script),
            intval($port),
            escapeshellarg(SEWN_WS_PLUGIN_DIR),
            escapeshellarg(WP_CONTENT_DIR)
        );
        
        // Start server process
        $pid = $process_manager->start_process($command);
        
        if (!$pid) {
            error_log('Failed to start WebSocket server');
            return false;
        }
        
        // Wait for server to start
        $start_time = time();
        $timeout = 30; // 30 seconds timeout
        
        while (time() - $start_time < $timeout) {
            $status = $this->get_server_status();
            if ($status['running']) {
                return true;
            }
            usleep(100000); // Sleep for 100ms
        }
        
        error_log('WebSocket server failed to start within timeout period');
        return false;
    }

    /**
     * Stop the WebSocket server
     * 
     * @return bool True if server stopped successfully, false otherwise
     */
    public function stop_server() {
        $process_manager = Process_Manager::get_instance();
        $status = $this->get_server_status();
        
        if (!$status['running']) {
            return true;
        }
        
        // If external process, try to stop it
        if (!empty($status['external'])) {
            $port = get_option(SEWN_WS_OPTION_PORT, SEWN_WS_DEFAULT_PORT);
            $pid = $process_manager->detect_running_server();
            
            if ($pid) {
                return $process_manager->kill_process($pid);
            }
            
            return false;
        }
        
        // Stop managed process
        return $process_manager->kill_current_process();
    }

    /**
     * Handle AJAX request to check server status
     */
    public function ajax_check_server_status() {
        check_ajax_referer('sewn_ws_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $status = $this->get_server_status();
        wp_send_json($status);
    }

    /**
     * Handle AJAX request to start server
     */
    public function ajax_start_server() {
        check_ajax_referer('sewn_ws_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $result = $this->start_server();
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to start server');
        }
    }

    /**
     * Handle AJAX request to stop server
     */
    public function ajax_stop_server() {
        check_ajax_referer('sewn_ws_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $result = $this->stop_server();
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to stop server');
        }
    }
} 