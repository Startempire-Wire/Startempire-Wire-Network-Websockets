<?php
/**
 * Location: includes/class-socket-manager.php
 * Dependencies: WordPress transients, server status checks
 * Classes: Socket_Manager
 * 
 * Orchestrates WebSocket connection validation and failure recovery mechanisms. Implements retry logic and server availability checks to maintain network reliability.
 */

namespace SEWN\WebSockets;

defined('ABSPATH') || exit;

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
        
        // Initialize server status
        if (get_option(SEWN_WS_OPTION_SERVER_STATUS) === false) {
            update_option(SEWN_WS_OPTION_SERVER_STATUS, SEWN_WS_SERVER_STATUS_STOPPED);
        }
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
     * Start the WebSocket server
     *
     * @return bool
     */
    private function start_server() {
        if ($this->is_server_running()) {
            return true;
        }

        $command = sprintf(
            'cd %s && node server.js --port=%d > %sserver.log 2>&1 & echo $!',
            SEWN_WS_NODE_SERVER,
            get_option(SEWN_WS_OPTION_PORT, SEWN_WS_DEFAULT_PORT),
            SEWN_WS_NODE_SERVER
        );

        $pid = exec($command, $output, $return_var);
        
        if ($return_var !== 0) {
            error_log('WebSocket server start failed: ' . implode("\n", $output));
            return false;
        }

        return $this->verify_server_running();
    }

    /**
     * Check if server is running
     *
     * @return bool
     */
    public function is_server_running() {
        $port = get_option(SEWN_WS_OPTION_PORT, SEWN_WS_DEFAULT_PORT);
        $connection = @fsockopen('localhost', $port, $errno, $errstr, 1);
        
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        
        return false;
    }

    /**
     * Verify server is running and responding
     *
     * @return bool
     */
    private function verify_server_running() {
        $retry_count = 0;
        $max_retries = 5;

        while ($retry_count < $max_retries) {
            if ($this->is_server_running()) {
                return true;
            }
            $retry_count++;
            sleep(1);
        }

        return false;
    }
} 