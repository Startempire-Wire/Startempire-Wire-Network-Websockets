<?php
namespace SEWN\WebSockets\Admin;   

use SEWN\WebSockets\Module_Registry;

if (!defined('ABSPATH')) exit;

$modules = Module_Registry::get_instance()->get_modules();
?>
<div class="wrap">
    <h1><?php esc_html_e('WebSocket Modules', 'sewn-ws'); ?></h1>
    
    <?php if (empty($modules)) : ?>
        <div class="notice notice-warning">
            <p>No modules found.</p>
        </div>
    <?php else : ?>
        <div class="sewn-modules-grid">
            <?php foreach ($modules as $module_slug => $module) : 
                if (!$module instanceof \SEWN\WebSockets\Module_Base) continue;
                
                $meta = $module->metadata();
                if (!is_array($meta)) continue;
                
                $safe_meta = wp_parse_args($meta, [
                    'module_slug' => $module_slug,
                    'name' => __('Unnamed Module', 'sewn-ws'),
                    'version' => '0.0.0',
                    'description' => __('No description available', 'sewn-ws'),
                    'author' => ''
                ]);
                
                $valid_slug = sanitize_key($safe_meta['module_slug']);
                $is_active = $module->is_active();
            ?>
                <div class="module-card <?php echo $is_active ? 'active' : 'inactive'; ?>">
                    <div class="module-header">
                        <h2><?php echo esc_html($safe_meta['name']); ?></h2>
                        <span class="module-version">v<?php echo esc_html($safe_meta['version']); ?></span>
                    </div>
                    
                    <div class="module-body">
                        <p class="module-description"><?php echo esc_html($safe_meta['description']); ?></p>
                        
                        <div class="module-actions">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=sewn-ws-module-settings&module=$module_slug")); ?>" 
                               class="button button-primary">
                                <?php esc_html_e('Configure', 'sewn-ws'); ?>
                            </a>
                            
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="sewn_ws_toggle_module">
                                <input type="hidden" name="module" value="<?php echo esc_attr($valid_slug); ?>">
                                <?php wp_nonce_field('sewn_ws_module_toggle'); ?>
                                
                                <button type="submit" class="button">
                                    <?php echo $is_active ? __('Deactivate', 'sewn-ws') : __('Activate', 'sewn-ws'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.sewn-modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.module-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.module-card.active {
    border-left: 4px solid #00a32a;
}

.module-card.inactive {
    border-left: 4px solid #d63638;
}

.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.module-header h2 {
    margin: 0;
    font-size: 1.1rem;
}

.module-version {
    color: #666;
    font-size: 0.8em;
}

.module-body {
    min-height: 120px;
}

.module-description {
    color: #666;
    margin: 0 0 1rem;
}

.module-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}
</style> 