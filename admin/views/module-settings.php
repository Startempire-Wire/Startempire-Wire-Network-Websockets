<?php
namespace SEWN\WebSockets\Admin;


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
?>
<div class="wrap">
    <h1><?php echo esc_html($meta['name']); ?> Settings</h1>
    
    <?php if (isset($_GET['settings-updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Settings saved successfully.', 'sewn-ws'); ?></p>
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
            settings_fields('sewn_ws_module_' . $module_slug);
            do_settings_sections('sewn_ws_module_' . $module_slug); 
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
</style>