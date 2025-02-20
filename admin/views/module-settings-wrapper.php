<?php
/**
 * Module settings wrapper template
 * 
 * @var array $module_data Module metadata
 * @var array $settings Module settings configuration
 * @var string $module_slug Module slug
 */

namespace SEWN\WebSockets\Admin;

if (!defined('ABSPATH')) exit;
?>

<div class="wrap sewn-ws-module-settings">
    <h1><?php echo esc_html($settings['menu_title'] ?? __('Module Settings', 'sewn-ws')); ?></h1>
    
    <div class="sewn-ws-module-info">
        <h2><?php echo esc_html($module_data['name']); ?></h2>
        <p class="description"><?php echo esc_html($module_data['description']); ?></p>
        <p class="version"><?php printf(__('Version: %s', 'sewn-ws'), esc_html($module_data['version'])); ?></p>
        <?php if (!empty($module_data['author'])): ?>
            <p class="author"><?php printf(__('Author: %s', 'sewn-ws'), esc_html($module_data['author'])); ?></p>
        <?php endif; ?>
    </div>

    <form method="post" action="options.php">
        <?php 
        settings_fields('sewn_ws_module_' . $module_slug);
        do_settings_sections('sewn_ws_module_' . $module_slug);
        submit_button(); 
        ?>
    </form>
</div>

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