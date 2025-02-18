<?php
/**
 * Location: includes/
 * Dependencies: Node.js, system commands
 * Variables: $node_server_dir, $required_dependencies
 * Classes: Install_Handler
 * 
 * Manages server installation and configuration for WebSocket infrastructure. Automates dependency checks, environment setup, and configuration generation for cross-platform deployment.
 */
namespace SEWN\WebSockets;

/**
 * Handles plugin installation tasks including database table creation.
 */
class Install_Handler {
    private $node_server_dir;
    private $required_dependencies = [
        'node' => '16.0.0',
        'npm' => '7.0.0'
    ];

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->node_server_dir = SEWN_WS_PATH . 'node-server/';
        
        add_action('wp_ajax_sewn_ws_install_node', [$this, 'handle_install']);
        add_action('wp_ajax_sewn_ws_check_installation', [$this, 'handle_installation_check']);
        add_action('wp_ajax_sewn_ws_initialize_server', [$this, 'handle_server_initialization']);

        register_activation_hook(SEWN_WS_FILE, [$this, 'activate']);
        register_deactivation_hook(SEWN_WS_FILE, [$this, 'deactivate']);
    }

    /**
     * Activation handler.
     *
     * @since 1.0.0
     * @return void
     */
    public function activate(): void {
        $this->create_tables();
        $this->initialize_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Deactivation handler.
     *
     * @since 1.0.0
     * @return void
     */
    public function deactivate(): void {
        // Clear scheduled events
        wp_clear_scheduled_hook('sewn_ws_persist_stats');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create necessary database tables.
     *
     * @since 1.0.0
     * @return void
     */
    private function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        
        // Stats table
        $table_name = $wpdb->prefix . 'sewn_ws_stats';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            stats_data longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Initialize plugin options.
     *
     * @since 1.0.0
     * @return void
     */
    private function initialize_options(): void {
        // Add default options if they don't exist
        add_option('sewn_ws_version', '1.0.0');
        add_option('sewn_ws_stats_retention_days', 30);
        add_option('sewn_ws_buffer_size', 1000);
    }

    /**
     * Check if database tables exist.
     *
     * @since 1.0.0
     * @return bool True if tables exist, false otherwise.
     */
    public function check_tables(): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sewn_ws_stats';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        return $table_exists;
    }

    /**
     * Get plugin version.
     *
     * @since 1.0.0
     * @return string Plugin version.
     */
    public function get_version(): string {
        return get_option('sewn_ws_version', '1.0.0');
    }

    public function handle_install() {
        check_ajax_referer('sewn_ws_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $method = sanitize_text_field($_POST['method'] ?? '');
        
        try {
            switch($method) {
                case 'auto':
                    $output = $this->auto_install();
                    break;
                case 'docker':
                    $output = $this->docker_install();
                    break;
                case 'manual':
                    $output = $this->manual_guide();
                    break;
                default:
                    throw new \Exception('Invalid installation method');
            }
            
            wp_send_json_success($output);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }

    public function handle_installation_check() {
        check_ajax_referer('sewn_ws_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        try {
            $status = $this->check_dependencies();
            wp_send_json_success($status);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }

    public function handle_server_initialization() {
        check_ajax_referer('sewn_ws_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        try {
            // Verify dependencies before initialization
            $deps_status = $this->check_dependencies();
            if (!$deps_status['ready']) {
                throw new \Exception('Dependencies not met: ' . implode(', ', $deps_status['missing']));
            }

            $this->initialize_server();
            wp_send_json_success(['message' => 'Server initialized successfully']);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }

    private function auto_install() {
        if(defined('SEWN_WS_IS_LOCAL') && SEWN_WS_IS_LOCAL) {
            return [
                'success' => true,
                'message' => 'Local environment detected - manual install required',
                'next_step' => 'manual_setup'
            ];
        }
        
        if(!in_array(PHP_OS, ['Linux', 'Darwin'])) {
            throw new \Exception("Auto-install only available for Mac/Linux");
        }
        
        $commands = [
            'Darwin' => '/opt/homebrew/bin/brew install node@16',
            'Linux' => 'curl -fsSL https://deb.nodesource.com/setup_16.x | sudo -E bash - && sudo apt-get install -y nodejs'
        ];
        
        exec($commands[PHP_OS] . ' 2>&1', $output, $result);
        
        if ($result !== 0) {
            throw new \Exception('Installation failed: ' . implode("\n", $output));
        }

        return [
            'success' => true,
            'message' => 'Node.js installed successfully',
            'output' => $output,
            'next_step' => 'server_init'
        ];
    }

    private function docker_install() {
        try {
            $compose_file = $this->node_server_dir . 'docker-compose.yml';
            if (!file_exists($compose_file)) {
                throw new \Exception('Docker compose file not found');
            }

            exec('docker-compose -f ' . escapeshellarg($compose_file) . ' up -d 2>&1', $output, $result);
            
            if ($result !== 0) {
                throw new \Exception('Docker installation failed: ' . implode("\n", $output));
            }

            return [
                'success' => true,
                'message' => 'Docker container started successfully',
                'output' => $output,
                'next_step' => 'server_init'
            ];
        } catch (\Exception $e) {
            throw new \Exception('Docker installation failed: ' . $e->getMessage());
        }
    }

    private function manual_guide() {
        return [
            'success' => true,
            'message' => 'Manual installation guide',
            'steps' => [
                'Install Node.js v16 or higher from https://nodejs.org/',
                'Install required dependencies using npm install',
                'Configure the WebSocket server settings',
                'Initialize the server using the dashboard'
            ],
            'next_step' => 'verify_installation'
        ];
    }

    private function check_dependencies() {
        $status = [
            'ready' => true,
            'node' => false,
            'npm' => false,
            'missing' => [],
            'versions' => []
        ];

        // Check Node.js
        exec('node --version 2>&1', $node_output, $node_result);
        if ($node_result === 0) {
            $node_version = trim($node_output[0], 'v');
            $status['node'] = version_compare($node_version, $this->required_dependencies['node'], '>=');
            $status['versions']['node'] = $node_version;
        } else {
            $status['missing'][] = 'Node.js';
        }

        // Check npm
        exec('npm --version 2>&1', $npm_output, $npm_result);
        if ($npm_result === 0) {
            $npm_version = trim($npm_output[0]);
            $status['npm'] = version_compare($npm_version, $this->required_dependencies['npm'], '>=');
            $status['versions']['npm'] = $npm_version;
        } else {
            $status['missing'][] = 'npm';
        }

        $status['ready'] = $status['node'] && $status['npm'];

        return $status;
    }

    private function initialize_server() {
        // Ensure node_modules exists
        if (!is_dir($this->node_server_dir . 'node_modules')) {
            exec('cd ' . escapeshellarg($this->node_server_dir) . ' && npm install 2>&1', $output, $result);
            if ($result !== 0) {
                throw new \Exception('Failed to install dependencies: ' . implode("\n", $output));
            }
        }

        // Create necessary directories
        $dirs = ['logs', 'config'];
        foreach ($dirs as $dir) {
            $path = $this->node_server_dir . $dir;
            if (!is_dir($path) && !mkdir($path, 0755, true)) {
                throw new \Exception("Failed to create directory: $dir");
            }
        }

        // Generate configuration
        $this->generate_config();

        return true;
    }

    private function generate_config() {
        $config = [
            'port' => get_option('sewn_ws_port', 3000),
            'host' => get_option('sewn_ws_host', '0.0.0.0'),
            'ssl' => get_option('sewn_ws_ssl', false),
            'logLevel' => WP_DEBUG ? 'debug' : 'info',
            'allowedOrigins' => $this->get_allowed_origins()
        ];

        $config_file = $this->node_server_dir . 'config/server.json';
        if (!file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT))) {
            throw new \Exception('Failed to write configuration file');
        }

        return true;
    }

    private function get_allowed_origins() {
        $origins = [home_url()];
        
        // Add any additional allowed origins from settings
        $additional_origins = get_option('sewn_ws_allowed_origins', '');
        if (!empty($additional_origins)) {
            $origins = array_merge($origins, array_map('trim', explode("\n", $additional_origins)));
        }

        return array_unique(array_filter($origins));
    }
} 