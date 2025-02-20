<?php
/**
 * Base class for module settings
 */

namespace SEWN\WebSockets\Admin;

use SEWN\WebSockets\Module_Base;
use SEWN\WebSockets\Config;

class Module_Settings_Base {
    protected $module;
    protected $settings;
    protected $config;
    protected $view_path;

    public function __construct(Module_Base $module) {
        $this->module = $module;
        $this->settings = $module->admin_ui();
        $this->view_path = plugin_dir_path(__FILE__) . 'views/';
        
        // Initialize Config using static methods directly
        $this->config = null; // We'll use Config::get_module_setting directly
    }

    /**
     * Get module slug safely
     *
     * @return string
     */
    protected function get_module_slug() {
        if (!$this->module instanceof Module_Base) {
            return '';
        }
        
        return $this->module->get_module_slug();
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

    /**
     * Get module setting with Config class integration
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function get_setting($key, $default = null) {
        $module_slug = $this->get_module_slug();
        if (empty($module_slug)) {
            return $default;
        }
        return Config::get_module_setting($module_slug, $key, $default);
    }

    /**
     * Save module setting with Config class integration
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool
     */
    protected function save_setting($key, $value) {
        $module_slug = $this->get_module_slug();
        if (empty($module_slug)) {
            return false;
        }
        return Config::set_module_setting($module_slug, $key, $value);
    }

    /**
     * Register module settings with WordPress
     *
     * @return void
     */
    public function register_settings() {
        $module_slug = $this->get_module_slug();
        $option_group = "sewn_ws_module_{$module_slug}";
        
        // Get settings array from module's admin_ui configuration
        $admin_ui = $this->settings;
        if (empty($admin_ui['settings']) || !is_array($admin_ui['settings'])) {
            error_log("[SEWN] No settings defined for module {$module_slug}");
            return;
        }

        foreach ($admin_ui['settings'] as $setting) {
            // Validate setting structure
            if (empty($setting['name'])) {
                error_log("[SEWN] Warning: Setting missing required 'name' field in module {$module_slug}");
                continue;
            }
            
            $setting_id = $setting['name'];
            
            register_setting(
                $option_group,
                $setting_id,
                [
                    'type' => $setting['type'] ?? 'string',
                    'sanitize_callback' => $setting['sanitize'] ?? [$this, 'sanitize_setting'],
                    'default' => $this->get_setting($setting_id, $setting['default'] ?? null)
                ]
            );

            // Register setting section if not already registered
            $section_id = $setting['section'] ?? 'general';
            $this->register_setting_section($option_group, $section_id, $admin_ui['sections'] ?? []);
            
            // Add field to appropriate section
            add_settings_field(
                $setting_id,
                $setting['label'],
                [$this, 'render_field'],
                $option_group,
                $section_id,
                $setting
            );
        }
    }

    protected function register_setting_section($option_group, $section_id, $sections) {
        static $registered_sections = [];
        
        // Skip if already registered
        if (isset($registered_sections[$option_group][$section_id])) {
            return;
        }
        
        // Find section config
        $section_config = array_filter($sections, function($s) use ($section_id) {
            return $s['id'] === $section_id;
        });
        $section_config = reset($section_config);
        
        // Register section
        add_settings_section(
            $section_id,
            $section_config['title'] ?? __('General Settings', 'sewn-ws'),
            $section_config['callback'] ?? null,
            $option_group
        );
        
        $registered_sections[$option_group][$section_id] = true;
    }

    public function render_field($setting) {
        $type = $setting['type'] ?? 'text';
        $name = $setting['name'];
        $value = get_option($name, $setting['default'] ?? '');
        
        switch ($type) {
            case 'password':
                printf(
                    '<input type="password" id="%1$s" name="%1$s" value="%2$s" class="regular-text">',
                    esc_attr($name),
                    esc_attr($value)
                );
                break;
                
            case 'checkbox':
                printf(
                    '<input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s>',
                    esc_attr($name),
                    checked($value, '1', false)
                );
                break;
                
            case 'textarea':
                printf(
                    '<textarea id="%1$s" name="%1$s" class="large-text" rows="5">%2$s</textarea>',
                    esc_attr($name),
                    esc_textarea($value)
                );
                break;
                
            case 'select':
                if (!empty($setting['options'])) {
                    printf('<select id="%1$s" name="%1$s">', esc_attr($name));
                    foreach ($setting['options'] as $option_value => $option_label) {
                        printf(
                            '<option value="%1$s" %2$s>%3$s</option>',
                            esc_attr($option_value),
                            selected($value, $option_value, false),
                            esc_html($option_label)
                        );
                    }
                    echo '</select>';
                }
                break;
                
            default:
                printf(
                    '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text">',
                    esc_attr($name),
                    esc_attr($value)
                );
        }
        
        if (!empty($setting['description'])) {
            printf(
                '<p class="description">%s</p>',
                esc_html($setting['description'])
            );
        }
    }

    /**
     * Standard setting sanitization
     *
     * @param mixed $value Value to sanitize
     * @return mixed
     */
    protected function sanitize_setting($value) {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return absint($value);
        }
        
        return sanitize_text_field($value);
    }

    public function render() {
        $module_data = $this->module->metadata();
        $this->render_view('module-settings-wrapper', [
            'module_data' => $module_data,
            'settings' => $this->settings,
            'module_slug' => $this->get_module_slug()
        ]);
    }

