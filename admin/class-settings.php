<?php
namespace SEWN\WebSockets\Admin;
use SEWN\WebSockets\Admin\Settings;

class Settings_Page {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'register_menu'], 20);
    }

    public function register_settings() {
        register_setting('sewn_ws_options', 'sewn_ws_port');
        
        add_settings_section(
            'server_config',
            __('Server Configuration', 'sewn-ws'),
            null,
            'sewn-ws-settings'
        );

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

    public function register_menu() {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_menu_page(
            __('WebSocket Server', 'sewn-ws'),
            __('WebSocket', 'sewn-ws'),
            'manage_options',
            'sewn-ws-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-networking',
            80
        );

        add_submenu_page(
            SEWN_WS_ADMIN_MENU_SLUG,
            __('WebSocket Server Settings', SEWN_WS_TEXT_DOMAIN),
            __('Settings'),
            'manage_options',
            SEWN_WS_ADMIN_MENU_SLUG . '-settings',
            [$this, 'render_dashboard']
        );
    }

    public function render_dashboard() {
        include plugin_dir_path(__FILE__) . '../admin/views/dashboard.php';
    }
}

// Initialize the settings page
Settings_Page::get_instance();
