<?php
/**
 * LOCATION: admin/views/settings.php
 * DEPENDENCIES: Settings_Page class
 * VARIABLES: $settings (current settings array)
 * CLASSES: None (template file)
 * 
 * Renders global WebSocket server configuration interface including port settings and TLS
 * encryption options. Integrates with network authentication providers for secure credential storage.
 */

namespace SEWN\WebSockets\Admin;

if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div id="sewn-ws-validation-summary"></div>

    <form method="post" action="options.php" id="sewn-ws-settings-form">
        <?php 
        settings_fields('sewn_ws_settings');
        do_settings_sections('sewn_ws_settings');
        wp_nonce_field('sewn_ws_settings', 'sewn_ws_nonce');
        ?>
        
        <table class="form-table">
            <!-- WebSocket Port -->
            <tr>
                <th scope="row">
                    <label for="sewn_ws_port"><?php _e('WebSocket Port', 'sewn-ws'); ?></label>
                </th>
                <td>
                    <input 
                        type="number" 
                        id="sewn_ws_port" 
                        name="sewn_ws_port" 
                        value="<?php echo esc_attr(get_option('sewn_ws_port', '8080')); ?>"
                        class="sewn-ws-validate-field"
                        data-validate="port"
                        min="1024"
                        max="65535"
                    >
                    <p class="description">
                        <?php _e('Port number for WebSocket server (1024-65535)', 'sewn-ws'); ?>
                    </p>
                </td>
            </tr>

            <!-- Development Mode -->
            <tr>
                <th scope="row">
                    <label for="sewn_ws_dev_mode"><?php _e('Development Mode', 'sewn-ws'); ?></label>
                </th>
                <td>
                    <label>
                        <input 
                            type="checkbox" 
                            id="sewn_ws_dev_mode" 
                            name="sewn_ws_dev_mode" 
                            value="1" 
                            <?php checked(get_option('sewn_ws_dev_mode', false)); ?>
                        >
                        <?php _e('Enable development mode (allows HTTP WebSocket connections)', 'sewn-ws'); ?>
                    </label>
                    <?php if (get_option('sewn_ws_dev_mode', false)): ?>
                        <p class="description" style="color: #d63638;">
                            <?php _e('Warning: Development mode is enabled. This should only be used in local development environments.', 'sewn-ws'); ?>
                        </p>
                    <?php else: ?>
                        <p class="description">
                            <?php _e('Enable this option to allow HTTP WebSocket connections during local development.', 'sewn-ws'); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>

            <!-- WebSocket Host -->
            <tr>
                <th scope="row">
                    <label for="sewn_ws_host"><?php _e('WebSocket Host', 'sewn-ws'); ?></label>
                </th>
                <td>
                    <input 
                        type="text" 
                        id="sewn_ws_host" 
                        name="sewn_ws_host" 
                        value="<?php echo esc_attr(get_option('sewn_ws_host', 'localhost')); ?>"
                        class="regular-text sewn-ws-validate-field"
                        data-validate="host"
                    >
                    <p class="description">
                        <?php _e('WebSocket server hostname (e.g., localhost)', 'sewn-ws'); ?>
                    </p>
                </td>
            </tr>

            <!-- Rate Limit -->
            <tr>
                <th scope="row">
                    <label for="sewn_ws_rate_limit"><?php _e('Rate Limit', 'sewn-ws'); ?></label>
                </th>
                <td>
                    <input 
                        type="number" 
                        id="sewn_ws_rate_limit" 
                        name="sewn_ws_rate_limit" 
                        value="<?php echo esc_attr(get_option('sewn_ws_rate_limit', '60')); ?>"
                        class="sewn-ws-validate-field"
                        data-validate="rate"
                        min="1"
                    >
                    <p class="description">
                        <?php _e('Maximum messages per minute per client', 'sewn-ws'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <div class="sewn-ws-settings-footer">
            <?php if (get_option('sewn_ws_dev_mode', false)): ?>
                <div class="notice notice-warning inline">
                    <p>
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('Development mode is currently enabled. WebSocket connections will use HTTP instead of HTTPS.', 'sewn-ws'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <p class="submit">
                <button type="button" id="sewn-ws-test-config" class="button-secondary">
                    <?php _e('Test Configuration', 'sewn-ws'); ?>
                </button>
                <?php submit_button(null, 'primary', 'submit', false); ?>
            </p>
        </div>
    </form>
</div>

<style>
.sewn-ws-settings-footer {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.notice.inline {
    margin: 15px 0;
}

.notice .dashicons {
    margin-right: 5px;
    vertical-align: middle;
}
</style>

