<?php
namespace SEWN\WebSockets\Admin;

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


    public function render_settings_page() {
        include plugin_dir_path(__FILE__) . 'views/settings.php';
    }
}

// Initialize the settings page
Settings_Page::get_instance();
