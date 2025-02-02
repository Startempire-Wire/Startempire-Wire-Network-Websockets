<?php

namespace SEWN\WebSockets\Admin;

use SEWN\WebSockets\Module_Base;
use SEWN\WebSockets\Module_Registry;

class Module_Admin {
    private $registry;

    public function __construct(Module_Registry $registry) {
        $this->registry = $registry;
        add_action('admin_menu', [$this, 'add_menu_items']);
        add_action('admin_init', [$this, 'handle_module_actions']);
        add_action('admin_init', [$this, 'register_module_settings']);
        add_action('wp_ajax_sewn_ws_get_stats', [$this, 'handle_stats_request']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function add_menu_items() {
        add_menu_page(
            'SEWN WebSockets',
            'WebSockets',
            'manage_options',
            'sewn-ws',
            [$this, 'render_dashboard'],
            'dashicons-networking'
        );
    
        add_submenu_page(
            'sewn-ws',
            __('Modules', 'sewn-ws'),
            __('Modules', 'sewn-ws'),
            'manage_options',
            'sewn-ws-modules',
            [$this, 'render_modules_page']
        );

        // Add hidden submenu for module settings
        add_submenu_page(
            null, // Don't add to menu
            __('Module Settings', 'sewn-ws'),
            __('Module Settings', 'sewn-ws'),
            'manage_options',
            'sewn-ws-module-settings',
            [$this, 'render_settings_page']
        );
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
                $module->init();
                break;
                
            case 'deactivate':
                update_option("sewn_module_{$module_slug}_active", false);
                if (method_exists($module, 'deactivate')) {
                    $module->deactivate();
                }
                break;
        }
    }

    public function render_modules_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WebSockets Modules', 'sewn-ws'); ?></h1>
            <div class="sewn-modules-grid">
                <?php foreach ($this->registry->get_modules() as $module) : 
                    $meta = $module->metadata();
                    $is_active = $module->is_active();
                ?>
                <div class="sewn-module-card card <?php echo $is_active ? 'active' : 'inactive'; ?>">
                    <div class="module-header">
                        <h2><?php echo esc_html($meta['name']); ?></h2>
                        <span class="version">v<?php echo esc_html($meta['version']); ?></span>
                    </div>
                    <div class="module-content">
                        <p class="description"><?php echo esc_html($meta['description']); ?></p>
                        
                        <?php if (!empty($meta['dependencies'])) : ?>
                        <div class="dependencies">
                            <strong><?php esc_html_e('Requires:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html(implode(', ', $meta['dependencies'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="module-actions">
                        <form method="post">
                            <?php wp_nonce_field('sewn_module_control'); ?>
                            <input type="hidden" name="module_slug" value="<?php echo esc_attr($meta['slug']); ?>">
                            
                            <?php if ($is_active) : ?>
                                <button type="submit" name="sewn_module_action" value="deactivate" 
                                    class="button button-secondary">
                                    <?php esc_html_e('Deactivate', 'sewn-ws'); ?>
                                </button>
                            <?php else : ?>
                                <button type="submit" name="sewn_module_action" value="activate" 
                                    class="button button-primary">
                                    <?php esc_html_e('Activate', 'sewn-ws'); ?>
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <style>
            .sewn-modules-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 1rem;
            }
            .sewn-module-card {
                padding: 1rem;
                border: 1px solid #ccd0d4;
            }
            .sewn-module-card.active {
                border-left: 4px solid #00a32a;
            }
            .module-header {
                display: flex;
                justify-content: space-between;
                margin-bottom: 1rem;
            }
            .module-actions {
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid #ccd0d4;
            }
        </style>
        <?php
    }

    public function register_module_settings() {
        foreach ($this->registry->get_modules() as $module) {
            if (!$module->is_active()) continue;
            
            $config = $module->admin_ui();
            $module_slug = $module->metadata()['slug'];
            
            register_setting(
                "sewn_ws_module_{$module_slug}",
                "sewn_ws_module_{$module_slug}_settings",
                ['sanitize_callback' => [$this, 'sanitize_module_settings']]
            );

            foreach ($config['sections'] as $section) {
                add_settings_section(
                    $section['id'],
                    $section['title'],
                    $section['callback'] ?? null,
                    "sewn_ws_module_{$module_slug}"
                );
            }

            foreach ($config['settings'] as $setting) {
                add_settings_field(
                    $setting['name'],
                    $setting['label'],
                    [$this, 'render_setting_field'],
                    "sewn_ws_module_{$module_slug}",
                    $setting['section'] ?? 'default',
                    [
                        'module' => $module_slug,
                        'name' => $setting['name'],
                        'type' => $setting['type'] ?? 'text',
                        'options' => $setting['options'] ?? []
                    ]
                );
            }
        }
    }

    public function render_setting_field($args) {
        $value = get_option("sewn_ws_module_{$args['module']}_settings")[$args['name']] ?? '';
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

    public function render_settings_page() {
        $module_slug = isset($_GET['module']) ? sanitize_key($_GET['module']) : '';
        $module = $this->registry->get_module($module_slug);
        
        if (!$module instanceof Module_Base) {
            wp_die(__('Invalid module', 'sewn-ws'));
        }
        
        // Pass variables to the view
        include plugin_dir_path(__DIR__) . 'admin/views/module-settings.php';
    }

    public function handle_stats_request() {
        check_ajax_referer('sewn_ws_nonce', 'nonce');
        
        wp_send_json_success([
            'connections' => get_transient('sewn_ws_connections'),
            'status' => get_option('sewn_ws_server_status')
        ]);
    }

    public function enqueue_scripts() {
        wp_localize_script('sewn-ws-admin', 'sewn_ws_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sewn_ws_nonce'),
            // ... other vars ...
        ]);
    }
}