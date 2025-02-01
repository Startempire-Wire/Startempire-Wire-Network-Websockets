<?php
namespace SEWN\WebSockets;

class Process_Manager {
    const NAMESPACE = 'sewn-ws';
    
    public static function start_server() {
        $command = 'cd ' . escapeshellarg(SEWN_WS_NODE_SERVER) . 
                 ' && npm start -- --port=' . SEWN_WS_PORT;
        exec('pm2 start ' . escapeshellarg($command) . ' --name ' . self::NAMESPACE);
    }
    
    public static function get_active_connections() {
        return apply_filters('sewn_ws_active_connections', []);
    }

    public static function activate() {
        try {
            // 1. Server config
            self::create_default_config();
            
            // 2. Dependency checks
            if(!self::check_node_version()) {
                wp_die('Node.js 16.x or higher required');
            }
            
            // 3. Directory setup
            self::create_log_directory();
            
            // 4. Cron jobs
            if(!wp_next_scheduled('sewn_ws_health_check')) {
                wp_schedule_event(time(), 'hourly', 'sewn_ws_health_check');
            }
            
            // 5. Default options
            update_option('sewn_ws_port', 8080);
            update_option('sewn_ws_tls_enabled', false);
            update_option('sewn_ws_rate_limits', [
                'free' => 10,
                'freewire' => 30,
                'wire' => 100,
                'extrawire' => 500
            ]);

            // Sync VM timezone with host
            date_default_timezone_set('America/Los_Angeles'); // Match your Local config
            shell_exec('ln -sf /usr/share/zoneinfo/America/Los_Angeles /etc/localtime');
        } catch(Throwable $e) {
            update_option('sewn_ws_activation_error', $e->getMessage());
        }
    }
    
    public static function deactivate() {
        exec('pm2 stop ' . self::NAMESPACE);
    }

    private static function check_node_version() {
        exec('node -v', $output, $result);
        if($result !== 0) return false;
        
        $version = trim(str_replace('v', '', $output[0]));
        return version_compare($version, '16.0.0', '>=');
    }

    private static function create_log_directory() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/sewn-ws-logs/';
        
        if(!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            file_put_contents($log_dir . '.htaccess', 'Deny from all');
        }
    }

    private static function create_default_config() {
        $config = [
            'port' => get_option('sewn_ws_port', 8080),
            'tls' => [
                'enabled' => false,
                'cert' => '',
                'key' => ''
            ],
            'origins' => [
                home_url(),
                'chrome-extension://your-extension-id'
            ]
        ];
        
        file_put_contents(SEWN_WS_NODE_SERVER . 'config.json', json_encode($config));
    }
}

// Add to admin notices
add_action('admin_notices', function() {
    if($error = get_option('sewn_ws_activation_error')) {
        echo '<div class="notice notice-error">';
        echo '<pre>Activation Error: ' . esc_html($error) . '</pre>';
        echo '</div>';
        delete_option('sewn_ws_activation_error');
    }
});
