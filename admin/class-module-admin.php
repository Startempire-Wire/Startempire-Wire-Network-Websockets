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
use SEWN\WebSockets\Config;

/**
 * Module administration handler
 */
class Module_Admin {
    private $registry;
    private $current_screen;
    private $settings_handler;
    private $view_path;

    public function __construct(Module_Registry $registry) {
        $this->registry = $registry;
        $this->view_path = plugin_dir_path(__FILE__) . 'views/';
        
        add_action('admin_init', [$this, 'handle_module_actions']);
        add_action('admin_init', [$this, 'register_module_settings']);
        add_action('wp_ajax_sewn_ws_get_stats', [$this, 'handle_stats_request']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('current_screen', [$this, 'set_current_screen']);
        add_action('admin_menu', [$this, 'register_module_pages'], 20);
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

    /**
     * Handle module actions with legacy support
     */
    public function handle_module_actions() {
        if (!isset($_POST['sewn_module_action']) || !check_admin_referer('sewn_module_control')) {
            error_log('[SEWN] Module action aborted: Missing action or invalid nonce');
            return;
        }

        $module_slug = sanitize_key($_POST['module_slug']);
        error_log("[SEWN] Processing module action for: {$module_slug}");
        
        $module = $this->registry->get_module($module_slug);
        
        if (!$module instanceof Module_Base) {
            error_log("[SEWN] Invalid module instance for slug: {$module_slug}");
            return;
        }

        switch ($_POST['sewn_module_action']) {
            case 'activate':
                error_log("[SEWN] Attempting to activate module: {$module_slug}");
                
                try {
                    // Check for legacy settings before activation
                    if (Config::has_legacy_settings($module_slug)) {
                        error_log("[SEWN] Found legacy settings for module: {$module_slug}");
                        // Migrate legacy settings
                        $migration_results = Config::migrate_legacy_settings();
                        
                        // Log migration results
                        error_log(sprintf(
                            '[SEWN] Migrated legacy settings for module %s: %s',
                            $module_slug,
                            wp_json_encode($migration_results)
                        ));
                    }
                    
                    // Check dependencies but don't block activation
                    $has_warnings = false;
                    if (method_exists($module, 'check_dependencies')) {
                        $dependency_check = $module->check_dependencies();
                        if ($dependency_check !== true) {
                            error_log("[SEWN] Module has dependency warnings: " . wp_json_encode($dependency_check));
                            
                            // Only block if explicitly not allowed to activate
                            if (isset($dependency_check['can_activate']) && $dependency_check['can_activate'] === false) {
                                throw new \Exception("Cannot activate: " . wp_json_encode($dependency_check));
                            }
                            
                            $has_warnings = true;
                            // Store warnings for display
                            set_transient('sewn_module_warnings_' . $module_slug, $dependency_check['warnings'], 30);
                        }
                    }
                    
                    // Use Config class for activation
                    $activation_success = Config::set_module_setting($module_slug, 'active', true);
                    if (!$activation_success) {
                        error_log("[SEWN] Failed to set module active status: {$module_slug}");
                        throw new \Exception('Failed to set module active status');
                    }
                    
                    // Initialize the module
                    if (method_exists($module, 'init')) {
                        error_log("[SEWN] Initializing module: {$module_slug}");
                        $module->init();
                    }
                    
                    error_log("[SEWN] Successfully activated module: {$module_slug}");
                    
                    // Set transient for admin notice
                    set_transient('sewn_module_activated', [
                        'module' => $module_slug,
                        'has_warnings' => $has_warnings
                    ], 30);
                    
                } catch (\Exception $e) {
                    error_log("[SEWN] Module activation failed: " . $e->getMessage());
                    // Set transient for error notice
                    set_transient('sewn_module_activation_error', [
                        'module' => $module_slug,
                        'error' => $e->getMessage()
                    ], 30);
                    
                    // Attempt to cleanup failed activation
                    Config::set_module_setting($module_slug, 'active', false);
                }
                break;
                
            case 'deactivate':
                error_log("[SEWN] Deactivating module: {$module_slug}");
                try {
                    // Call deactivate method if it exists
                    if (method_exists($module, 'deactivate')) {
                        $module->deactivate();
                    }
                    
                    Config::set_module_setting($module_slug, 'active', false);
                    error_log("[SEWN] Successfully deactivated module: {$module_slug}");
                    
                } catch (\Exception $e) {
                    error_log("[SEWN] Module deactivation failed: " . $e->getMessage());
                }
                break;
        }

        // Clear module status cache
        wp_cache_delete('sewn_ws_active_modules', 'sewn_ws');
        
        // Clear individual module cache
        wp_cache_delete("sewn_module_{$module_slug}_active", 'sewn_ws');
    }

    /**
     * Register module settings with legacy support
     */
    public function register_module_settings() {
        foreach ($this->registry->get_modules() as $module) {
            if (!method_exists($module, 'admin_ui')) {
                continue;
            }

            // Create settings instance for this module
            $this->settings_handler = new \SEWN\WebSockets\Admin\Module_Settings_Base($module);
            $this->settings_handler->register_settings();
        }
    }

    /**
     * Load and render a view template
     *
     * @param string $template Template name without .php
     * @param array $data Data to pass to template
     * @return void
     */
    protected function render_view($template, $data = []) {
        $template_path = $this->view_path . $template . '.php';
        
        if (!file_exists($template_path)) {
            error_log("[SEWN] View template not found: {$template}");
            return;
        }

        // Extract data to make it available in template
        if (!empty($data)) {
            extract($data);
        }

        include $template_path;
    }

    public function render_modules() {
        $modules = $this->registry->get_modules();
        $module_errors = [];
        
        if ($this->should_show_module_errors()) {
            foreach ($modules as $module) {
                $module_slug = $module->get_module_slug();
                if ($this->is_module_active($module_slug)) {
                    $module_errors[$module_slug] = $this->check_module_dependencies($module);
                }
            }
        }
        
        $this->render_view('modules-list', [
            'modules' => $modules,
            'module_errors' => $module_errors,
            'registry' => $this->registry
        ]);
    }

    public function render_settings() {
        $this->render_view('settings');
    }

    public function render_dashboard() {
        $this->render_view('dashboard');
    }

    public function register_module_pages() {
        foreach ($this->registry->get_modules() as $module) {
            if (!method_exists($module, 'admin_ui')) {
                continue;
            }

            $settings = $module->admin_ui();
            $module_slug = $module->get_module_slug();
            $page_title = $settings['menu_title'] ?? __('Module Settings', 'sewn-ws');
            $menu_title = $settings['menu_title'] ?? __('Module Settings', 'sewn-ws');
            $capability = $settings['capability'] ?? 'manage_options';
            $menu_slug = 'sewn-ws-module-' . $module_slug;

            add_submenu_page(
                'sewn-ws', // Parent slug
                $page_title,
                $menu_title,
                $capability,
                $menu_slug,
                [$this, 'render_module_settings_page']
            );
        }
    }

    public function sanitize_setting($value) {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return absint($value);
        }
        
        return sanitize_text_field($value);
    }

    private function add_standard_base_settings($module_slug, $option_group) {
        // Core Settings - Left Column
        add_settings_section(
            'module_info',
            __('Module Information', 'sewn-ws'),
            [$this, 'render_module_info_section'],
            $option_group . '_core_left'
        );

        add_settings_section(
            'access_control',
            __('Access Control', 'sewn-ws'),
            [$this, 'render_access_section'],
            $option_group . '_core_left'
        );

        // Core Settings - Right Column
        add_settings_section(
            'debug_settings',
            __('Debug Settings', 'sewn-ws'),
            null,
            $option_group . '_core_right'
        );

        // Performance - Left Column
        add_settings_section(
            'cache_settings',
            __('Cache Settings', 'sewn-ws'),
            [$this, 'render_cache_section'],
            $option_group . '_performance_left'
        );

        // Performance - Right Column
        add_settings_section(
            'rate_limits',
            __('Rate Limits', 'sewn-ws'),
            [$this, 'render_rate_section'],
            $option_group . '_performance_right'
        );

        // Module Information Fields
        add_settings_field(
            'module_status_indicator',
            __('Module Status', 'sewn-ws'),
            [$this, 'render_status_indicator'],
            $option_group . '_core_left',
            'module_info',
            ['module_slug' => $module_slug]
        );

        // Access Control Fields
        add_settings_field(
            'access_level',
            __('Access Level', 'sewn-ws'),
            [$this, 'render_setting_field'],
            $option_group . '_core_left',
            'access_control',
            [
                'type' => 'select',
                'name' => "sewn_ws_module_{$module_slug}_access_level",
                'options' => [
                    'public' => __('Public Access', 'sewn-ws'),
                    'registered' => __('Registered Users', 'sewn-ws'),
                    'premium' => __('Premium Members', 'sewn-ws')
                ],
                'description' => __('Select who can access this module', 'sewn-ws')
            ]
        );

        // Debug Settings Fields
        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'sewn-ws'),
            [$this, 'render_setting_field'],
            $option_group . '_core_right',
            'debug_settings',
            [
                'type' => 'checkbox',
                'name' => "sewn_ws_module_{$module_slug}_debug",
                'description' => __('Enable debug logging for this module', 'sewn-ws')
            ]
        );

