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
use SEWN\WebSockets\Config;

/**
 * Class Settings_Page
 * Handles the plugin settings page in WordPress admin
 */
class Settings_Page {
    /**
     * Singleton instance
     *
     * @var Settings_Page
     */
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

    /**
     * Hook suffix for the settings page
     *
     * @var string
     */
    private $page_hook;

    /**
     * Get singleton instance
     *
     * @return Settings_Page
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
        $this->logger = Error_Logger::get_instance();
        $this->monitor = Environment_Monitor::get_instance();
        $this->options = get_option(SEWN_WS_SETTINGS_GROUP, []);

        $this->init();
    }

    /**
     * Initialize the settings page
     */
    private function init() {
        // Register settings on admin_init
        add_action('admin_init', array($this, 'register_settings'));
        
        // Initialize AJAX handlers
        Settings_Ajax::init();

        // Register assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Register settings and sections
     */
    public function register_settings() {
        // Register settings
        $this->register_general_settings();
        $this->register_environment_settings();
        $this->register_ssl_settings();
        $this->register_debug_settings();

        // Add settings sections
        $this->add_settings_sections();
    }

    /**
     * Register general settings
     */
    private function register_general_settings() {
        register_setting('sewn_ws_settings', 'sewn_ws_port', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'validate_port'),
            'default' => SEWN_WS_DEFAULT_PORT
        ));

        register_setting('sewn_ws_settings', 'sewn_ws_host', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'localhost'
        ));

        register_setting('sewn_ws_settings', SEWN_WS_OPTION_RATE_LIMIT, array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'validate_rate_limit'),
            'default' => 60
        ));
    }

    /**
     * Register environment settings
     */
    private function register_environment_settings() {
        register_setting('sewn_ws_settings', SEWN_WS_OPTION_DEV_MODE, array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ));

        register_setting('sewn_ws_settings', 'sewn_ws_env_local_mode', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ));

        register_setting('sewn_ws_settings', 'sewn_ws_env_container_mode', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ));
    }

    /**
     * Register SSL settings
     */
    private function register_ssl_settings() {
        register_setting('sewn_ws_settings', SEWN_WS_OPTION_SSL_CERT, array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'validate_file_path'),
            'default' => ''
        ));

        register_setting('sewn_ws_settings', SEWN_WS_OPTION_SSL_KEY, array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'validate_file_path'),
            'default' => ''
        ));
    }

    /**
     * Register debug settings
     */
    private function register_debug_settings() {
        register_setting('sewn_ws_settings', 'sewn_ws_debug_enabled', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ));
    }

    /**
     * Add settings sections
     */
    private function add_settings_sections() {
        // General section
        add_settings_section(
            'sewn_ws_general',
            __('General Settings', 'sewn-ws'),
            null,
            'sewn_ws_settings'
        );

        // Environment section
        add_settings_section(
            'sewn_ws_environment',
            __('Environment Settings', 'sewn-ws'),
            array($this, 'render_environment_section'),
            'sewn_ws_settings'
        );

        // SSL section
        add_settings_section(
            'sewn_ws_ssl',
            __('SSL Configuration', 'sewn-ws'),
            array($this, 'render_ssl_section'),
            'sewn_ws_settings'
        );

        // Debug section
        add_settings_section(
            'sewn_ws_debug',
            __('Debug Settings', 'sewn-ws'),
            null,
            'sewn_ws_settings'
        );
    }

    /**
     * Render environment section description
     */
    public function render_environment_section() {
        echo '<p>' . __('Configure environment-specific settings for local development and containerized environments.', 'sewn-ws') . '</p>';
    }

    /**
     * Render SSL section description
     */
    public function render_ssl_section() {
        echo '<p>' . __('Configure SSL certificates for secure WebSocket connections.', 'sewn-ws') . '</p>';
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if ($hook !== $this->page_hook) {
            return;
        }

        // Enqueue main settings styles and scripts
        wp_enqueue_style(
            'sewn-ws-settings',
            plugins_url('css/settings.css', dirname(__FILE__)),
            array(),
            SEWN_WS_VERSION
        );

        wp_enqueue_script(
            'sewn-ws-settings',
            plugins_url('js/settings.js', dirname(__FILE__)),
            array('jquery'),
            SEWN_WS_VERSION,
            true
        );

        // Localize script
        wp_localize_script('sewn-ws-settings', 'sewnWsAdmin', array(
            'nonce' => wp_create_nonce('sewn_ws_admin'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n' => array(
                'checking' => __('Checking environment...', 'sewn-ws'),
                'success' => __('Environment check completed', 'sewn-ws'),
                'error' => __('Failed to check environment', 'sewn-ws'),
                'confirmClearLogs' => __('Are you sure you want to clear all logs?', 'sewn-ws'),
            ),
        ));
    }

    /**
     * Render the settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/views/settings.php';
    }

    /**
     * Validate port number
     */
    public function validate_port($value) {
        $port = absint($value);
        if ($port < 1024 || $port > 65535) {
            add_settings_error(
                'sewn_ws_port',
                'invalid_port',
                __('Port must be between 1024 and 65535', 'sewn-ws')
            );
            return SEWN_WS_DEFAULT_PORT;
        }
        return $port;
    }

    /**
     * Validate rate limit
     */
    public function validate_rate_limit($value) {
        $rate = absint($value);
        if ($rate < 1) {
            add_settings_error(
                SEWN_WS_OPTION_RATE_LIMIT,
                'invalid_rate',
                __('Rate limit must be greater than 0', 'sewn-ws')
            );
            return 60;
        }
        return $rate;
    }

    /**
     * Validate file path
     */
    public function validate_file_path($value) {
        if (empty($value)) {
            return '';
        }

        $path = sanitize_text_field($value);
        if (!file_exists($path)) {
            add_settings_error(
                'sewn_ws_ssl',
                'invalid_path',
                __('File does not exist', 'sewn-ws')
            );
            return '';
        }

        if (!is_readable($path)) {
            add_settings_error(
                'sewn_ws_ssl',
                'not_readable',
                __('File is not readable', 'sewn-ws')
            );
            return '';
        }

        return $path;
    }

    /**
     * Handle AJAX debug toggle
     */
    public static function ajax_toggle_debug() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        check_ajax_referer(\SEWN_WS_NONCE_ACTION, 'nonce');

        $enabled = isset($_POST['enabled']) ? rest_sanitize_boolean($_POST['enabled']) : false;
        update_option('sewn_ws_debug_enabled', $enabled);

        wp_send_json_success(array(
            'enabled' => $enabled
        ));
    }

    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the instance
     */
    private function __wakeup() {}
}

// Initialize the settings page
add_action('plugins_loaded', array('SEWN\WebSockets\Admin\Settings_Page', 'get_instance'));

// Register AJAX actions
add_action('wp_ajax_sewn_ws_toggle_debug', array('SEWN\WebSockets\Admin\Settings_Page', 'ajax_toggle_debug'));
