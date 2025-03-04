<?php

/**
 * LOCATION: admin/class-websockets-admin.php
 * DEPENDENCIES: WordPress Plugin API, Admin_UI
 * VARIABLES: SEWN_WS_ENCRYPTION_KEY
 * CLASSES: Websockets_Admin (main admin controller)
 * 
 * Core administration controller handling plugin initialization and security sanitization. Maintains WebSocket
 * server integrity across network authentication methods. Enforces membership-based access rules for admin
 * features per Startempire Wire Network tier system.
 */

namespace SEWN\WebSockets\Admin;

use Buddyboss\LearndashIntegration\Buddypress\Admin;
use SEWN\WebSockets\Admin\Admin_UI;
use SEWN\WebSockets\Admin\Module_Admin;
use SEWN\WebSockets\Module_Registry;

class Websockets_Admin {
    private static $instance = null;
    private $ui;
    private $modules;
    private $settings;

    private function __construct() {
        $this->init_hooks();
        $this->init_components();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_hooks() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    private function init_components() {
        // Initialize Admin_UI first
        $this->ui = Admin_UI::get_instance();
        
        // Then initialize other components
        if (class_exists('\SEWN\WebSockets\Module_Registry')) {
            $this->modules = new Module_Admin(Module_Registry::get_instance());
        }
        
        $this->settings = Settings_Page::get_instance();
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'sewn-ws') === false) return;

        wp_enqueue_style(
            'sewn-ws-admin',
            plugins_url('assets/css/admin.css', dirname(__FILE__)),
            [],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/admin.css')
        );

        wp_enqueue_script(
            'sewn-ws-admin',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            ['jquery', 'wp-util'],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/admin.js'),
            true
        );

        wp_localize_script('sewn-ws-admin', 'sewn_ws_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(SEWN_WS_NONCE_ACTION),
            'i18n' => [
                'confirm_restart' => __('Are you sure you want to restart the WebSocket server?', 'sewn-ws'),
                'server_starting' => __('Server starting...', 'sewn-ws')
            ]
        ]);
    }

    public function register_settings() {
        register_setting(SEWN_WS_SETTINGS_GROUP, SEWN_WS_OPTION_PORT, [
            'sanitize_callback' => [$this, 'sanitize_port'],
            'default' => SEWN_WS_DEFAULT_PORT
        ]);

        register_setting('sewn_ws_settings', 'sewn_ws_ssl_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ]);

        add_settings_field(
            'sewn_ws_port',
            __('WebSocket Port', SEWN_WS_TEXT_DOMAIN),
            [$this, 'render_port_field'],
            SEWN_WS_ADMIN_MENU_SLUG,
            'main'
        );
    }

    public function sanitize_port($value) {
        $port = absint($value);
        return ($port >= 1024 && $port <= 65535) ? $port : SEWN_WS_DEFAULT_PORT;
    }
}

// Initialize the admin interface
Websockets_Admin::get_instance(); 