        add_settings_field(
            'log_level',
            __('Log Level', 'sewn-ws'),
            [$this, 'render_setting_field'],
            $option_group . '_core_right',
            'debug_settings',
            [
                'type' => 'select',
                'name' => "sewn_ws_module_{$module_slug}_log_level",
                'options' => [
                    'error' => __('Errors Only', 'sewn-ws'),
                    'warning' => __('Warnings & Errors', 'sewn-ws'),
                    'info' => __('Info & Above', 'sewn-ws'),
                    'debug' => __('Debug & Above', 'sewn-ws')
                ],
                'description' => __('Select logging detail level', 'sewn-ws'),
                'depends_on' => "sewn_ws_module_{$module_slug}_debug"
            ]
        );

        // Cache Settings Fields
        add_settings_field(
            'cache_enabled',
            __('Enable Caching', 'sewn-ws'),
            [$this, 'render_setting_field'],
            $option_group . '_performance_left',
            'cache_settings',
            [
                'type' => 'checkbox',
                'name' => "sewn_ws_module_{$module_slug}_cache_enabled",
                'description' => __('Enable data caching for better performance', 'sewn-ws')
            ]
        );

        add_settings_field(
            'cache_ttl',
            __('Cache Duration', 'sewn-ws'),
            [$this, 'render_setting_field'],
            $option_group . '_performance_left',
            'cache_settings',
            [
                'type' => 'select',
                'name' => "sewn_ws_module_{$module_slug}_cache_ttl",
                'options' => [
                    '300' => __('5 minutes', 'sewn-ws'),
                    '900' => __('15 minutes', 'sewn-ws'),
                    '3600' => __('1 hour', 'sewn-ws'),
                    '86400' => __('24 hours', 'sewn-ws')
                ],
                'description' => __('How long to keep cached data', 'sewn-ws'),
                'depends_on' => "sewn_ws_module_{$module_slug}_cache_enabled"
            ]
        );

