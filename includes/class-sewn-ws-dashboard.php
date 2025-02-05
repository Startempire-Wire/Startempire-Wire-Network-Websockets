<?php
namespace SEWN\WebSockets;

use SEWN\WebSockets\Admin\Module_Admin;
use SEWN\WebSockets\Admin\Settings_Page;

class Dashboard {
    private static $instance = null;

    private function __construct() {
        // Hook the add_menu method to admin_menu
        add_action('admin_menu', [$this, 'add_menu']);
        // Hook assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_menu() {
        error_log('[SEWN] Attempting menu registration');
        // SINGLE top-level menu
        add_menu_page(
            __('WebSocket Server', 'sewn-ws'),
            __('WebSockets', 'sewn-ws'),
            'manage_options',
            'sewn-ws',
            [$this, 'render_dashboard'],
            'dashicons-networking'
        );

        // Dashboard submenu (main page)
        add_submenu_page('sewn-ws',
            __('Dashboard', 'sewn-ws'),
            __('Dashboard', 'sewn-ws'),
            'manage_options',
            'sewn-ws',
            [$this, 'render_dashboard']
        );

        // Status submenu
        add_submenu_page('sewn-ws',
            __('Status', 'sewn-ws'),
            __('Status', 'sewn-ws'),
            'manage_options',
            'sewn-ws-status',
            [$this, 'render_status']
        );

        // Modules submenu
        add_submenu_page('sewn-ws',
            __('Modules', 'sewn-ws'),
            __('Modules', 'sewn-ws'),
            'manage_options',
            'sewn-ws-modules',
            [Module_Admin::class, 'render_modules_page']
        );

        // Settings submenu
        add_submenu_page('sewn-ws',
            __('Settings', 'sewn-ws'),
            __('Settings', 'sewn-ws'),
            'manage_options',
            'sewn-ws-settings',
            [Settings_Page::get_instance(), 'render_settings_page']
        );

        error_log('[SEWN] Main menu registered');
        error_log('[SEWN] Current user capabilities: ' . print_r(wp_get_current_user()->allcaps, true));
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'sewn-ws-admin',
            SEWN_WS_URL . 'assets/css/admin.css',
            [],
            SEWN_WS_VERSION
        );
    }

    public function render_dashboard() {
        ?>
        <div class="wrap sewn-ws-dashboard-container">
            <h1>WebSocket Server Management</h1>
            
            <div class="card">
                <h2>Server Controls</h2>
                <button id="start-server" class="button button-primary">Start</button>
                <button id="stop-server" class="button button-secondary">Stop</button>
                <span id="server-status"></span>
            </div>

            <div class="card">
                <h2>Real-time Statistics</h2>
                <div id="network-stats">
                    <p>Connections: <span class="connection-count">0</span></p>
                    <p>Bandwidth: <span class="bandwidth">0KB/s</span></p>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_status() {
        ?>
        <div class="wrap sewn-ws-status-container">
            <h1><?php _e('WebSocket Server Status', 'sewn-ws'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Server Status', 'sewn-ws'); ?></h2>
                <div id="server-status-details">
                    <p><?php _e('Current Status:', 'sewn-ws'); ?> <span id="current-status">-</span></p>
                    <p><?php _e('Uptime:', 'sewn-ws'); ?> <span id="server-uptime">-</span></p>
                    <p><?php _e('Active Connections:', 'sewn-ws'); ?> <span id="active-connections">0</span></p>
                </div>
            </div>
        </div>
        <?php
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

