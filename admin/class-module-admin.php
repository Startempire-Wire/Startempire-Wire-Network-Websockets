<?php

/**
 * LOCATION: admin/class-module-admin.php
 * DEPENDENCIES: Module_Registry, WordPress Settings API
 * VARIABLES: SEWN_WS_MODULE_SETTINGS_PREFIX
 * CLASSES: Module_Admin (module configuration handler)
 * 
 * Manages plugin module registration and settings interface. Ensures compatibility with network-wide authentication
 * tiers and membership levels. Serves as configuration layer for WebRing feature toggles and ad network controls.
 */

namespace SEWN\WebSockets\Admin;

use SEWN\WebSockets\Module_Base;
use SEWN\WebSockets\Module_Registry;

class Module_Admin {
    private $registry;
    private $current_screen;

    public function __construct(Module_Registry $registry) {
        $this->registry = $registry;
        add_action('admin_init', [$this, 'handle_module_actions']);
        add_action('admin_init', [$this, 'register_module_settings']);
        add_action('wp_ajax_sewn_ws_get_stats', [$this, 'handle_stats_request']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('current_screen', [$this, 'set_current_screen']);
    }

    public function set_current_screen($screen) {
        $this->current_screen = $screen;
    }

    public function should_show_module_errors() {
        if (!$this->current_screen) {
            return false;
        }
        
        $valid_screens = [
            'websockets_page_sewn-ws-modules',
            'websockets_page_sewn-ws-module-settings'
        ];
        
        return in_array($this->current_screen->id, $valid_screens);
    }

    public function handle_module_actions() {
        if (!isset($_POST['sewn_module_action']) || !check_admin_referer('sewn_module_control')) {
            return;
        }

        $module_slug = sanitize_key($_POST['module_slug']);
        $module = $this->registry->get_module($module_slug);
        
        if (!$module instanceof Module_Base) {
            return;
        }

        switch ($_POST['sewn_module_action']) {
            case 'activate':
                update_option("sewn_module_{$module_slug}_active", true);
                if (method_exists($module, 'init')) {
                    $module->init();
                }
                break;
                
            case 'deactivate':
                update_option("sewn_module_{$module_slug}_active", false);
                if (method_exists($module, 'deactivate')) {
                    $module->deactivate();
                }
                break;
        }
    }

    public function render_modules() {
        include_once dirname(__FILE__) . '/views/module-settings.php';
    }

    public function render_settings() {
        include_once dirname(__FILE__) . '/views/settings.php';
    }

    public function render_dashboard() {
        include_once dirname(__FILE__) . '/views/dashboard.php';
    }

    public function register_module_settings() {
        foreach ($this->registry->get_modules() as $module) {
            $config = $module->admin_ui();
            $slug = $module->get_module_slug();
            
            register_setting(
                "sewn_ws_module_{$slug}",
                sprintf(SEWN_WS_MODULE_SETTINGS_PREFIX, $slug),
                [
                    'sanitize_callback' => [$this, 'sanitize_module_settings'],
                    'show_in_rest' => false,
                    'type' => 'object',
                    'default' => []
                ]
            );

            // Register sections
            foreach ($config['sections'] as $section) {
                add_settings_section(
                    $section['id'],
                    $section['title'],
                    $section['callback'] ?? '__return_empty_string',
                    "sewn_ws_module_{$slug}"
                );
            }

            foreach ($config['settings'] as $setting) {
                add_settings_field(
                    $setting['name'],
                    $setting['label'],
                    [$this, 'render_setting_field'],
                    "sewn_ws_module_{$slug}",
                    $setting['section'] ?? 'default',
                    [
                        'module' => $slug,
                        'name' => $setting['name'],
                        'type' => $setting['type'] ?? 'text',
                        'options' => $setting['options'] ?? []
                    ]
                );
            }
        }
    }

    public function render_setting_field($args) {
        $value = get_option(sprintf(SEWN_WS_MODULE_SETTINGS_PREFIX, $args['module']))[$args['name']] ?? '';
        $name = "sewn_ws_module_{$args['module']}_settings[{$args['name']}]";
        
        switch ($args['type']) {
            case 'password':
                echo '<input type="password" name="'.esc_attr($name).'" 
                      value="'.esc_attr($value).'" class="regular-text">';
                break;
            case 'select':
                echo '<select name="'.esc_attr($name).'">';
                foreach ($args['options'] as $key => $label) {
                    echo '<option value="'.esc_attr($key).'" '.selected($value, $key, false).'>'
                         .esc_html($label).'</option>';
                }
                echo '</select>';
                break;
            case 'textarea':
                echo '<textarea name="'.esc_attr($name).'" class="large-text" rows="5">'
                     .esc_textarea($value).'</textarea>';
                break;
            default:
                echo '<input type="text" name="'.esc_attr($name).'" 
                      value="'.esc_attr($value).'" class="regular-text">';
        }
    }

    public function sanitize_module_settings($input) {
        $sanitized = [];
        foreach ($input as $key => $value) {
            $sanitized[$key] = sanitize_text_field($value);
        }
        return $sanitized;
    }

    public function handle_stats_request() {
        check_ajax_referer('sewn_ws_nonce', 'nonce');
        
        wp_send_json_success([
            'connections' => get_transient('sewn_ws_connections'),
            'status' => get_option('sewn_ws_server_status')
        ]);
    }

    public function enqueue_scripts($hook) {
        // Add explicit check
        if ($hook !== 'toplevel_page_sewn-ws') {
            return;
        }
        
        // Initialize all variables with defaults
        $menu_slug = defined('SEWN_WS_ADMIN_MENU_SLUG') ? SEWN_WS_ADMIN_MENU_SLUG : 'sewn-ws';
        $current_port = (int) get_option(
            defined('SEWN_WS_OPTION_PORT') ? SEWN_WS_OPTION_PORT : 'sewn_ws_port',
            defined('SEWN_WS_DEFAULT_PORT') ? SEWN_WS_DEFAULT_PORT : 8080
        );

        // Explicit script dependencies array
        $deps = ['jquery'];
        
        // Register script with module type
        wp_register_script(
            SEWN_WS_SCRIPT_HANDLE_ADMIN,
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            $deps,
            SEWN_WS_VERSION,
            true
        );
        
        // Add module type attribute
        wp_script_add_data(SEWN_WS_SCRIPT_HANDLE_ADMIN, 'type', 'module');

        wp_enqueue_script(SEWN_WS_SCRIPT_HANDLE_ADMIN);
        
        // Localize with fallback values
        wp_localize_script(SEWN_WS_SCRIPT_HANDLE_ADMIN, 'sewn_ws_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(
                defined('SEWN_WS_NONCE_ACTION') ? SEWN_WS_NONCE_ACTION : 'sewn_ws_nonce'
            ),
            'port' => $current_port
        ]);
    }

    public function get_module_settings($module_slug) {
        return get_option(sprintf(SEWN_WS_MODULE_SETTINGS_PREFIX, $module_slug), []);
    }

    public function render_modules_page() {
        $module_registry = \SEWN\WebSockets\Module_Registry::get_instance();
        $modules = $module_registry->get_modules();
        $module_errors = [];
        
        // Only process dependency checks for active modules and only on modules page
        if ($this->should_show_module_errors()) {
            foreach ($modules as $module) {
                $module_slug = $module->get_module_slug();
                if ($this->is_module_active($module_slug)) {
                    $module_errors[$module_slug] = $this->check_module_dependencies($module);
                }
            }
        }
        
        // Pass errors to template
        include plugin_dir_path(__FILE__) . 'views/modules-list.php';
    }

    private function is_module_active($module_slug) {
        return (bool) get_option("sewn_module_{$module_slug}_active", false);
    }

    private function check_module_dependencies($module) {
        if (!method_exists($module, 'check_dependencies')) {
            return [];
        }

        $dependency_errors = $module->check_dependencies();
        
        // Don't add admin notices, just return the errors
        return is_array($dependency_errors) ? $dependency_errors : [];
    }

    public function render_module_settings_page() {
        $module_slug = isset($_GET['module']) ? sanitize_key($_GET['module']) : '';
        $module = $this->registry->get_module($module_slug);
        
        if (!$module || !$module instanceof Module_Base) {
            wp_die(__('Invalid module or module not loaded', 'sewn-ws'));
        }
        
        // Check for dependency errors
        if ($this->should_show_module_errors()) {
            $module_errors = $this->check_module_dependencies($module);
            if (!empty($module_errors)) {
                echo '<div class="notice notice-error">';
                foreach ($module_errors as $error) {
                    echo '<p>' . esc_html($error) . '</p>';
                }
                echo '</div>';
            }
        }
        
        // Load and use the settings base class
        require_once dirname(__FILE__) . '/class-module-settings-base.php';
        $settings = new Module_Settings_Base($module);
        $settings->render();
    }
}