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
            $option_name = 'sewn_ws_module_' . $module_slug . '_settings';

            // Register standard base settings for all modules
            register_setting($option_group, $option_name, [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_module_settings']
            ]);

            // Add base settings (combines general, performance and security)
            $this->add_standard_base_settings($module_slug, $option_group);

            // Add module-specific settings
            $settings = $module->admin_ui();
            if (!empty($settings['sections'])) {
                foreach ($settings['sections'] as $section) {
                add_settings_section(
                    $section['id'],
                    $section['title'],
                        $section['callback'] ?? null,
                        $option_group
                );
                }
            }

            if (!empty($settings['settings'])) {
                foreach ($settings['settings'] as $setting) {
                add_settings_field(
                    $setting['name'],
                    $setting['label'],
                    [$this, 'render_setting_field'],
                        $option_group,
                        $setting['section'] ?? 'general',
                        [
                            'module' => $module_slug,
                            'setting' => $setting
                        ]
                    );
                }
            }
        }
    }

    private function add_standard_base_settings($module_slug, $option_group) {
        // Core Settings Tab - Left Column
        add_settings_section(
            'core_left',
            __('Module Information', 'sewn-ws'),
            [$this, 'render_module_info_section'],
            $option_group
        );

        // Core Settings Tab - Right Column
        add_settings_section(
            'core_right',
            __('Access Control', 'sewn-ws'),
            [$this, 'render_access_section'],
            $option_group
        );

        // Performance Tab - Left Column
        add_settings_section(
            'performance_left',
            __('Caching', 'sewn-ws'),
            [$this, 'render_cache_section'],
            $option_group
        );

        // Performance Tab - Right Column
        add_settings_section(
            'performance_right',
            __('Rate Limiting', 'sewn-ws'),
            [$this, 'render_rate_section'],
            $option_group
        );

        // Core Settings
        $core_settings = [
            // Left Column
            'core_left' => [
                [
                    'name' => 'debug_mode',
                    'label' => __('Debug Mode', 'sewn-ws'),
                    'type' => 'checkbox',
                    'description' => __('Enable detailed logging for troubleshooting', 'sewn-ws'),
                    'sanitize' => 'rest_sanitize_boolean'
                ]
            ],
            // Right Column
            'core_right' => [
                [
                    'name' => 'access_level',
                    'label' => __('Required Capability', 'sewn-ws'),
                    'type' => 'select',
                    'description' => __('Minimum capability required to use this module', 'sewn-ws'),
                    'options' => [
                        'manage_options' => __('Administrator', 'sewn-ws'),
                        'edit_pages' => __('Editor', 'sewn-ws'),
                        'publish_posts' => __('Author', 'sewn-ws')
                    ],
                    'sanitize' => 'sanitize_text_field'
                ]
            ]
        ];

        // Performance Settings
        $performance_settings = [
            // Left Column
            'performance_left' => [
                [
                    'name' => 'cache_enabled',
                    'label' => __('Enable Caching', 'sewn-ws'),
                    'type' => 'checkbox',
                    'description' => __('Cache responses to improve performance', 'sewn-ws'),
                    'sanitize' => 'rest_sanitize_boolean'
                ],
                [
                    'name' => 'cache_ttl',
                    'label' => __('Cache Duration', 'sewn-ws'),
                    'type' => 'select',
                    'description' => __('How long to keep cached data', 'sewn-ws'),
                    'options' => [
                        '300' => __('5 minutes', 'sewn-ws'),
                        '3600' => __('1 hour', 'sewn-ws'),
                        '86400' => __('24 hours', 'sewn-ws')
                    ],
                    'sanitize' => 'absint',
                    'depends_on' => 'cache_enabled'
                ]
            ],
            // Right Column
            'performance_right' => [
                [
                    'name' => 'rate_limit',
                    'label' => __('Request Limit', 'sewn-ws'),
                    'type' => 'number',
                    'description' => __('Maximum requests per minute (0 for unlimited)', 'sewn-ws'),
                    'sanitize' => 'absint',
                    'min' => 0,
                    'max' => 1000
                ]
            ]
        ];

        // Register all settings
        foreach ([$core_settings, $performance_settings] as $tab_settings) {
            foreach ($tab_settings as $section => $settings) {
                foreach ($settings as $setting) {
                    $setting_name = 'sewn_ws_' . $module_slug . '_' . $setting['name'];
                    register_setting($option_group, $setting_name);
                    add_settings_field(
                        $setting_name,
                        $setting['label'],
                        [$this, 'render_setting_field'],
                        $option_group,
                        $section,
                        [
                            'module' => $module_slug,
                            'setting' => $setting
                        ]
                    );
                }
            }
        }
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
        $module = $args['module'];
        $setting = $args['setting'];
        $name = 'sewn_ws_' . $module . '_' . $setting['name'];
        $value = get_option($name);
        $depends_on = isset($setting['depends_on']) ? 'data-depends-on="sewn_ws_' . $module . '_' . $setting['depends_on'] . '"' : '';
        
        switch ($setting['type']) {
            case 'checkbox':
                printf(
                    '<label><input type="checkbox" name="%s" value="1" %s %s> %s</label>',
                    esc_attr($name),
                    checked($value, '1', false),
                    $depends_on,
                    esc_html($setting['description'])
                );
                break;
                
            case 'select':
                printf('<select name="%s" %s>', esc_attr($name), $depends_on);
                foreach ($setting['options'] as $option_value => $option_label) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($option_value),
                        selected($value, $option_value, false),
                        esc_html($option_label)
                    );
                }
                echo '</select>';
                echo '<p class="description">' . esc_html($setting['description']) . '</p>';
                break;
                
            case 'number':
                printf(
                    '<input type="number" name="%s" value="%s" min="%d" max="%d" %s>',
                    esc_attr($name),
                    esc_attr($value),
                    $setting['min'] ?? 0,
                    $setting['max'] ?? 999999,
                    $depends_on
                );
                echo '<p class="description">' . esc_html($setting['description']) . '</p>';
                break;
                
            default:
                printf(
                    '<input type="text" name="%s" value="%s" class="regular-text" %s>',
                    esc_attr($name),
                    esc_attr($value),
                    $depends_on
                );
                echo '<p class="description">' . esc_html($setting['description']) . '</p>';
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
        // Get module slug and create option group
        $module_slug = $this->get_current_module_slug();
        $option_group = 'sewn_ws_module_' . $module_slug;

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="#tab-core" class="nav-tab nav-tab-active" data-tab="core">
                    <?php _e('Core Settings', 'sewn-ws'); ?>
                </a>
                <a href="#tab-performance" class="nav-tab" data-tab="performance">
                    <?php _e('Performance', 'sewn-ws'); ?>
                </a>
            </h2>

            <form method="post" action="options.php">
                <?php settings_fields($option_group); ?>
                
                <div class="sewn-ws-tab-content" id="tab-core">
                    <div class="sewn-ws-columns">
                        <div class="sewn-ws-column">
                            <?php do_settings_sections($option_group . '_core_left'); ?>
                        </div>
                        <div class="sewn-ws-column">
                            <?php do_settings_sections($option_group . '_core_right'); ?>
                        </div>
                    </div>
                </div>

                <div class="sewn-ws-tab-content" id="tab-performance" style="display: none;">
                    <div class="sewn-ws-columns">
                        <div class="sewn-ws-column">
                            <?php do_settings_sections($option_group . '_performance_left'); ?>
                        </div>
                        <div class="sewn-ws-column">
                            <?php do_settings_sections($option_group . '_performance_right'); ?>
                        </div>
                    </div>
                </div>

                <hr class="sewn-ws-section-separator" />
                
                <h3><?php _e('Module Specific Settings', 'sewn-ws'); ?></h3>
                <?php
                // Get module instance
                $module = $this->registry->get_module($module_slug);
                
                // Render custom module settings
                $custom_settings = $module->admin_ui()['settings'] ?? [];
                if (!empty($custom_settings)) {
                    echo '<table class="form-table">';
                    foreach ($custom_settings as $setting) {
                        echo '<tr>';
                        echo '<th scope="row">' . esc_html($setting['label']) . '</th>';
                        echo '<td>';
                        $this->render_setting_field([
                            'module' => $module_slug,
                            'setting' => $setting
                        ]);
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
                
                submit_button();
                ?>
            </form>
        </div>

        <style>
        .sewn-ws-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 20px 0;
        }

        .sewn-ws-section-separator {
            margin: 40px 0 20px;
            border-top: 1px solid #ccd0d4;
            border-bottom: 0;
        }

        .sewn-ws-tab-content:not(:first-child) {
            display: none;
        }

        .form-table th {
            width: 200px;
        }

        /* Add proper spacing between sections */
        .sewn-ws-column .form-table {
            margin-top: 0;
        }

        /* Ensure section titles are visible */
        .sewn-ws-column h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #ccd0d4;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                // Update tabs
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show/hide content
                $('.sewn-ws-tab-content').hide();
                $($(this).attr('href')).show();
            });
        });
        </script>
        <?php
    }

    private function get_current_module_slug() {
        $current_screen = get_current_screen();
        
        // Handle both possible screen ID formats
        $module_slug = '';
        if (strpos($current_screen->id, 'websockets_page_sewn-ws-module-') !== false) {
            $module_slug = str_replace('websockets_page_sewn-ws-module-', '', $current_screen->id);
        } else if (strpos($current_screen->id, 'admin_page_sewn-ws-module-') !== false) {
            $module_slug = str_replace('admin_page_sewn-ws-module-', '', $current_screen->id);
        }
        
        if (empty($module_slug)) {
            error_log('[SEWN WebSocket] Invalid screen ID format: ' . $current_screen->id);
            wp_die(__('Invalid module screen format', 'sewn-ws'));
        }

        return $module_slug;
    }
}