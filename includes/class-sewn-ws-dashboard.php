<?php

/**
 * Location: includes/class-sewn-ws-dashboard.php
 * Dependencies: WordPress admin UI, Module_Admin, Settings_Page
 * Classes: Dashboard
 * 
 * Creates administrative interface for WebSocket server configuration and monitoring. Centralizes status display, module management, and real-time connection metrics visualization.
 */

namespace SEWN\WebSockets\Admin;

use SEWN\WebSockets\WebSocket_Server;

class Dashboard {
    private static $instance = null;
    private $registry = null;
    private $server; 
    
    const MENU_SLUG = 'sewn-ws-dashboard';
    
    private function __construct() {
        $this->server = new WebSocket_Server();
        
        // Modified module registry initialization
        if (class_exists('\SEWN\WebSockets\Module_Registry')) {
            $this->registry = \SEWN\WebSockets\Module_Registry::get_instance();
            $this->registry->discover_modules();
            $this->registry->init_modules();
        } else {
            error_log('Module Registry class not found - continuing without module support');
        }
    }
    
    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
            add_action('admin_menu', [self::$instance, 'register_menus']);
        }
        return self::$instance;
    }
    
    public function register_menus() {
        // Main Dashboard
        add_menu_page(
            __('WebSocket Server', 'sewn-ws'),
            __('WebSockets', 'sewn-ws'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_root_dashboard'],
            'dashicons-networking',
            80
        );
        
        // Dashboard Submenu (default view)
        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard', 'sewn-ws'),
            __('Dashboard', 'sewn-ws'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_root_dashboard']
        );
        
        // Status Submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('Status Monitor', 'sewn-ws'),
            __('Status', 'sewn-ws'),
            'manage_options',
            'sewn-ws-status',
            [$this, 'render_status_view']
        );
        
        // Modules Submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('Installed Modules', 'sewn-ws'),
            __('Modules', 'sewn-ws'),
            'manage_options',
            'sewn-ws-modules',
            [$this, 'render_modules_view']
        );
        
        // Settings Submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('Server Configuration', 'sewn-ws'),
            __('Settings', 'sewn-ws'),
            'manage_options',
            'sewn-ws-settings',
            [$this, 'render_settings_view']
        );
    }
    
    public function render_root_dashboard() {
        // Delegate to Admin_UI's render method
        Admin_UI::get_instance()->render_dashboard();
    }
    
    public function render_status_view() {
        Admin_UI::get_instance()->render_status();
    }
    
    public function render_modules_view() {
        if ($this->registry) {
            Admin_UI::get_instance()->render_modules_page();
        } else {
            echo '<div class="notice notice-error"><p>';
            _e('Module system unavailable - required components missing', 'sewn-ws');
            echo '</p></div>';
        }
    }
    
    public function render_settings_view() {
        Admin_UI::get_instance()->render_settings();
    }
}

// Initialize the dashboard
Dashboard::init();

