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

// Enqueue required styles and scripts
wp_enqueue_style('sewn-ws-settings', plugins_url('css/settings.css', dirname(__FILE__)));
wp_enqueue_script('sewn-ws-settings', plugins_url('js/settings.js', dirname(__FILE__)), array('jquery'), false, true);
wp_localize_script('sewn-ws-settings', 'sewnWsAdmin', array(
    'nonce' => wp_create_nonce('sewn_ws_admin')
));
?>
<div class="wrap">
    <?php if (get_option(SEWN_WS_OPTION_DEV_MODE, false)): ?>
        <div class="notice notice-warning inline">
            <p>
                <span class="dashicons dashicons-warning"></span>
                <?php _e('Development mode is currently enabled. WebSocket connections will use HTTP instead of HTTPS.', 'sewn-ws'); ?>
            </p>
        </div>
    <?php endif; ?>
    
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div id="sewn-ws-validation-summary"></div>

    <form method="post" action="options.php" id="sewn-ws-settings-form">
        <?php 
        settings_fields('sewn_ws_settings');
        do_settings_sections('sewn_ws_settings');
        wp_nonce_field('sewn_ws_settings', 'sewn_ws_nonce');
        ?>

        <nav class="nav-tab-wrapper sewn-ws-tab-nav">
            <a href="#general" class="nav-tab"><?php _e('General', 'sewn-ws'); ?></a>
            <a href="#environment" class="nav-tab"><?php _e('Environment', 'sewn-ws'); ?></a>
            <a href="#modules" class="nav-tab"><?php _e('Modules', 'sewn-ws'); ?></a>
            <a href="#debug" class="nav-tab"><?php _e('Debug', 'sewn-ws'); ?></a>
        </nav>

        <!-- General Settings Tab -->
        <div id="general" class="sewn-ws-tab-content">
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
                            value="<?php echo esc_attr(get_option('sewn_ws_port', SEWN_WS_DEFAULT_PORT)); ?>"
                            class="sewn-ws-validate-field"
                            data-validate="port"
                            min="1024"
                            max="65535"
                        >
                        <p class="description">
                            <?php _e('Port for WebSocket server. Default is 49200 (from IANA dynamic port range 49152-65535).', 'sewn-ws'); ?>
                        </p>
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
                            name="<?php echo SEWN_WS_OPTION_RATE_LIMIT; ?>" 
                            value="<?php echo esc_attr(get_option(SEWN_WS_OPTION_RATE_LIMIT, '60')); ?>"
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
        </div>

        <!-- Environment Settings Tab -->
        <div id="environment" class="sewn-ws-tab-content">
            <?php 
            $environment = get_option(SEWN_WS_OPTION_ENVIRONMENT, array());
            $is_local = isset($environment['is_local']) ? $environment['is_local'] : false;
            $container_mode = isset($environment['container_mode']) ? $environment['container_mode'] : false;
            $detected_url = get_site_url();
            $auto_detected = $is_local || $container_mode;
            ?>

            <?php if ($auto_detected): ?>
            <div class="notice notice-info inline environment-detection">
                <p>
                    <span class="dashicons dashicons-info"></span>
                    <?php _e('Local environment detected: Settings have been optimized automatically', 'sewn-ws'); ?>
                </p>
                <div class="environment-details">
                    <h4><?php _e('Detected Environment:', 'sewn-ws'); ?></h4>
                    <ul>
                        <li>
                            <strong><?php _e('Type:', 'sewn-ws'); ?></strong>
                            <?php echo $environment['type'] ?? 'Standard Local'; ?>
                        </li>
                        <li>
                            <strong><?php _e('Container System:', 'sewn-ws'); ?></strong>
                            <?php echo $container_mode ? __('Docker (Local by Flywheel)', 'sewn-ws') : __('None', 'sewn-ws'); ?>
                        </li>
                        <li>
                            <strong><?php _e('Site URL:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($detected_url); ?>
                        </li>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <table class="form-table">
                <!-- Development Mode -->
                <tr>
                    <th scope="row">
                        <label for="sewn_ws_dev_mode"><?php _e('Development Mode', 'sewn-ws'); ?></label>
                    </th>
                    <td>
                        <label class="sewn-ws-toggle">
                            <input 
                                type="checkbox" 
                                id="sewn_ws_dev_mode" 
                                name="<?php echo SEWN_WS_OPTION_DEV_MODE; ?>" 
                                value="1" 
                                <?php checked(get_option(SEWN_WS_OPTION_DEV_MODE, false)); ?>
                            >
                            <span class="sewn-ws-toggle-slider"></span>
                        </label>
                        <label for="sewn_ws_dev_mode">
                            <?php _e('Enable development mode (allows HTTP WebSocket connections)', 'sewn-ws'); ?>
                        </label>
                        <?php if (get_option(SEWN_WS_OPTION_DEV_MODE, false)): ?>
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

                <!-- Local Environment Mode -->
                <tr>
                    <th scope="row">
                        <label for="sewn_ws_env_local_mode"><?php _e('Local Environment', 'sewn-ws'); ?></label>
                        <p class="description">
                            <?php _e('Optimizes settings for local development', 'sewn-ws'); ?>
                        </p>
                    </th>
                    <td>
                        <fieldset>
                            <label class="sewn-ws-toggle">
                                <input 
                                    type="checkbox" 
                                    id="sewn_ws_env_local_mode" 
                                    name="sewn_ws_env_local_mode" 
                                    value="1" 
                                    <?php checked(get_option('sewn_ws_env_local_mode', false)); ?>
                                    <?php disabled($is_local); ?>
                                >
                                <span class="sewn-ws-toggle-slider"></span>
                            </label>
                            <label for="sewn_ws_env_local_mode">
                                <?php _e('Enable Local Environment Mode', 'sewn-ws'); ?>
                            </label>
                            <?php if ($is_local): ?>
                                <p class="description highlighted">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Local environment detected and automatically configured', 'sewn-ws'); ?>
                                </p>
                            <?php else: ?>
                                <p class="description">
                                    <?php _e('Enable for local development environments like MAMP, XAMPP, or Local by Flywheel', 'sewn-ws'); ?>
                                </p>
                            <?php endif; ?>
                        </fieldset>
                    </td>
                </tr>

                <!-- Container Mode -->
                <tr class="container-mode-row <?php echo !get_option('sewn_ws_env_local_mode', false) && !$is_local ? 'disabled' : ''; ?>">
                    <th scope="row">
                        <label for="sewn_ws_env_container_mode"><?php _e('Container Mode', 'sewn-ws'); ?></label>
                        <p class="description">
                            <?php _e('Optimizes network settings for containerized environments', 'sewn-ws'); ?>
                        </p>
                    </th>
                    <td>
                        <fieldset>
                            <label class="sewn-ws-toggle">
                                <input 
                                    type="checkbox" 
                                    id="sewn_ws_env_container_mode" 
                                    name="sewn_ws_env_container_mode" 
                                    value="1" 
                                    <?php checked(get_option('sewn_ws_env_container_mode', false)); ?>
                                    <?php disabled($container_mode || (!get_option('sewn_ws_env_local_mode', false) && !$is_local)); ?>
                                >
                                <span class="sewn-ws-toggle-slider"></span>
                            </label>
                            <label for="sewn_ws_env_container_mode">
                                <?php _e('Enable Container Mode', 'sewn-ws'); ?>
                            </label>
                            <?php if ($container_mode): ?>
                                <p class="description highlighted">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Container environment detected and configured automatically', 'sewn-ws'); ?>
                                </p>
                            <?php else: ?>
                                <div class="container-benefits">
                                    <p class="description">
                                        <?php _e('Enables:', 'sewn-ws'); ?>
                                    </p>
                                    <ul>
                                        <li><span class="dashicons dashicons-yes"></span> <?php _e('Proper network routing between containers', 'sewn-ws'); ?></li>
                                        <li><span class="dashicons dashicons-yes"></span> <?php _e('Correct hostname resolution', 'sewn-ws'); ?></li>
                                        <li><span class="dashicons dashicons-yes"></span> <?php _e('Automatic port mapping', 'sewn-ws'); ?></li>
                                        <li><span class="dashicons dashicons-yes"></span> <?php _e('Built-in SSL handling', 'sewn-ws'); ?></li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </fieldset>
                    </td>
                </tr>

                <!-- SSL Configuration -->
                <tr class="ssl-config-row <?php echo !get_option('sewn_ws_env_local_mode', false) && !$is_local ? 'disabled' : ''; ?>">
                    <th scope="row">
                        <label><?php _e('SSL Configuration', 'sewn-ws'); ?></label>
                        <p class="description">
                            <?php _e('SSL certificate settings for secure connections', 'sewn-ws'); ?>
                        </p>
                    </th>
                    <td>
                        <div class="ssl-paths">
                            <div class="ssl-field-wrapper">
                                <label for="sewn_ws_ssl_cert"><?php _e('Certificate Path:', 'sewn-ws'); ?></label>
                                <div class="input-group">
                                    <input 
                                        type="text" 
                                        id="sewn_ws_ssl_cert" 
                                        name="<?php echo SEWN_WS_OPTION_SSL_CERT; ?>" 
                                        value="<?php echo esc_attr(get_option(SEWN_WS_OPTION_SSL_CERT, '')); ?>"
                                        class="regular-text code"
                                        <?php disabled(!get_option('sewn_ws_env_local_mode', false) && !$is_local); ?>
                                    >
                                    <button type="button" class="button button-secondary detect-ssl" data-type="cert" <?php disabled(!get_option('sewn_ws_env_local_mode', false) && !$is_local); ?>>
                                        <span class="dashicons dashicons-search"></span>
                                        <?php _e('Detect', 'sewn-ws'); ?>
                                    </button>
                                    <button type="button" class="button button-secondary browse-ssl" data-type="cert" <?php disabled(!get_option('sewn_ws_env_local_mode', false) && !$is_local); ?>>
                                        <span class="dashicons dashicons-portfolio"></span>
                                        <?php _e('Browse...', 'sewn-ws'); ?>
                                    </button>
                                </div>
                            </div>
                            <div class="ssl-field-wrapper">
                                <label for="sewn_ws_ssl_key"><?php _e('Private Key Path:', 'sewn-ws'); ?></label>
                                <div class="input-group">
                                    <input 
                                        type="text" 
                                        id="sewn_ws_ssl_key" 
                                        name="<?php echo SEWN_WS_OPTION_SSL_KEY; ?>" 
                                        value="<?php echo esc_attr(get_option(SEWN_WS_OPTION_SSL_KEY, '')); ?>"
                                        class="regular-text code"
                                        <?php disabled(!get_option('sewn_ws_env_local_mode', false) && !$is_local); ?>
                                    >
                                    <button type="button" class="button button-secondary detect-ssl" data-type="key" <?php disabled(!get_option('sewn_ws_env_local_mode', false) && !$is_local); ?>>
                                        <span class="dashicons dashicons-search"></span>
                                        <?php _e('Detect', 'sewn-ws'); ?>
                                    </button>
                                    <button type="button" class="button button-secondary browse-ssl" data-type="key" <?php disabled(!get_option('sewn_ws_env_local_mode', false) && !$is_local); ?>>
                                        <span class="dashicons dashicons-portfolio"></span>
                                        <?php _e('Browse...', 'sewn-ws'); ?>
                                    </button>
                                </div>
                            </div>
                            <div class="ssl-actions">
                                <button type="button" class="button button-secondary test-ssl" <?php disabled(!get_option('sewn_ws_env_local_mode', false) && !$is_local); ?>>
                                    <span class="dashicons dashicons-shield"></span>
                                    <?php _e('Test SSL Configuration', 'sewn-ws'); ?>
                                </button>
                            </div>
                            <p class="description">
                                <?php _e('SSL certificate and private key paths for secure WebSocket connections. Leave empty to use default Local SSL.', 'sewn-ws'); ?>
                            </p>
                            <div class="ssl-help">
                                <p class="description">
                                    <span class="dashicons dashicons-info"></span>
                                    <?php 
                                    $os = PHP_OS_FAMILY;
                                    if ($os === 'Darwin') {
                                        _e('On macOS, certificates are typically located in /etc/ssl/certs/', 'sewn-ws');
                                    } elseif ($os === 'Windows') {
                                        _e('On Windows, certificates are typically located in C:\\Program Files\\Local\\resources\\certs\\', 'sewn-ws');
                                    } else {
                                        _e('On Linux, certificates are typically located in /etc/ssl/certs/', 'sewn-ws');
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Modules Tab -->
        <div id="modules" class="sewn-ws-tab-content">
            <div class="sewn-ws-module-grid">
                <?php
                $modules = apply_filters('sewn_ws_registered_modules', array());
                foreach ($modules as $module_id => $module) :
                    $status = isset($module['active']) && $module['active'] ? 'active' : 'inactive';
                ?>
                <div class="sewn-ws-module-card" data-module-id="<?php echo esc_attr($module_id); ?>">
                    <h3><?php echo esc_html($module['name']); ?></h3>
                    <p><?php echo esc_html($module['description']); ?></p>
                    <span class="sewn-ws-module-status <?php echo esc_attr($status); ?>">
                        <?php echo $status === 'active' ? __('Active', 'sewn-ws') : __('Inactive', 'sewn-ws'); ?>
                    </span>
                    <?php if (!empty($module['version'])): ?>
                        <p class="module-version">
                            <?php printf(__('Version: %s', 'sewn-ws'), esc_html($module['version'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Debug Tab -->
        <div id="debug" class="sewn-ws-tab-content">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="sewn_ws_debug_enabled"><?php _e('Debug Mode', 'sewn-ws'); ?></label>
                        <p class="description">
                            <?php _e('Enable detailed logging and debugging tools', 'sewn-ws'); ?>
                        </p>
                    </th>
                    <td>
                        <fieldset>
                            <label class="sewn-ws-toggle">
                                <input 
                                    type="checkbox" 
                                    id="sewn_ws_debug_enabled" 
                                    name="sewn_ws_debug_enabled"
                                    value="1" 
                                    <?php checked(get_option('sewn_ws_debug_enabled', false)); ?>
                                >
                                <span class="sewn-ws-toggle-slider"></span>
                            </label>
                            <label for="sewn_ws_debug_enabled">
                                <?php _e('Enable Debug Mode', 'sewn-ws'); ?>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, detailed error logs and debugging information will be available below.', 'sewn-ws'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <!-- Debug Panel -->
            <div class="sewn-ws-debug-panel" style="display: <?php echo get_option('sewn_ws_debug_enabled', false) ? 'block' : 'none'; ?>">
                <div class="sewn-ws-debug-toolbar">
                    <div class="debug-filters">
                        <label>
                            <input type="checkbox" class="log-level-filter" value="error" checked> 
                            <span class="level-indicator error"><?php _e('Errors', 'sewn-ws'); ?></span>
                        </label>
                        <label>
                            <input type="checkbox" class="log-level-filter" value="warning" checked> 
                            <span class="level-indicator warning"><?php _e('Warnings', 'sewn-ws'); ?></span>
                        </label>
                        <label>
                            <input type="checkbox" class="log-level-filter" value="info" checked> 
                            <span class="level-indicator info"><?php _e('Info', 'sewn-ws'); ?></span>
                        </label>
                    </div>
                    <div class="debug-actions">
                        <button type="button" class="button button-secondary clear-logs">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Clear Logs', 'sewn-ws'); ?>
                        </button>
                        <button type="button" class="button button-secondary export-logs">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Export Logs', 'sewn-ws'); ?>
                        </button>
                    </div>
                </div>

                <div class="sewn-ws-log-viewer">
                    <div class="log-content"></div>
                    <div class="log-loading">
                        <span class="spinner is-active"></span>
                        <?php _e('Loading logs...', 'sewn-ws'); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="sewn-ws-settings-footer">
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

