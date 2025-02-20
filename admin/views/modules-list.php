<?php
/**
 * LOCATION: admin/views/modules-list.php
 * DEPENDENCIES: Module_Registry class
 * VARIABLES: $modules (registered modules array)
 * CLASSES: None (template file)
 * 
 * Displays all registered WebSocket protocol modules with their activation status. Enables
 * network operators to manage WebRing feature compatibility and membership-tier restrictions.
 */ 

namespace SEWN\WebSockets\Admin;   

use SEWN\WebSockets\Config;
use SEWN\WebSockets\Module_Registry;

if (!defined('ABSPATH')) exit;

/** @var Module_Registry $module_registry */
/** @var array $modules */
/** @var array $module_errors */
?>
<div class="wrap">
    <h1><?php _e('WebSocket Modules', 'sewn-ws'); ?></h1>

    <?php 
    // Display activation success notice
    $activated_module = get_transient('sewn_module_activated');
    if ($activated_module) {
        $module_slug = $activated_module['module'];
        $has_warnings = $activated_module['has_warnings'];
        delete_transient('sewn_module_activated');
        
        // Show success notice
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php printf(__('Module "%s" activated successfully.', 'sewn-ws'), esc_html($module_slug)); ?></p>
        </div>
        <?php
        
        // If there are warnings, show them
        if ($has_warnings) {
            $warnings = get_transient('sewn_module_warnings_' . $module_slug);
            delete_transient('sewn_module_warnings_' . $module_slug);
            if ($warnings) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><strong><?php _e('Module activated with warnings:', 'sewn-ws'); ?></strong></p>
                    <ul>
                        <?php foreach ($warnings as $warning): ?>
                            <li><?php echo esc_html($warning['message']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php
            }
        }
    }

    // Display activation error notice
    $activation_error = get_transient('sewn_module_activation_error');
    if ($activation_error) {
        delete_transient('sewn_module_activation_error');
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php 
                printf(
                    __('Failed to activate module "%s": %s', 'sewn-ws'),
                    esc_html($activation_error['module']),
                    esc_html($activation_error['error'])
                ); 
                ?>
            </p>
        </div>
        <?php
    }
    
    // Check for modules with legacy settings
    $legacy_modules = [];
    foreach ($modules as $module) {
        $module_slug = method_exists($module, 'get_module_slug') ? 
            $module->get_module_slug() : 
            (property_exists($module, 'module_slug') ? $module->module_slug : 'unknown');
            
        if (Config::has_legacy_settings($module_slug)) {
            $legacy_modules[] = $module_slug;
        }
    }
    
    if (!empty($legacy_modules)): ?>
    <div class="notice notice-warning is-dismissible">
        <p>
            <?php 
            printf(
                __('Legacy settings detected for module(s): %s. These will be automatically migrated.', 'sewn-ws'),
                implode(', ', array_map('esc_html', $legacy_modules))
            ); 
            ?>
        </p>
    </div>
    <?php endif; ?>

    <div class="sewn-ws-modules-grid">
        <?php foreach ($modules as $module): ?>
            <?php 
            $module_slug = method_exists($module, 'get_module_slug') ? 
                $module->get_module_slug() : 
                (property_exists($module, 'module_slug') ? $module->module_slug : 'unknown');
                
            $is_active = Config::get_module_setting($module_slug, 'active', false);
            $status_class = $is_active ? 'active' : 'inactive';
            
            // Get module metadata
            $metadata = method_exists($module, 'metadata') ? $module->metadata() : [];
            $module_name = $metadata['name'] ?? ucfirst(str_replace('_', ' ', $module_slug));
            $module_version = $metadata['version'] ?? '1.0.0';
            $module_author = $metadata['author'] ?? 'Unknown';
            $module_description = $metadata['description'] ?? '';
            $module_dependencies = $metadata['dependencies'] ?? [];

            // Check for legacy settings
            $has_legacy = Config::has_legacy_settings($module_slug);
            ?>
            <div class="sewn-ws-module-card <?php echo esc_attr($status_class); ?>">
                <div class="module-header">
                    <div class="module-title">
                        <h3><?php echo esc_html($module_name); ?></h3>
                        <span class="module-version">v<?php echo esc_html($module_version); ?></span>
                        <?php if ($has_legacy): ?>
                        <span class="legacy-indicator" title="<?php esc_attr_e('Has legacy settings', 'sewn-ws'); ?>">
                            <span class="dashicons dashicons-backup"></span>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="module-status">
                        <span class="status-dot <?php echo esc_attr($status_class); ?>"></span>
                        <span class="status-text">
                            <?php echo $is_active ? __('Active', 'sewn-ws') : __('Inactive', 'sewn-ws'); ?>
                        </span>
                    </div>
                </div>

                <div class="module-meta">
                    <div class="meta-item">
                        <span class="meta-label"><?php _e('Author:', 'sewn-ws'); ?></span>
                        <span class="meta-value"><?php echo esc_html($module_author); ?></span>
                    </div>
                    <?php if (!empty($module_dependencies)): ?>
                    <div class="meta-item">
                        <span class="meta-label"><?php _e('Dependencies:', 'sewn-ws'); ?></span>
                        <span class="meta-value"><?php echo esc_html(implode(', ', $module_dependencies)); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($module_description): ?>
                <div class="module-description">
                    <?php echo wp_kses_post($module_description); ?>
                </div>
                <?php endif; ?>

                <?php if ($is_active && isset($module_errors[$module_slug]) && !empty($module_errors[$module_slug])): ?>
                    <div class="module-errors">
                        <?php if (is_array($module_errors[$module_slug])): ?>
                            <?php foreach ($module_errors[$module_slug] as $key => $error): ?>
                                <div class="module-error">
                                    <span class="dashicons dashicons-warning"></span>
                                    <?php echo esc_html(is_array($error) ? $error['error'] : $error); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="module-actions">
                    <form method="post" action="" class="module-form">
                        <?php wp_nonce_field('sewn_module_control'); ?>
                        <input type="hidden" name="module_slug" value="<?php echo esc_attr($module_slug); ?>">
                        <?php if ($is_active): ?>
                            <input type="hidden" name="sewn_module_action" value="deactivate">
                            <button type="submit" class="button">
                                <?php _e('Deactivate', 'sewn-ws'); ?>
                            </button>
                            <?php if (method_exists($module, 'admin_ui')): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sewn-ws-module-' . $module_slug)); ?>" 
                               class="button button-secondary">
                                <?php _e('Settings', 'sewn-ws'); ?>
                            </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <input type="hidden" name="sewn_module_action" value="activate">
                            <button type="submit" class="button button-primary">
                                <?php _e('Activate', 'sewn-ws'); ?>
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.sewn-ws-modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.sewn-ws-module-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
    border-radius: 4px;
    position: relative;
}

