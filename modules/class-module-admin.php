<?php

namespace SEWN\WebSockets\Admin;

use SEWN\WebSockets\Modules\Module_Registry;

class Module_Admin {
    private $registry;

    public function __construct(Module_Registry $registry) {
        $this->registry = $registry;
        add_action('admin_menu', [$this, 'add_menu_items']);
        add_action('admin_init', [$this, 'register_module_settings']);
    }
    
    public function add_menu_items() {
        foreach ($this->registry->get_modules() as $module) {
            add_submenu_page(
                'sewn-ws',
                $module->metadata()['name'],
                $module->metadata()['name'],
                'manage_options',
                'sewn-ws-'.$module->metadata()['slug'],
                [$this, 'render_settings_page']
            );
        }
    }
    
    public function render_settings_page() {
        $module_slug = str_replace('sewn-ws-', '', $_GET['page']);
        $module = $this->registry->get_module($module_slug);
        ?>
        <div class="wrap sewn-module-settings">
            <h1><?php echo esc_html($module->metadata()['name']); ?></h1>
            <div class="card">
                <p><?php echo esc_html($module->metadata()['description']); ?></p>
            </div>
            <form method="post" action="options.php">
                <?php $module->render_settings(); ?>
                <?php submit_button(); ?>
            </form>
            <?php do_action("sewn_ws_module_settings_{$module_slug}"); ?>
        </div>
        <?php
    }
    
    public function register_module_settings() {
        foreach ($this->registry->get_modules() as $module) {
            $config = $module->admin_ui();
            $slug = $module->metadata()['slug'];
            
            // Register sections
            foreach ($config['sections'] as $section) {
                add_settings_section(
                    $section['id'],
                    $section['title'],
                    $section['callback'] ?? null,
                    'sewn_ws_module_'.$slug
                );
            }
            
            // Register fields
            foreach ($config['settings'] as $setting) {
                add_settings_field(
                    $setting['name'],
                    $setting['label'],
                    [$this, 'render_setting_field'],
                    'sewn_ws_module_'.$slug,
                    $setting['section'] ?? 'default',
                    [
                        'name' => $setting['name'],
                        'type' => $setting['type'] ?? 'text',
                        'options' => $setting['options'] ?? []
                    ]
                );
                
                register_setting(
                    'sewn_ws_module_'.$slug,
                    $setting['name'],
                    $setting['sanitize']
                );
            }
        }
    }

    public function render_setting_field($args) {
        $value = get_option($args['name']);
        switch ($args['type']) {
            case 'password':
                echo '<input type="password" name="'.esc_attr($args['name']).'" 
                      value="'.esc_attr($value).'" class="regular-text">';
                break;
            case 'select':
                echo '<select name="'.esc_attr($args['name']).'">';
                foreach ($args['options'] as $key => $label) {
                    echo '<option value="'.esc_attr($key).'" '.selected($value, $key, false).'>'
                         .esc_html($label).'</option>';
                }
                echo '</select>';
                break;
            default:
                echo '<input type="text" name="'.esc_attr($args['name']).'" 
                      value="'.esc_attr($value).'" class="regular-text">';
        }
    }
}
