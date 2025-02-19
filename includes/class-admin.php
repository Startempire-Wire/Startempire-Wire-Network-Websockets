/**
 * Register AJAX handlers
 */
public function register_ajax_handlers() {
    add_action('wp_ajax_sewn_ws_check_server_status', [$this, 'handle_check_server_status']);
}

/**
 * Handle server status check AJAX request
 */
public function handle_check_server_status() {
    check_ajax_referer('sewn_ws_admin', 'nonce');

    $process_manager = new Process_Manager();
    $status = $process_manager->get_server_status();

    wp_send_json_success([
        'status' => $status['running'] ? 'running' : 'stopped',
        'pid' => $status['pid'] ?? null,
        'uptime' => $status['uptime'] ?? 0
    ]);
} 