.sewn-ws-module-card.active {
    border-left: 4px solid #00c853;
}

.sewn-ws-module-card.inactive {
    border-left: 4px solid #9e9e9e;
}

.module-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    gap: 10px;
}

.module-title {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.module-title h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.module-version {
    color: #666;
    font-size: 12px;
}

.module-status {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #ccc;
}

.status-dot.active {
    background: #00c853;
}

.status-dot.inactive {
    background: #9e9e9e;
}

.module-meta {
    margin: 15px 0;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
    font-size: 13px;
}

.meta-item {
    display: flex;
    gap: 5px;
    margin-bottom: 5px;
}

.meta-item:last-child {
    margin-bottom: 0;
}

.meta-label {
    color: #666;
    font-weight: 500;
}

.module-description {
    color: #666;
    margin: 15px 0;
    font-size: 13px;
    line-height: 1.5;
}

.module-errors {
    margin: 15px 0;
    padding: 10px;
    background: #fff8e5;
    border-left: 4px solid #ffb900;
    font-size: 13px;
}

.module-error {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 5px;
}

.module-error:last-child {
    margin-bottom: 0;
}

.module-error .dashicons {
    color: #ffb900;
}

.module-actions {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #f0f0f1;
}

.module-form {
    display: flex;
    gap: 10px;
}

.button-secondary {
    color: #2271b1;
    border-color: #2271b1;
    background: #f6f7f7;
}

.button-secondary:hover {
    background: #f0f0f1;
    border-color: #0a4b78;
    color: #0a4b78;
}

.legacy-indicator {
    display: inline-flex;
    align-items: center;
    margin-left: 8px;
    color: #856404;
}

.legacy-indicator .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}
</style>

