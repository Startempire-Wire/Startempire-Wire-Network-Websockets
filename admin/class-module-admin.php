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

    public function handle_module_actions() {
        if (!isset($_POST['sewn_module_action']) || !check_admin_referer('sewn_module_control')) {
            return;
        }

        $module_slug = sanitize_key($_POST['module_slug']);
        $module = $this->registry->get_module($module_slug);
        
        if (!$module instanceof Module_Base) {
            return;
        }

        $option_name = "sewn_module_{$module_slug}_active";
        
        switch ($_POST['sewn_module_action']) {
            case 'activate':
                // Use autoload=no for better performance
                $this->update_module_status($module_slug, true);
                if (method_exists($module, 'init')) {
                    $module->init();
                }
                break;
                
            case 'deactivate':
                $this->update_module_status($module_slug, false);
                break;
        }

        // Clear module status cache
        wp_cache_delete('sewn_ws_active_modules', 'sewn_ws');
    }

    /**
     * Optimized module status update
     *
     * @param string $module_slug
     * @param bool $status
     */
    private function update_module_status($module_slug, $status) {
        global $wpdb;
        
        $option_name = "sewn_module_{$module_slug}_active";
        
        // First try to update existing option
        $result = $wpdb->update(
            $wpdb->options,
            [
                'option_value' => $status ? '1' : '',
                'autoload' => 'no'  // Set autoload=no for better performance
            ],
            ['option_name' => $option_name],
            ['%s', '%s'],
            ['%s']
        );
        
        // If option doesn't exist, insert it
        if ($result === false) {
            $wpdb->insert(
                $wpdb->options,
                [
                    'option_name' => $option_name,
                    'option_value' => $status ? '1' : '',
                    'autoload' => 'no'
                ],
                ['%s', '%s', '%s']
            );
        }
        
        // Update cache
        wp_cache_set($option_name, $status, 'sewn_ws', HOUR_IN_SECONDS);
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

    public function render_modules() {
        include_once dirname(__FILE__) . '/views/module-settings.php';
    }

    public function render_settings() {
        include_once dirname(__FILE__) . '/views/settings.php';
    }

    public function render_dashboard() {
        include_once dirname(__FILE__) . '/views/dashboard.php';
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

    public function register_module_settings() {
        foreach ($this->registry->get_modules() as $module) {
            if (!method_exists($module, 'admin_ui')) {
                continue;
            }

            $module_slug = $module->get_module_slug();
            $option_group = 'sewn_ws_module_' . $module_slug;

            // Register base settings
            $base_settings = [
                'debug', 'log_level', 'access_level',
                'cache_enabled', 'cache_ttl',
                'rate_limit_enabled', 'rate_limit_requests', 'rate_limit_window'
            ];

            foreach ($base_settings as $setting) {
                register_setting(
                    $option_group,
                    "sewn_ws_module_{$module_slug}_{$setting}",
                    [
                        'type' => 'string',
                        'sanitize_callback' => [$this, 'sanitize_setting']
                    ]
                );
            }

            // Add base settings sections and fields
            $this->add_standard_base_settings($module_slug, $option_group);

            // Get module-specific settings
            $ui = $module->admin_ui();

            // Register and add module-specific settings
            if (!empty($ui['sections'])) {
                foreach ($ui['sections'] as $section) {
                    // Register the section
                    add_settings_section(
                        $section['id'],
                        $section['title'],
                        $section['callback'] ?? null,
                        $option_group . '_module_settings'
                    );
                }
            }

            if (!empty($ui['settings'])) {
                foreach ($ui['settings'] as $setting) {
                    // Register the setting
                    register_setting(
                        $option_group,
                        $setting['name'],
                        [
                            'type' => 'string',
                            'sanitize_callback' => $setting['sanitize'] ?? [$this, 'sanitize_setting']
                        ]
                    );

                    // Add the settings field
                    add_settings_field(
                        $setting['name'],
                        $setting['label'],
                        [$this, 'render_setting_field'],
                        $option_group . '_module_settings',
                        $setting['section'],
                        [
                            'type' => $setting['type'],
                            'name' => $setting['name'],
                            'description' => $setting['description'],
                            'options' => $setting['options'] ?? [],
                            'depends_on' => $setting['depends_on'] ?? null
                        ]
                    );
                }
            }
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

    public function render_setting_field($args) {
        // Extract all needed values from args
        $type = $args['type'] ?? 'text';
        $name = $args['name'] ?? '';
        $description = $args['description'] ?? '';
        $options = $args['options'] ?? [];
        $depends_on = $args['depends_on'] ?? '';
        
        // Get the current value
        $value = get_option($name);
        
        // Add dependency attribute if needed
        $dependency_attr = $depends_on ? sprintf('data-depends-on="%s"', esc_attr($depends_on)) : '';
        
        switch ($type) {
            case 'checkbox':
                printf(
                    '<label><input type="checkbox" name="%s" value="1" %s %s> %s</label>',
                    esc_attr($name),
                    checked($value, '1', false),
                    $dependency_attr,
                    esc_html($description)
                );
                break;
                
            case 'select':
                printf('<select name="%s" %s>', esc_attr($name), $dependency_attr);
                foreach ($options as $option_value => $option_label) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($option_value),
                        selected($value, $option_value, false),
                        esc_html($option_label)
                    );
                }
                echo '</select>';
                if ($description) {
                    echo '<p class="description">' . esc_html($description) . '</p>';
                }
                break;
                
            case 'number':
                printf(
                    '<input type="number" name="%s" value="%s" %s>',
                    esc_attr($name),
                    esc_attr($value),
                    $dependency_attr
                );
                if ($description) {
                    echo '<p class="description">' . esc_html($description) . '</p>';
                }
                break;
                
            case 'password':
                printf(
                    '<input type="password" name="%s" value="%s" class="regular-text" %s>',
                    esc_attr($name),
                    esc_attr($value),
                    $dependency_attr
                );
                if ($description) {
                    echo '<p class="description">' . esc_html($description) . '</p>';
                }
                break;
                
            default:
                printf(
                    '<input type="text" name="%s" value="%s" class="regular-text" %s>',
                    esc_attr($name),
                    esc_attr($value),
                    $dependency_attr
                );
                if ($description) {
                    echo '<p class="description">' . esc_html($description) . '</p>';
                }
        }
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

    private function check_module_dependencies($module) {
        if (!method_exists($module, 'check_dependencies')) {
            return [];
        }

        $dependency_errors = $module->check_dependencies();
        
        // Don't add admin notices, just return the errors
        return is_array($dependency_errors) ? $dependency_errors : [];
    }

    public function render_module_settings_page() {
        $module_slug = $this->get_current_module_slug();
        $module = $this->registry->get_module($module_slug);
        
        if (!$module || !$module instanceof \SEWN\WebSockets\Module_Base) {
            wp_die(__('Invalid module or module not loaded', 'sewn-ws'));
        }

        $option_group = 'sewn_ws_module_' . $module_slug;
        $meta = $module->metadata();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($meta['name']); ?> <?php _e('Settings', 'sewn-ws'); ?></h1>

            <?php settings_errors(); ?>

            <div class="sewn-ws-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#core-settings" class="nav-tab nav-tab-active"><?php _e('Core Settings', 'sewn-ws'); ?></a>
                    <a href="#performance" class="nav-tab"><?php _e('Performance', 'sewn-ws'); ?></a>
                    <a href="#module-settings" class="nav-tab"><?php _e('Module Settings', 'sewn-ws'); ?></a>
                </nav>

                <form method="post" action="options.php">
                    <?php settings_fields($option_group); ?>
                    
                    <div id="core-settings" class="sewn-ws-tab-content active">
                        <div class="sewn-ws-settings-grid">
                            <div class="sewn-ws-settings-column">
                                <?php do_settings_sections($option_group . '_core_left'); ?>
                            </div>
                            <div class="sewn-ws-settings-column">
                                <?php do_settings_sections($option_group . '_core_right'); ?>
                            </div>
                        </div>
                    </div>

                    <div id="performance" class="sewn-ws-tab-content">
                        <div class="sewn-ws-settings-grid">
                            <div class="sewn-ws-settings-column">
                                <?php do_settings_sections($option_group . '_performance_left'); ?>
                            </div>
                            <div class="sewn-ws-settings-column">
                                <?php do_settings_sections($option_group . '_performance_right'); ?>
                            </div>
                        </div>
                    </div>

                    <div id="module-settings" class="sewn-ws-tab-content">
                        <?php 
                        $ui = $module->admin_ui();
                        if (!empty($ui['sections'])) {
                            foreach ($ui['sections'] as $section) {
                                do_settings_sections($option_group . '_' . $section['id']);
                            }
                        } else {
                            echo '<div class="notice notice-info inline"><p>';
                            _e('This module does not have any custom settings.', 'sewn-ws');
                            echo '</p></div>';
                        }
                        ?>
                    </div>

                    <?php submit_button(); ?>
                </form>
            </div>
        </div>

        <style>
            .sewn-ws-tabs {
                margin-top: 20px;
            }
            .sewn-ws-tab-content {
                display: none;
                padding: 20px;
                background: #fff;
                border: 1px solid #ccc;
                border-top: none;
            }
            .sewn-ws-tab-content.active {
                display: block;
            }
            .sewn-ws-settings-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
            }
            .sewn-ws-settings-column {
                min-width: 0;
            }
            .module-status {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 15px;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 4px;
            }
            .status-indicator {
                width: 10px;
                height: 10px;
                border-radius: 50%;
            }
            .module-status.active .status-indicator {
                background: #00a32a;
            }
            .module-status.inactive .status-indicator {
                background: #cc1818;
            }
            .notice.inline {
                margin: 0;
                padding: 10px;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.sewn-ws-tabs .nav-tab').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                
                // Update tabs
                $('.sewn-ws-tabs .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Update content
                $('.sewn-ws-tab-content').removeClass('active');
                $(target).addClass('active');
            });

            // Show/hide dependent fields
            $('input[type="checkbox"]').on('change', function() {
                var dependentFields = $('[data-depends-on="' + $(this).attr('name') + '"]');
                dependentFields.closest('tr').toggle(this.checked);
            }).trigger('change');
        });
        </script>
        <?php
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
}