    protected function render_module_info($module_data) {
        ?>
        <div class="sewn-ws-module-info">
            <h2><?php echo esc_html($module_data['name']); ?></h2>
            <p class="description"><?php echo esc_html($module_data['description']); ?></p>
            <p class="version"><?php printf(__('Version: %s', 'sewn-ws'), esc_html($module_data['version'])); ?></p>
            <?php if (!empty($module_data['author'])): ?>
                <p class="author"><?php printf(__('Author: %s', 'sewn-ws'), esc_html($module_data['author'])); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    protected function render_settings_sections() {
        ?>
        <div class="sewn-ws-settings-sections">
            <?php
            $this->render_general_section();
            $this->render_custom_sections();
            $this->render_debug_section();
            ?>
        </div>
        <?php
    }

    protected function render_general_section() {
        ?>
        <div class="sewn-ws-section sewn-ws-section-general">
            <h2><?php _e('General Settings', 'sewn-ws'); ?></h2>
            <table class="form-table">
                <?php $this->render_section_fields('general'); ?>
            </table>
        </div>
        <?php
    }

    protected function render_custom_sections() {
        if (empty($this->settings['sections'])) {
            return;
        }

        foreach ($this->settings['sections'] as $section) {
            if ($section['id'] === 'general') {
                continue;
            }
            ?>
            <div class="sewn-ws-section sewn-ws-section-<?php echo esc_attr($section['id']); ?>">
                <h2><?php echo esc_html($section['title']); ?></h2>
                <?php 
                if (isset($section['callback'])) {
                    call_user_func($section['callback']);
                }
                ?>
                <table class="form-table">
                    <?php $this->render_section_fields($section['id']); ?>
                </table>
            </div>
            <?php
        }
    }

    protected function render_section_fields($section_id) {
        if (empty($this->settings['settings'])) {
            return;
        }

        foreach ($this->settings['settings'] as $setting) {
            if (($setting['section'] ?? 'general') === $section_id) {
                $this->render_setting_field($setting);
            }
        }
    }

    protected function render_setting_field($setting) {
        $type = $setting['type'] ?? 'text';
        $name = $setting['name'];
        $value = get_option($name);
        ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($setting['label']); ?></label>
            </th>
            <td>
                <?php
                switch ($type) {
                    case 'password':
                        ?>
                        <input type="password" 
                               id="<?php echo esc_attr($name); ?>"
                               name="<?php echo esc_attr($name); ?>"
                               value="<?php echo esc_attr($value); ?>"
                               class="regular-text">
                        <?php
                        break;
                    case 'checkbox':
                        ?>
                        <input type="checkbox"
                               id="<?php echo esc_attr($name); ?>"
                               name="<?php echo esc_attr($name); ?>"
                               value="1"
                               <?php checked($value, '1'); ?>>
                        <?php
                        break;
                    case 'textarea':
                        ?>
                        <textarea id="<?php echo esc_attr($name); ?>"
                                  name="<?php echo esc_attr($name); ?>"
                                  class="large-text"
                                  rows="5"><?php echo esc_textarea($value); ?></textarea>
                        <?php
                        break;
                    case 'select':
                        if (!empty($setting['options'])) {
                            ?>
                            <select id="<?php echo esc_attr($name); ?>"
                                    name="<?php echo esc_attr($name); ?>">
                                <?php foreach ($setting['options'] as $option_value => $option_label): ?>
                                    <option value="<?php echo esc_attr($option_value); ?>"
                                            <?php selected($value, $option_value); ?>>
                                        <?php echo esc_html($option_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php
                        }
                        break;
                    default:
                        ?>
                        <input type="text"
                               id="<?php echo esc_attr($name); ?>"
                               name="<?php echo esc_attr($name); ?>"
                               value="<?php echo esc_attr($value); ?>"
                               class="regular-text">
                        <?php
                }

                if (!empty($setting['description'])) {
                    echo '<p class="description">' . esc_html($setting['description']) . '</p>';
                }
                ?>
            </td>
        </tr>
        <?php
    }

    protected function render_debug_section() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        ?>
        <div class="sewn-ws-section sewn-ws-section-debug">
            <h2><?php _e('Debug Information', 'sewn-ws'); ?></h2>
            <div class="sewn-ws-debug-info">
                <pre><?php 
                    $debug_info = [
                        'Module' => $this->module->metadata(),
                        'Dependencies' => $this->module->requires(),
                        'Status' => $this->module->check_dependencies()
                    ];
                    echo esc_html(print_r($debug_info, true)); 
                ?></pre>
            </div>
        </div>
        <?php
    }

    protected function render_styles() {
        ?>
        <style>
        .sewn-ws-module-settings {
            max-width: 1200px;
            margin: 20px auto;
        }

        .sewn-ws-module-info {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }

        .sewn-ws-settings-sections {
            margin-top: 20px;
        }

        .sewn-ws-section {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }

        .sewn-ws-section h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .sewn-ws-debug-info {
            background: #f5f5f5;
            padding: 15px;
            border: 1px solid #ddd;
            overflow: auto;
        }
        </style>
        <?php
    }

    /**
     * Handle settings validation
     *
     * @param array $settings Settings to validate
     * @return array Validation results
     */
    protected function validate_settings($settings) {
        $errors = [];
        $module_slug = $this->get_module_slug();

        foreach ($this->settings['settings'] as $setting) {
            $key = $setting['name'] ?? '';
            if (empty($key)) continue;

            if (!empty($setting['required']) && empty($settings[$key])) {
                $errors[] = sprintf(__('Setting %s is required', 'sewn-ws'), $setting['label']);
            }

            if (!empty($setting['validate']) && is_callable($setting['validate'])) {
                $result = call_user_func($setting['validate'], $settings[$key]);
                if ($result !== true) {
                    $errors[] = $result;
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
} 