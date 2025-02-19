/**
 * Enqueue admin scripts and styles
 */
public function enqueue_admin_assets() {
    $screen = get_current_screen();
    if ($screen->id !== 'toplevel_page_sewn-ws-dashboard') {
        return;
    }

    // Enqueue Chart.js
    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js',
        array(),
        '3.7.0',
        true
    );

    // Enqueue Socket.IO client
    wp_enqueue_script(
        'socket-io-client',
        'https://cdn.socket.io/4.5.4/socket.io.min.js',
        array(),
        '4.5.4',
        true
    );

    // Enqueue our dashboard script
    wp_enqueue_script(
        'sewn-ws-dashboard',
        plugin_dir_url(__FILE__) . 'js/dashboard.js',
        array('jquery', 'chartjs', 'socket-io-client'),
        $this->version,
        true
    );

    // Enqueue dashboard styles
    wp_enqueue_style(
        'sewn-ws-dashboard',
        plugin_dir_url(__FILE__) . 'css/dashboard.css',
        array(),
        $this->version
    );

    // Localize script with server settings
    wp_localize_script(
        'sewn-ws-dashboard',
        'sewnWsSettings',
        array(
            'wsPort' => 49200,
            'nonce' => wp_create_nonce('sewn_ws_dashboard'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n' => array(
                'connected' => __('Connected', 'sewn-ws'),
                'disconnected' => __('Disconnected', 'sewn-ws'),
                'error' => __('Error', 'sewn-ws'),
                'success' => __('Success', 'sewn-ws'),
                'serverStarted' => __('Server started successfully', 'sewn-ws'),
                'serverStopped' => __('Server stopped successfully', 'sewn-ws'),
                'serverError' => __('Server error occurred', 'sewn-ws'),
            )
        )
    );
}

/**
 * Add admin menu pages
 */
public function add_admin_menu() {
    add_menu_page(
        __('WebSocket Server', 'sewn-ws'),
        __('WebSocket Server', 'sewn-ws'),
        'manage_options',
        'sewn-ws-dashboard',
        array($this, 'render_dashboard_page'),
        'dashicons-rss',
        30
    );
}

/**
 * Render the dashboard page
 */
public function render_dashboard_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Get current server status
    $node_status = $this->get_node_status();
    $status_class = $node_status['running'] ? 'running' : 'stopped';
    $status_text = $node_status['running'] ? __('Running', 'sewn-ws') : __('Stopped', 'sewn-ws');

    // Include the dashboard template
    include_once plugin_dir_path(__FILE__) . 'views/dashboard.php';
}

/**
 * Get the current Node.js server status
 */
private function get_node_status() {
    $pid_file = $this->plugin->get_node_server_dir() . '/server.pid';
    $stats_file = $this->plugin->get_node_server_dir() . '/stats.json';
    
    $status = array(
        'running' => false,
        'pid' => null,
        'uptime' => 0,
        'memory' => 0,
        'connections' => 0,
        'messages' => 0,
        'errors' => 0
    );

    // Check if PID file exists and process is running
    if (file_exists($pid_file)) {
        $pid = trim(file_get_contents($pid_file));
        if ($pid && $this->is_process_running($pid)) {
            $status['running'] = true;
            $status['pid'] = $pid;
        }
    }

    // Get stats if available
    if (file_exists($stats_file)) {
        $stats = json_decode(file_get_contents($stats_file), true);
        if ($stats) {
            $status = array_merge($status, $stats);
        }
    }

    return $status;
}

/**
 * Check if a process is running
 */
private function is_process_running($pid) {
    if (empty($pid)) {
        return false;
    }

    // Check process on Unix-like systems
    if (function_exists('posix_kill')) {
        return posix_kill($pid, 0);
    }

    // Fallback for Windows
    if (PHP_OS_FAMILY === 'Windows') {
        $output = array();
        exec("tasklist /FI \"PID eq $pid\"", $output);
        return count($output) > 1;
    }

    return false;
} 