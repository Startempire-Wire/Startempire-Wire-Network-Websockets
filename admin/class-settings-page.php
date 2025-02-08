<?php

/**
 * LOCATION: admin/class-settings-page.php
 * DEPENDENCIES: WordPress Settings API
 * VARIABLES: None (singleton pattern)
 * CLASSES: Settings_Page (settings UI renderer)
 * 
 * Handles core WebSocket server configuration including port settings, TLS certificates, and rate limiting rules.
 * Integrates with network authentication providers (WordPress, BuddyBoss, Discord) to enforce membership-tier access
 * controls across distributed network components.
 */

namespace SEWN\WebSockets\Admin;

/**
 * Class Settings_Page
 * Handles the plugin settings page in WordPress admin
 */
class Settings_Page {
    private static $instance = null;

    /**
     * Error logger instance
     *
     * @var Error_Logger
     */
    private $logger;

    /**
     * Environment monitor instance
     *
     * @var Environment_Monitor
     */
    private $monitor;

    /**
     * Plugin options
     *
     * @var array
     */
    private $options;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = Error_Logger::get_instance();
        $this->monitor = Environment_Monitor::get_instance();
        $this->options = get_option('sewn_ws_settings', array());

        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'display_environment_notice'));
    }

    /**
     * Add options page
     */
    public function add_plugin_page() {
        add_options_page(
            __('WebSocket Server Settings', 'sewn-ws'),
            __('WebSocket Server', 'sewn-ws'),
            'manage_options',
            'websocket-server-settings',
            array($this, 'create_admin_page')
        );
    }

    /**
     * Display environment notice
     */
    public function display_environment_notice() {
        $screen = get_current_screen();
        if ($screen->id !== 'settings_page_websocket-server-settings') {
            return;
        }

        $status = $this->monitor->get_environment_status();
        $class = $status['is_local'] ? 'notice-info' : 'notice-warning';
        $env_type = $status['is_local'] ? __('Local', 'sewn-ws') : __('Production', 'sewn-ws');
        
        ?>
        <div class="notice <?php echo esc_attr($class); ?> is-dismissible">
            <p>
                <strong><?php echo esc_html(sprintf(__('Current Environment: %s', 'sewn-ws'), $env_type)); ?></strong>
            </p>
            <div class="sewn-ws-environment-details">
                <p>
                    <?php if ($status['is_local']) : ?>
                        <?php esc_html_e('Local development mode is active. Some features may be modified for development purposes.', 'sewn-ws'); ?>
                    <?php else : ?>
                        <?php esc_html_e('Production environment detected. All security measures are active.', 'sewn-ws'); ?>
                    <?php endif; ?>
                </p>
                <ul>
                    <li>
                        <span class="dashicons <?php echo $status['container_mode'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
                        <?php esc_html_e('Container Mode:', 'sewn-ws'); ?>
                        <?php echo $status['container_mode'] ? esc_html__('Active', 'sewn-ws') : esc_html__('Inactive', 'sewn-ws'); ?>
                    </li>
                    <li>
                        <span class="dashicons <?php echo $status['ssl_valid'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
                        <?php esc_html_e('SSL Certificate:', 'sewn-ws'); ?>
                        <?php echo $status['ssl_valid'] ? esc_html__('Valid', 'sewn-ws') : esc_html__('Not Found/Invalid', 'sewn-ws'); ?>
                    </li>
                    <li>
                        <span class="dashicons <?php echo $status['system_ready'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
                        <?php esc_html_e('System Requirements:', 'sewn-ws'); ?>
                        <?php echo $status['system_ready'] ? esc_html__('Met', 'sewn-ws') : esc_html__('Not Met', 'sewn-ws'); ?>
                    </li>
                </ul>
                <p class="description">
                    <?php
                    printf(
                        /* translators: %s: time difference */
                        esc_html__('Last checked: %s ago', 'sewn-ws'),
                        human_time_diff($status['last_check'], time())
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_websocket-server-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'sewn-ws-admin',
            plugin_dir_url(__FILE__) . 'css/admin.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_style(
            'sewn-ws-debug-panel',
            plugin_dir_url(__FILE__) . 'css/debug-panel.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'sewn-ws-admin',
            plugin_dir_url(__FILE__) . 'js/admin.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_enqueue_script(
            'sewn-ws-debug-panel',
            plugin_dir_url(__FILE__) . 'js/debug-panel.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script(
            'sewn-ws-admin',
            'sewnWsAdmin',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sewn_ws_admin'),
                'i18n' => array(
                    'checking' => __('Checking environment...', 'sewn-ws'),
                    'success' => __('Environment check completed', 'sewn-ws'),
                    'error' => __('Failed to check environment', 'sewn-ws'),
                    'confirmClearLogs' => __('Are you sure you want to clear all logs?', 'sewn-ws'),
                ),
            )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page() {
        $this->options = get_option('sewn_ws_settings');
        $environment = $this->monitor->check_environment();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="sewn-ws-environment-panel">
                <h2><?php esc_html_e('Environment Information', 'sewn-ws'); ?></h2>
                <div class="sewn-ws-environment-info">
                    <button type="button" class="button button-secondary" id="check-environment">
                        <?php esc_html_e('Check Environment', 'sewn-ws'); ?>
                    </button>
                    <div class="environment-status">
                        <h3><?php esc_html_e('Server Information', 'sewn-ws'); ?></h3>
                        <ul>
                            <li>
                                <strong><?php esc_html_e('Software:', 'sewn-ws'); ?></strong>
                                <?php echo esc_html($environment['server_info']['software']); ?>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Operating System:', 'sewn-ws'); ?></strong>
                                <?php echo esc_html($environment['server_info']['os']); ?>
                                (<?php echo esc_html($environment['server_info']['architecture']); ?>)
                            </li>
                        </ul>

                        <h3><?php esc_html_e('PHP Information', 'sewn-ws'); ?></h3>
                        <ul>
                            <li>
                                <strong><?php esc_html_e('Version:', 'sewn-ws'); ?></strong>
                                <?php echo esc_html($environment['php_info']['version']); ?>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Memory Limit:', 'sewn-ws'); ?></strong>
                                <?php echo esc_html($environment['php_info']['memory_limit']); ?>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Max Execution Time:', 'sewn-ws'); ?></strong>
                                <?php echo esc_html($environment['php_info']['max_execution_time']); ?>s
                            </li>
                        </ul>

                        <h3><?php esc_html_e('WordPress Information', 'sewn-ws'); ?></h3>
                        <ul>
                            <li>
                                <strong><?php esc_html_e('Version:', 'sewn-ws'); ?></strong>
                                <?php echo esc_html($environment['wordpress_info']['version']); ?>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Debug Mode:', 'sewn-ws'); ?></strong>
                                <?php echo $environment['wordpress_info']['debug_mode'] ? esc_html__('Enabled', 'sewn-ws') : esc_html__('Disabled', 'sewn-ws'); ?>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Multisite:', 'sewn-ws'); ?></strong>
                                <?php echo $environment['wordpress_info']['is_multisite'] ? esc_html__('Yes', 'sewn-ws') : esc_html__('No', 'sewn-ws'); ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('sewn_ws_settings');
                do_settings_sections('websocket-server-settings');
                submit_button();
                ?>
            </form>

            <div class="sewn-ws-debug-panel">
                <h2><?php esc_html_e('Debug Information', 'sewn-ws'); ?></h2>
                <div class="sewn-ws-log-viewer">
                    <div class="log-controls">
                        <button type="button" class="button button-secondary" id="clear-logs">
                            <?php esc_html_e('Clear Logs', 'sewn-ws'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="export-logs">
                            <?php esc_html_e('Export Logs', 'sewn-ws'); ?>
                        </button>
                    </div>
                    <div class="log-content">
                        <!-- Log entries will be loaded here via AJAX -->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init() {
        register_setting(
            'sewn_ws_settings',
            'sewn_ws_settings',
            array($this, 'sanitize')
        );

        add_settings_section(
            'sewn_ws_environment_settings',
            __('Environment Settings', 'sewn-ws'),
            array($this, 'print_environment_section_info'),
            'websocket-server-settings'
        );

        $environment = $this->monitor->get_environment_status();
        $is_local = isset($environment['is_local']) ? $environment['is_local'] : false;
        $container_mode = isset($environment['container_mode']) ? $environment['container_mode'] : false;

        add_settings_field(
            'local_mode',
            __('Local Environment Mode', 'sewn-ws'),
            array($this, 'local_mode_callback'),
            'websocket-server-settings',
            'sewn_ws_environment_settings',
            array(
                'disabled' => $is_local,
                'auto_detected' => $is_local
            )
        );

        add_settings_field(
            'container_mode',
            __('Container Mode', 'sewn-ws'),
            array($this, 'container_mode_callback'),
            'websocket-server-settings',
            'sewn_ws_environment_settings',
            array(
                'disabled' => $container_mode,
                'auto_detected' => $container_mode
            )
        );

        add_settings_field(
            'local_site_url',
            __('Local Site URL', 'sewn-ws'),
            array($this, 'local_site_url_callback'),
            'websocket-server-settings',
            'sewn_ws_environment_settings'
        );

        add_settings_field(
            'ssl_cert_path',
            __('SSL Certificate Path', 'sewn-ws'),
            array($this, 'ssl_cert_path_callback'),
            'websocket-server-settings',
            'sewn_ws_environment_settings'
        );

        add_settings_field(
            'ssl_key_path',
            __('SSL Key Path', 'sewn-ws'),
            array($this, 'ssl_key_path_callback'),
            'websocket-server-settings',
            'sewn_ws_environment_settings'
        );
    }

    /**
     * Print the Environment Section text
     */
    public function print_environment_section_info() {
        echo '<p>' . esc_html__('Configure your local development environment settings below. Some settings may be automatically detected and locked.', 'sewn-ws') . '</p>';
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function local_mode_callback($args) {
        $disabled = $args['disabled'] ? ' disabled="disabled"' : '';
        $checked = $args['disabled'] || (isset($this->options['local_mode']) && $this->options['local_mode']) ? ' checked="checked"' : '';
        ?>
        <label>
            <input type="checkbox" id="local_mode" name="sewn_ws_settings[local_mode]" value="1"<?php echo $disabled . $checked; ?>>
            <?php esc_html_e('Enable Local Environment Mode', 'sewn-ws'); ?>
        </label>
        <?php if ($args['auto_detected']) : ?>
            <p class="description">
                <?php esc_html_e('Local environment automatically detected. This setting is locked.', 'sewn-ws'); ?>
            </p>
        <?php else : ?>
            <p class="description">
                <?php esc_html_e('Enable this for local development environments.', 'sewn-ws'); ?>
            </p>
        <?php endif;
    }

    /**
     * Container mode callback
     */
    public function container_mode_callback($args) {
        $disabled = $args['disabled'] ? ' disabled="disabled"' : '';
        $checked = $args['disabled'] || (isset($this->options['container_mode']) && $this->options['container_mode']) ? ' checked="checked"' : '';
        ?>
        <label>
            <input type="checkbox" id="container_mode" name="sewn_ws_settings[container_mode]" value="1"<?php echo $disabled . $checked; ?>>
            <?php esc_html_e('Enable Container Mode', 'sewn-ws'); ?>
        </label>
        <?php if ($args['auto_detected']) : ?>
            <p class="description">
                <?php esc_html_e('Container environment detected (Docker/Local by Flywheel). This setting is locked.', 'sewn-ws'); ?>
            </p>
        <?php else : ?>
            <p class="description">
                <?php esc_html_e('Enable this when running in a containerized environment (e.g., Docker, Local by Flywheel).', 'sewn-ws'); ?>
            </p>
        <?php endif;
    }

    /**
     * Local site URL callback
     */
    public function local_site_url_callback() {
        $value = isset($this->options['local_site_url']) ? $this->options['local_site_url'] : '';
        if (empty($value)) {
            $value = get_site_url();
        }
        ?>
        <input type="url" id="local_site_url" name="sewn_ws_settings[local_site_url]" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">
            <?php esc_html_e('The URL of your local development site.', 'sewn-ws'); ?>
        </p>
        <?php
    }

    /**
     * SSL certificate path callback
     */
    public function ssl_cert_path_callback() {
        $value = isset($this->options['ssl_cert_path']) ? $this->options['ssl_cert_path'] : '';
        ?>
        <input type="text" id="ssl_cert_path" name="sewn_ws_settings[ssl_cert_path]" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <button type="button" class="button button-secondary" id="detect-ssl-cert">
            <?php esc_html_e('Detect Certificate', 'sewn-ws'); ?>
        </button>
        <p class="description">
            <?php esc_html_e('Path to your SSL certificate file (.crt or .pem).', 'sewn-ws'); ?>
        </p>
        <?php
    }

    /**
     * SSL key path callback
     */
    public function ssl_key_path_callback() {
        $value = isset($this->options['ssl_key_path']) ? $this->options['ssl_key_path'] : '';
        ?>
        <input type="text" id="ssl_key_path" name="sewn_ws_settings[ssl_key_path]" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <button type="button" class="button button-secondary" id="detect-ssl-key">
            <?php esc_html_e('Detect Key', 'sewn-ws'); ?>
        </button>
        <p class="description">
            <?php esc_html_e('Path to your SSL private key file (.key).', 'sewn-ws'); ?>
        </p>
        <?php
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     * @return array
     */
    public function sanitize($input) {
        $new_input = array();

        if (isset($input['local_mode'])) {
            $new_input['local_mode'] = (bool) $input['local_mode'];
        }

        if (isset($input['container_mode'])) {
            $new_input['container_mode'] = (bool) $input['container_mode'];
        }

        if (isset($input['local_site_url'])) {
            $new_input['local_site_url'] = esc_url_raw($input['local_site_url']);
        }

        if (isset($input['ssl_cert_path'])) {
            $new_input['ssl_cert_path'] = $this->sanitize_file_path($input['ssl_cert_path']);
        }

        if (isset($input['ssl_key_path'])) {
            $new_input['ssl_key_path'] = $this->sanitize_file_path($input['ssl_key_path']);
        }

        return $new_input;
    }

    /**
     * Sanitize file path
     *
     * @param string $path File path to sanitize
     * @return string
     */
    private function sanitize_file_path($path) {
        // Remove any potentially harmful characters
        $path = preg_replace('/[^a-zA-Z0-9\/\\\._-]/', '', $path);
        
        // Ensure the path is absolute
        if (!path_is_absolute($path)) {
            $path = ABSPATH . ltrim($path, '/\\');
        }
        
        return $path;
    }

    public function register_settings() {
        // Register settings group
        register_setting(
            'sewn_ws_settings', // Group name
            'sewn_ws_options', // Option name
            [
                'type' => 'array',
                'description' => 'WebSocket Server Settings',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => []
            ]
        );

        // Register individual settings
        register_setting('sewn_ws_settings', 'sewn_ws_port');
        register_setting('sewn_ws_settings', 'sewn_ws_local_mode');
        register_setting('sewn_ws_settings', 'sewn_ws_container_mode');
        register_setting('sewn_ws_settings', 'sewn_ws_local_site_url');
        register_setting('sewn_ws_settings', 'sewn_ws_ssl_key');
        register_setting('sewn_ws_settings', 'sewn_ws_ssl_cert');

        // Add settings sections
        add_settings_section(
            'server_config',
            __('Server Configuration', 'sewn-ws'),
            null,
            'sewn-ws-settings'
        );

        add_settings_section(
            'local_environment',
            __('Local Environment Settings', 'sewn-ws'),
            [$this, 'render_local_environment_section'],
            'sewn-ws-settings'
        );

        // Add settings fields
        add_settings_field(
            'ws_port',
            __('WebSocket Port', 'sewn-ws'),
            [$this, 'render_port_field'],
            'sewn-ws-settings',
            'server_config'
        );
    }

    public function render_port_field() {
        $port = get_option('sewn_ws_port', 8080);
        echo "<input name='sewn_ws_port' value='$port'>";
    }

    /**
     * Render local environment section description
     */
    public function render_local_environment_section($args) {
        ?>
        <p class="description">
            <?php _e('Configure settings for running the WebSocket server in a Local by Flywheel environment.', 'sewn-ws'); ?>
        </p>
        <?php if (get_option('sewn_ws_local_mode', false)): ?>
            <div class="notice notice-info inline">
                <p>
                    <?php _e('Local Environment Mode is enabled. The server will use container-aware networking and SSL settings.', 'sewn-ws'); ?>
                </p>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        if (!isset($_POST['sewn_ws_nonce']) || !wp_verify_nonce($_POST['sewn_ws_nonce'], 'sewn_ws_settings')) {
            add_settings_error(
                'sewn_ws_settings',
                'invalid_nonce',
                __('Security check failed. Please try again.', 'sewn-ws')
            );
            return get_option('sewn_ws_options', []);
        }

        $output = [];

        // Sanitize port
        $output['port'] = isset($input['port']) ? absint($input['port']) : 8080;
        if ($output['port'] < 1024 || $output['port'] > 65535) {
            add_settings_error(
                'sewn_ws_settings',
                'invalid_port',
                __('Port must be between 1024 and 65535.', 'sewn-ws')
            );
            $output['port'] = 8080;
        }

        // Sanitize boolean values
        $output['local_mode'] = !empty($input['local_mode']);
        $output['container_mode'] = !empty($input['container_mode']);

        // Sanitize URL
        $output['local_site_url'] = esc_url_raw($input['local_site_url'] ?? '');

        // Sanitize file paths
        $output['ssl_key'] = $this->sanitize_file_path($input['ssl_key'] ?? '');
        $output['ssl_cert'] = $this->sanitize_file_path($input['ssl_cert'] ?? '');

        return $output;
    }

    public function render_settings_page() {
        include plugin_dir_path(__FILE__) . 'views/settings.php';
    }
}

// Initialize the settings page
Settings_Page::get_instance();
