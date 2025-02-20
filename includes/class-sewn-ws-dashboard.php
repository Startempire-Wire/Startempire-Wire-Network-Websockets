<?php

/**
 * Location: includes/class-sewn-ws-dashboard.php
 * Dependencies: WordPress admin UI, Module_Admin, Settings_Page
 * Classes: Dashboard
 * 
 * Creates administrative interface for WebSocket server configuration and monitoring. Centralizes status display, module management, and real-time connection metrics visualization.
 */

namespace SEWN\WebSockets\Admin;

use SEWN\WebSockets\Admin\Admin_UI;

class Dashboard {
    private static $instance = null;
    private $registry = null;
    
    const MENU_SLUG = 'sewn-ws-dashboard';
    
    private function __construct() {
        // Modified module registry initialization
        if (class_exists('\SEWN\WebSockets\Module_Registry')) {
            $this->registry = \SEWN\WebSockets\Module_Registry::get_instance();
            error_log('[SEWN] Dashboard initialized with registry');
            error_log('[SEWN] Available modules in registry: ' . print_r($this->registry->get_modules(), true));
        } else {
            error_log('Module Registry class not found - continuing without module support');
        }
    }
    
    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
            add_action('admin_menu', [self::$instance, 'register_menus']);
            error_log('[SEWN] Dashboard initialized');
        }
        return self::$instance;
    }
    
    public function register_menus() {
        // Main Menu
        add_menu_page(
            __('WebSocket Server', 'sewn-ws'),
            __('WebSockets', 'sewn-ws'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_root_dashboard'],
            'dashicons-networking',
            80
        );

        // Submenu Items
        $submenus = [
            [
                'parent' => self::MENU_SLUG,
                'title'  => __('Dashboard', 'sewn-ws'),
                'cap'    => 'manage_options',
                'slug'   => self::MENU_SLUG,
                'render' => 'render_root_dashboard'
            ],
            [
                'parent' => self::MENU_SLUG,
                'title'  => __('Status Monitor', 'sewn-ws'),
                'cap'    => 'manage_options',
                'slug'   => 'sewn-ws-status',
                'render' => 'render_status_view'
            ],
            [
                'parent' => self::MENU_SLUG,
                'title'  => __('Modules', 'sewn-ws'), 
                'cap'    => 'manage_options',
                'slug'   => 'sewn-ws-modules',
                'render' => 'render_modules_view'
            ],
            [
                'parent' => self::MENU_SLUG,
                'title'  => __('Settings', 'sewn-ws'),
                'cap'    => 'manage_options',
                'slug'   => 'sewn-ws-settings',
                'render' => 'render_settings_view'
            ]
        ];

        foreach ($submenus as $submenu) {
            add_submenu_page(
                $submenu['parent'],
                $submenu['title'],
                $submenu['title'],
                $submenu['cap'],
                $submenu['slug'],
                [$this, $submenu['render']]
            );
        }
    }
    
    public function render_root_dashboard() {
        // Delegate to Admin_UI's render method
        Admin_UI::get_instance()->render_dashboard();
    }
    
    public function render_status_view() {
        Admin_UI::get_instance()->render_status();
    }
    
    public function render_modules_view() {
        error_log('[SEWN] Rendering modules view');
        if ($this->registry) {
            $modules = $this->registry->get_modules();
            error_log('[SEWN] Available modules: ' . print_r($modules, true));
            Admin_UI::get_instance()->render_modules_page();
        } else {
            error_log('[SEWN] Registry not available in Dashboard');
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