        // Rate Limit Fields
        add_settings_field(
            'rate_limit_enabled',
            __('Enable Rate Limiting', 'sewn-ws'),
            [$this, 'render_setting_field'],
            $option_group . '_performance_right',
            'rate_limits',
            [
                'type' => 'checkbox',
                'name' => "sewn_ws_module_{$module_slug}_rate_limit_enabled",
                'description' => __('Enable request rate limiting', 'sewn-ws')
            ]
        );

        add_settings_field(
            'rate_limit_requests',
            __('Max Requests', 'sewn-ws'),
            [$this, 'render_setting_field'],
            $option_group . '_performance_right',
            'rate_limits',
            [
                'type' => 'number',
                'name' => "sewn_ws_module_{$module_slug}_rate_limit_requests",
                'description' => __('Maximum requests per time window', 'sewn-ws'),
                'depends_on' => "sewn_ws_module_{$module_slug}_rate_limit_enabled"
            ]
        );

        add_settings_field(
            'rate_limit_window',
            __('Time Window', 'sewn-ws'),
            [$this, 'render_setting_field'],
            $option_group . '_performance_right',
            'rate_limits',
            [
                'type' => 'select',
                'name' => "sewn_ws_module_{$module_slug}_rate_limit_window",
                'options' => [
                    '60' => __('1 minute', 'sewn-ws'),
                    '300' => __('5 minutes', 'sewn-ws'),
                    '3600' => __('1 hour', 'sewn-ws'),
                    '86400' => __('24 hours', 'sewn-ws')
                ],
                'description' => __('Time window for rate limiting', 'sewn-ws'),
                'depends_on' => "sewn_ws_module_{$module_slug}_rate_limit_enabled"
            ]
        );
    }

    public function render_module_info_section($args) {
        $module_slug = str_replace('sewn_ws_module_', '', $args['id']);
        $module = $this->registry->get_module($module_slug);
        if (!$module) return;

        $meta = $module->metadata();
        $is_active = $module->is_active();
        ?>
        <div class="module-status <?php echo $is_active ? 'active' : 'inactive'; ?>">
            <span class="status-indicator"></span>
            <span class="status-text">
                <?php echo $is_active ? __('Active', 'sewn-ws') : __('Inactive', 'sewn-ws'); ?>
            </span>
        </div>
        <div class="module-meta">
            <p><strong><?php _e('Version:', 'sewn-ws'); ?></strong> <?php echo esc_html($meta['version']); ?></p>
            <p><strong><?php _e('Author:', 'sewn-ws'); ?></strong> <?php echo esc_html($meta['author']); ?></p>
        </div>
        <?php
    }

    public function render_access_section() {
        echo '<p>' . esc_html__('Configure who can access and use this module.', 'sewn-ws') . '</p>';
    }

    public function render_cache_section() {
        echo '<p>' . esc_html__('Configure caching settings to optimize performance.', 'sewn-ws') . '</p>';
    }

    public function render_rate_section() {
        echo '<p>' . esc_html__('Set rate limits to prevent abuse.', 'sewn-ws') . '</p>';
    }

    public function handle_stats_request() {
        check_ajax_referer(\SEWN_WS_NONCE_ACTION, 'nonce');
        
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
            defined('SEWN_WS_DEFAULT_PORT') ? SEWN_WS_DEFAULT_PORT : 49200 // Use IANA Dynamic Port range
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
        $modules = $this->registry->get_modules();
        $module_errors = [];
        
        if ($this->should_show_module_errors()) {
            foreach ($modules as $module) {
                $module_slug = $module->get_module_slug();
                if ($this->is_module_active($module_slug)) {
                    $module_errors[$module_slug] = $this->check_module_dependencies($module);
                }
            }
        }
        
        $this->render_view('modules-list', [
            'modules' => $modules,
            'module_errors' => $module_errors,
            'registry' => $this->registry
        ]);
    }

    private function check_module_dependencies($module) {
        if (!method_exists($module, 'check_dependencies')) {
            return [];
        }

        $dependency_errors = $module->check_dependencies();
        
        // Don't add admin notices, just return the errors
        return is_array($dependency_errors) ? $dependency_errors : [];
    }

    /**
     * Render module settings page
     */
    public function render_module_settings_page() {
        $module_slug = $this->get_current_module_slug();
        $module = $this->registry->get_module($module_slug);
        
        if (!$module || !$module instanceof Module_Base) {
            wp_die(__('Invalid module or module not loaded', 'sewn-ws'));
        }

        // Create settings instance for this module
        $this->settings_handler = new \SEWN\WebSockets\Admin\Module_Settings_Base($module);
        $this->settings_handler->render();
    }

    private function get_current_module_slug() {
        $screen = get_current_screen();
        if (!$screen) return '';
        
        // Handle both possible formats
        $patterns = [
            '/^websockets_page_sewn-ws-module-(.+)$/',
            '/^admin_page_sewn-ws-module-(.+)$/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $screen->id, $matches)) {
                return $matches[1];
            }
        }
        
        return '';
    }

    public function render_status_indicator($args) {
        $module_slug = $args['module_slug'];
        $is_active = $this->is_module_active($module_slug);
        $status_class = $is_active ? 'active' : 'inactive';
        ?>
        <div class="module-status <?php echo esc_attr($status_class); ?>">
            <span class="status-indicator"></span>
            <span class="status-text">
                <?php echo $is_active ? esc_html__('Active', 'sewn-ws') : esc_html__('Inactive', 'sewn-ws'); ?>
            </span>
        </div>
        <?php
    }

    /**
     * Check if module is active with caching
     *
     * @param string $module_slug
     * @return bool
     */
    private function is_module_active($module_slug) {
        $cache_key = "sewn_module_{$module_slug}_active";
        $cached = wp_cache_get($cache_key, 'sewn_ws');
        
        if ($cached !== false) {
            return (bool)$cached;
        }
        
        $status = (bool)get_option($cache_key, false);
        wp_cache_set($cache_key, $status, 'sewn_ws', HOUR_IN_SECONDS);
        
        return $status;
    }
}