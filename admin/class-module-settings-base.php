<?php
/**
 * Base class for module settings
 */

namespace SEWN\WebSockets\Admin;

use SEWN\WebSockets\Module_Base;

class Module_Settings_Base {
    protected $module;
    protected $settings;

    public function __construct(Module_Base $module) {
        $this->module = $module;
        $this->settings = $module->admin_ui();
    }

    public function render() {
        $module_data = $this->module->metadata();
        ?>
        <div class="wrap sewn-ws-module-settings">
            <h1><?php echo esc_html($this->settings['menu_title'] ?? __('Module Settings', 'sewn-ws')); ?></h1>
            
            <?php $this->render_module_info($module_data); ?>

            <form method="post" action="options.php">
                <?php 
                settings_fields('sewn_ws_module_' . $this->module->get_module_slug());
                $this->render_settings_sections();
                submit_button(); 
                ?>
            </form>
        </div>
        <?php
        $this->render_styles();
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
} 