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

use SEWN\WebSockets\Admin\Environment_Monitor;
use SEWN\WebSockets\Admin\Error_Logger;

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
        $this->options = get_option(SEWN_WS_SETTINGS_GROUP, []);

        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
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
                    'checking' => __('Checking environment...', SEWN_WS_TEXT_DOMAIN),
                    'success' => __('Environment check completed', SEWN_WS_TEXT_DOMAIN),
                    'error' => __('Failed to check environment', SEWN_WS_TEXT_DOMAIN),
                    'confirmClearLogs' => __('Are you sure you want to clear all logs?', SEWN_WS_TEXT_DOMAIN),
                ),
            )
        );
    }

    

    /**
     * Register and add settings
     */
    public function register_settings() {
        // Main settings group
        register_setting(
            SEWN_WS_SETTINGS_GROUP,
            SEWN_WS_SETTINGS_GROUP,
            [$this, 'sanitize_settings']
        );

        // Server configuration section
        add_settings_section(
            'server_config',
            __('Server Configuration', SEWN_WS_TEXT_DOMAIN),
            null,
            SEWN_WS_SETTINGS_GROUP
        );

        // Add port setting
        add_settings_field(
            'ws_port',
            __('WebSocket Port', SEWN_WS_TEXT_DOMAIN),
            [$this, 'render_port_field'],
            SEWN_WS_SETTINGS_GROUP,
            'server_config',
            ['label_for' => 'sewn_ws_port']
        );

        // Environment settings section
        add_settings_section(
            'environment_config',
            __('Environment Settings', SEWN_WS_TEXT_DOMAIN),
            [$this, 'render_environment_section'],
            SEWN_WS_SETTINGS_GROUP
        );

        // Add environment fields
        $this->add_environment_fields();
    }

    private function add_environment_fields() {
        $env_status = $this->monitor->get_environment_status();
        
        add_settings_field(
            'local_mode',
            __('Local Environment', SEWN_WS_TEXT_DOMAIN),
            [$this, 'render_local_mode_field'],
            SEWN_WS_SETTINGS_GROUP,
            'environment_config',
            [
                'disabled' => $env_status['is_local'],
                'auto_detected' => $env_status['is_local']
            ]
        );

        // Add SSL fields only if local mode is enabled
        if ($env_status['is_local'] || get_option('sewn_ws_local_mode', false)) {
            $this->add_ssl_fields();
        }
    }

    private static function add_ssl_fields() {
        add_settings_field(
            'ssl_cert_path',
            __('SSL Certificate Path', 'sewn-ws'),
            [self::class, 'render_text_field'],
            'sewn_ws_settings',
            'sewn_ws_environment_section',
            [
                'label_for' => 'ssl_cert_path',
                'description' => __('Path to SSL certificate file (PEM format)', 'sewn-ws')
            ]
        );

        add_settings_field(
            'ssl_key_path',
            __('SSL Private Key Path', 'sewn-ws'),
            [self::class, 'render_text_field'],
            'sewn_ws_settings',
            'sewn_ws_environment_section',
            [
                'label_for' => 'ssl_key_path', 
                'description' => __('Path to SSL private key file', 'sewn-ws')
            ]
        );

        add_settings_field(
            'ssl_ca_bundle',
            __('CA Bundle Path', 'sewn-ws'),
            [self::class, 'render_text_field'],
            'sewn_ws_settings',
            'sewn_ws_environment_section',
            [
                'label_for' => 'ssl_ca_bundle',
                'description' => __('Path to CA bundle file (if required)', 'sewn-ws')
            ]
        );
    }

    public function sanitize_settings($input) {
        $this->logger->log('Sanitizing settings input', ['input' => $input]);
        
        $sanitized = [];
        
        // Sanitize port
        if (isset($input['port'])) {
            $port = absint($input['port']);
            $sanitized['port'] = ($port >= 1024 && $port <= 65535) ? $port : SEWN_WS_DEFAULT_PORT;
        }

        // Sanitize other fields...

        return $sanitized;
    }

    public function render_port_field() {
        $port = get_option('sewn_ws_port', 8080);
        echo "<input name='sewn_ws_port' value='$port'>";
    }

    /**
     * Render local environment section description
     */
    public function render_environment_section($args) {
        ?>
        <p class="description">
            <?php _e('Configure your local development environment settings below. Some settings may be automatically detected and locked.', SEWN_WS_TEXT_DOMAIN); ?>
        </p>
        <?php if (get_option('sewn_ws_local_mode', false)): ?>
            <div class="notice notice-info inline">
                <p>
                    <?php _e('Local Environment Mode is enabled. The server will use container-aware networking and SSL settings.', SEWN_WS_TEXT_DOMAIN); ?>
                </p>
            </div>
        <?php endif; ?>
        <?php
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
            <?php esc_html_e('Enable Local Environment Mode', SEWN_WS_TEXT_DOMAIN); ?>
        </label>
        <?php if ($args['auto_detected']) : ?>
            <p class="description">
                <?php esc_html_e('Local environment automatically detected. This setting is locked.', SEWN_WS_TEXT_DOMAIN); ?>
            </p>
        <?php else : ?>
            <p class="description">
                <?php esc_html_e('Enable this for local development environments.', SEWN_WS_TEXT_DOMAIN); ?>
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
            <?php esc_html_e('Enable Container Mode', SEWN_WS_TEXT_DOMAIN); ?>
        </label>
        <?php if ($args['auto_detected']) : ?>
            <p class="description">
                <?php esc_html_e('Container environment detected (Docker/Local by Flywheel). This setting is locked.', SEWN_WS_TEXT_DOMAIN); ?>
            </p>
        <?php else : ?>
            <p class="description">
                <?php esc_html_e('Enable this when running in a containerized environment (e.g., Docker, Local by Flywheel).', SEWN_WS_TEXT_DOMAIN); ?>
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
            <?php esc_html_e('The URL of your local development site.', SEWN_WS_TEXT_DOMAIN); ?>
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
            <?php esc_html_e('Detect Certificate', SEWN_WS_TEXT_DOMAIN); ?>
        </button>
        <p class="description">
            <?php esc_html_e('Path to your SSL certificate file (.crt or .pem).', SEWN_WS_TEXT_DOMAIN); ?>
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
            <?php esc_html_e('Detect Key', SEWN_WS_TEXT_DOMAIN); ?>
        </button>
        <p class="description">
            <?php esc_html_e('Path to your SSL private key file (.key).', SEWN_WS_TEXT_DOMAIN); ?>
        </p>
        <?php
    }

    public function render_local_mode_field($args) {
        $disabled = $args['disabled'] ? ' disabled="disabled"' : '';
        $checked = $args['disabled'] || (isset($this->options['local_mode']) && $this->options['local_mode']) ? ' checked="checked"' : '';
        ?>
        <label>
            <input type="checkbox" id="local_mode" name="sewn_ws_settings[local_mode]" value="1"<?php echo $disabled . $checked; ?>>
            <?php esc_html_e('Enable Local Environment Mode', SEWN_WS_TEXT_DOMAIN); ?>
        </label>
        <?php if ($args['auto_detected']) : ?>
            <p class="description">
                <?php esc_html_e('Local environment automatically detected. This setting is locked.', SEWN_WS_TEXT_DOMAIN); ?>
            </p>
        <?php else : ?>
            <p class="description">
                <?php esc_html_e('Enable this for local development environments.', SEWN_WS_TEXT_DOMAIN); ?>
            </p>
        <?php endif;
    }

    public function render_ssl_fields() {
        $env_status = $this->monitor->get_environment_status();
        
        // Add SSL fields only if local mode is enabled
        if ($env_status['is_local'] || get_option('sewn_ws_local_mode', false)) {
            $this->ssl_cert_path_callback();
            $this->ssl_key_path_callback();
        }
    }

    /**
     * AJAX callback to toggle debug mode
     */
    public static function ajax_toggle_debug() {
        error_log('AJAX toggle_debug called');
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sewn_ws_admin')) {
            error_log('Debug toggle failed: Invalid nonce');
            wp_send_json_error('Invalid nonce');
            return;
        }

        if (!isset($_POST['enabled'])) {
            error_log('Debug toggle failed: Missing enabled parameter');
            wp_send_json_error('Missing enabled parameter');
            return;
        }

        $enabled = filter_var($_POST['enabled'], FILTER_VALIDATE_BOOLEAN);
        error_log('Setting debug mode to: ' . ($enabled ? 'enabled' : 'disabled'));
        
        $result = update_option('sewn_ws_debug_enabled', $enabled);
        error_log('Update option result: ' . ($result ? 'success' : 'failed'));
        
        if ($result) {
            wp_send_json_success(array('enabled' => $enabled));
        } else {
            wp_send_json_error('Failed to update option');
        }
    }
}

// After the class definition, register the AJAX action
if (is_admin()) {
    add_action('wp_ajax_sewn_ws_toggle_debug', array('SEWN\WebSockets\Admin\Settings_Page', 'ajax_toggle_debug'));
}

// Initialize the settings page
Settings_Page::get_instance();
