<?php

/**
 * LOCATION: admin/views/module-settings.php
 * DEPENDENCIES: Module_Admin class
 * VARIABLES: $module_data (array)
 * CLASSES: None (template file)
 * 
 * Renders configuration interface for individual WebSocket protocol modules. Provides tier-based access controls
 * aligned with Startempire Wire Network membership levels. Integrates with network authentication providers for
 * secure settings storage.
 */

namespace SEWN\WebSockets\Admin;

use SEWN\WebSockets\Config;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get module slug from query parameter
$module_slug = isset($_GET['module']) ? sanitize_key($_GET['module']) : '';
$module_registry = \SEWN\WebSockets\Module_Registry::get_instance();
$module = $module_registry->get_module($module_slug);

if (!$module || !$module instanceof \SEWN\WebSockets\Module_Base) {
    wp_die(__('Invalid module or module not loaded', 'sewn-ws'));
}

$meta = $module->metadata();

// Check for legacy settings
$has_legacy = Config::has_legacy_settings($module_slug);
$migration_results = [];

// Handle migration if requested
if (isset($_POST['sewn_ws_migrate_legacy']) && check_admin_referer('sewn_ws_migrate_legacy')) {
    $migration_results = Config::migrate_legacy_settings();
}

?>
<div class="wrap">
    <h1><?php echo esc_html($meta['name']); ?> Settings</h1>
    
    <?php if (isset($_GET['settings-updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Settings saved successfully.', 'sewn-ws'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($has_legacy): ?>
    <div class="notice notice-warning">
        <p>
            <?php _e('Legacy settings detected for this module. These settings will be automatically migrated when saved.', 'sewn-ws'); ?>
        </p>
        <form method="post" action="">
            <?php wp_nonce_field('sewn_ws_migrate_legacy'); ?>
            <input type="hidden" name="sewn_ws_migrate_legacy" value="1">
            <p>
                <button type="submit" class="button button-secondary">
                    <?php _e('Migrate Settings Now', 'sewn-ws'); ?>
                </button>
            </p>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!empty($migration_results)): ?>
    <div class="notice notice-info">
        <p><?php _e('Migration Results:', 'sewn-ws'); ?></p>
        <ul>
            <?php foreach ($migration_results as $mod => $results): ?>
                <?php foreach ($results as $key => $success): ?>
                    <li>
                        <?php 
                        echo sprintf(
                            '%s: %s - %s',
                            esc_html($mod),
                            esc_html($key),
                            $success ? __('Success', 'sewn-ws') : __('Failed', 'sewn-ws')
                        ); 
                        ?>
                    </li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="sewn-module-settings">
        <div class="module-meta">
            <div class="module-info">
                <h2 class="module-title"><?php echo esc_html($meta['name']); ?></h2>
                <p class="module-version">Version: <?php echo esc_html($meta['version']); ?></p>
                <?php if (!empty($meta['author'])) : ?>
                    <p class="module-author">By <?php echo esc_html($meta['author']); ?></p>
                <?php endif; ?>
                <p class="module-description"><?php echo esc_html($meta['description']); ?></p>
            </div>
        </div>

        <form method="post" action="options.php" class="module-settings-form">
            <?php 
            $option_group = 'sewn_ws_module_' . $module_slug;
            settings_fields($option_group);
            
            // Get admin UI configuration
            $admin_ui = $module->admin_ui();
            
            // Output sections and fields
            do_settings_sections($option_group);
            
            submit_button(__('Save Changes', 'sewn-ws')); 
            ?>
        </form>
    </div>
</div>

<style>
.sewn-module-settings {
    max-width: 800px;
    background: #fff;
    padding: 2rem;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.module-meta {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.module-title {
    margin: 0 0 0.5rem;
    font-size: 1.5rem;
}

.module-version {
    color: #666;
    font-size: 0.9em;
    margin: 0 0 0.5rem;
}

.module-author {
    color: #666;
    margin: 0 0 1rem;
}

.module-description {
    color: #444;
    margin: 1rem 0 0;
}

.module-settings-form {
    margin-top: 2rem;
}

.module-settings-form .form-table {
    margin-top: 1rem;
}

.notice ul {
    list-style: disc;
    margin-left: 2em;
    margin-bottom: 1em;
}

.notice form {
    margin-top: 1em;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Add confirmation for migration
    $('form[name="sewn_ws_migrate_legacy"]').on('submit', function(e) {
        if (!confirm('<?php esc_attr_e('Are you sure you want to migrate legacy settings? This process cannot be undone.', 'sewn-ws'); ?>')) {
            e.preventDefault();
        }
    });
});
</script>