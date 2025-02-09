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

        <h2 class="title"><?php _e('Local Environment Settings', 'sewn-ws'); ?></h2>
        
        <?php 
        $environment = get_option('sewn_ws_environment', array());
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

        <table class="form-table" role="presentation">
            <!-- Local Environment Mode -->
            <tr>
                <th scope="row">
                    <label for="sewn_ws_local_mode"><?php _e('Local Environment', 'sewn-ws'); ?></label>
                    <p class="description">
                        <?php _e('Optimizes settings for local development', 'sewn-ws'); ?>
                    </p>
                </th>
                <td>
                    <fieldset>
                        <label class="sewn-ws-toggle">
                            <input 
                                type="checkbox" 
                                id="sewn_ws_local_mode" 
                                name="sewn_ws_local_mode" 
                                value="1" 
                                <?php checked($is_local || get_option('sewn_ws_local_mode', false)); ?>
                                <?php disabled($is_local); ?>
                            >
                            <span class="sewn-ws-toggle-slider"></span>
                        </label>
                        <label for="sewn_ws_local_mode">
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
            <tr class="container-mode-row <?php echo !get_option('sewn_ws_local_mode', false) && !$is_local ? 'disabled' : ''; ?>">
                <th scope="row">
                    <label for="sewn_ws_container_mode"><?php _e('Container Mode', 'sewn-ws'); ?></label>
                    <p class="description">
                        <?php _e('Optimizes network settings for containerized environments', 'sewn-ws'); ?>
                    </p>
                </th>
                <td>
                    <fieldset>
                        <label class="sewn-ws-toggle">
                            <input 
                                type="checkbox" 
                                id="sewn_ws_container_mode" 
                                name="sewn_ws_container_mode" 
                                value="1" 
                                <?php checked($container_mode || get_option('sewn_ws_container_mode', false)); ?>
                                <?php disabled($container_mode || (!get_option('sewn_ws_local_mode', false) && !$is_local)); ?>
                            >
                            <span class="sewn-ws-toggle-slider"></span>
                        </label>
                        <label for="sewn_ws_container_mode">
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

            <!-- Local Site URL -->
            <tr class="local-url-row <?php echo !get_option('sewn_ws_local_mode', false) && !$is_local ? 'disabled' : ''; ?>">
                <th scope="row">
                    <label for="sewn_ws_local_site_url"><?php _e('Local Site URL', 'sewn-ws'); ?></label>
                    <p class="description">
                        <?php _e('The URL used to access your local site', 'sewn-ws'); ?>
                    </p>
                </th>
                <td>
                    <div class="url-field-wrapper">
                        <input 
                            type="url" 
                            id="sewn_ws_local_site_url" 
                            name="sewn_ws_local_site_url" 
                            value="<?php echo esc_attr(get_option('sewn_ws_local_site_url', $detected_url)); ?>"
                            class="regular-text code"
                            <?php disabled(!get_option('sewn_ws_local_mode', false) && !$is_local); ?>
                            placeholder="https://your-site.local"
                            data-detected-url="<?php echo esc_attr($detected_url); ?>"
                        >
                        <button type="button" class="button button-secondary reset-url" <?php disabled(!get_option('sewn_ws_local_mode', false) && !$is_local); ?>>
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Reset to Detected', 'sewn-ws'); ?>
                        </button>
                    </div>
                    <p class="description url-status">
                        <?php if ($detected_url === get_option('sewn_ws_local_site_url', '')): ?>
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php _e('Using detected URL', 'sewn-ws'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-info"></span>
                            <?php _e('Custom URL set', 'sewn-ws'); ?>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>

            <!-- SSL Configuration -->
            <tr class="ssl-config-row <?php echo !get_option('sewn_ws_local_mode', false) && !$is_local ? 'disabled' : ''; ?>">
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
                                    name="sewn_ws_ssl_cert" 
                                    value="<?php echo esc_attr(get_option('sewn_ws_ssl_cert', '')); ?>"
                                    class="regular-text code"
                                    <?php disabled(!get_option('sewn_ws_local_mode', false) && !$is_local); ?>
                                >
                                <button type="button" class="button button-secondary detect-ssl" data-type="cert" <?php disabled(!get_option('sewn_ws_local_mode', false) && !$is_local); ?>>
                                    <span class="dashicons dashicons-search"></span>
                                    <?php _e('Detect', 'sewn-ws'); ?>
                                </button>
                                <button type="button" class="button button-secondary browse-ssl" data-type="cert" <?php disabled(!get_option('sewn_ws_local_mode', false) && !$is_local); ?>>
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
                                    name="sewn_ws_ssl_key" 
                                    value="<?php echo esc_attr(get_option('sewn_ws_ssl_key', '')); ?>"
                                    class="regular-text code"
                                    <?php disabled(!get_option('sewn_ws_local_mode', false) && !$is_local); ?>
                                >
                                <button type="button" class="button button-secondary detect-ssl" data-type="key" <?php disabled(!get_option('sewn_ws_local_mode', false) && !$is_local); ?>>
                                    <span class="dashicons dashicons-search"></span>
                                    <?php _e('Detect', 'sewn-ws'); ?>
                                </button>
                                <button type="button" class="button button-secondary browse-ssl" data-type="key" <?php disabled(!get_option('sewn_ws_local_mode', false) && !$is_local); ?>>
                                    <span class="dashicons dashicons-portfolio"></span>
                                    <?php _e('Browse...', 'sewn-ws'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="ssl-actions">
                            <button type="button" class="button button-secondary test-ssl" <?php disabled(!get_option('sewn_ws_local_mode', false) && !$is_local); ?>>
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

        <!-- Debug Settings Section -->
        <h2 class="title"><?php _e('Debug Settings', 'sewn-ws'); ?></h2>
        
        <table class="form-table" role="presentation">
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

        <style>
        .sewn-ws-toggle {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
            margin-right: 10px;
        }

        .sewn-ws-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .sewn-ws-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .sewn-ws-toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        .sewn-ws-toggle input:checked + .sewn-ws-toggle-slider {
            background-color: #2271b1;
        }

        .sewn-ws-toggle input:disabled + .sewn-ws-toggle-slider {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .sewn-ws-toggle input:checked + .sewn-ws-toggle-slider:before {
            transform: translateX(26px);
        }

        tr.disabled {
            opacity: 0.5;
        }

        tr.disabled input,
        tr.disabled button {
            pointer-events: none;
        }

        .description.highlighted {
            color: #2271b1;
            font-weight: 500;
        }

        .description.highlighted .dashicons {
            color: #46b450;
            vertical-align: middle;
        }

        .ssl-paths label {
            display: inline-block;
            min-width: 120px;
            margin: 5px 0;
        }

        .detect-ssl .dashicons {
            vertical-align: middle;
            margin-top: -2px;
        }

        .notice.inline {
            margin: 15px 0;
        }

        .notice .dashicons {
            margin-right: 5px;
            vertical-align: middle;
        }

        .environment-detection {
            background-color: #f0f6fc;
            border-left-color: #2271b1;
        }

        .environment-details {
            margin-top: 10px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 4px;
        }

        .environment-details h4 {
            margin: 0 0 10px 0;
            color: #1d2327;
        }

        .environment-details ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .environment-details li {
            margin: 5px 0;
        }

        .environment-details strong {
            display: inline-block;
            min-width: 120px;
            color: #1d2327;
        }

        .container-benefits {
            margin-top: 10px;
        }

        .container-benefits ul {
            margin: 5px 0 0 0;
            padding: 0;
            list-style: none;
        }

        .container-benefits li {
            margin: 5px 0;
            padding-left: 24px;
            position: relative;
        }

        .container-benefits .dashicons {
            position: absolute;
            left: 0;
            top: 2px;
            color: #46b450;
        }

        .url-field-wrapper,
        .ssl-field-wrapper {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .url-field-wrapper .button,
        .ssl-field-wrapper .button {
            margin-left: 5px;
        }

        .input-group {
            display: flex;
            align-items: center;
            flex: 1;
        }

        .input-group input {
            flex: 1;
        }

        .input-group .button {
            margin-left: 5px;
        }

        .ssl-actions {
            margin: 15px 0;
        }

        .ssl-help {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .url-status {
            margin-top: 5px;
        }

        .url-status .dashicons {
            vertical-align: middle;
        }

        .url-status .dashicons-yes-alt {
            color: #46b450;
        }

        .url-status .dashicons-info {
            color: #2271b1;
        }

        /* Form field styles */
        .form-table th {
            padding-top: 20px;
            padding-bottom: 20px;
        }

        .form-table th .description {
            font-weight: normal;
            font-style: italic;
            margin-top: 5px;
        }

        .button .dashicons {
            vertical-align: middle;
            margin-top: -2px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Handle local mode toggle
            $('#sewn_ws_local_mode').on('change', function() {
                var isEnabled = $(this).is(':checked');
                $('.container-mode-row, .local-url-row, .ssl-config-row').toggleClass('disabled', !isEnabled);
                $('#sewn_ws_container_mode, #sewn_ws_local_site_url, #sewn_ws_ssl_cert, #sewn_ws_ssl_key')
                    .prop('disabled', !isEnabled);
                $('.detect-ssl, .browse-ssl, .test-ssl, .reset-url').prop('disabled', !isEnabled);
                
                if (!isEnabled) {
                    $('#sewn_ws_container_mode').prop('checked', false);
                }
            });

            // Reset URL to detected value
            $('.reset-url').on('click', function() {
                var $input = $('#sewn_ws_local_site_url');
                var detectedUrl = $input.data('detected-url');
                $input.val(detectedUrl);
                updateUrlStatus(detectedUrl);
            });

            // Update URL status message
            function updateUrlStatus(currentUrl) {
                var $status = $('.url-status');
                var detectedUrl = $('#sewn_ws_local_site_url').data('detected-url');
                
                if (currentUrl === detectedUrl) {
                    $status.html('<span class="dashicons dashicons-yes-alt"></span> <?php _e('Using detected URL', 'sewn-ws'); ?>');
                } else {
                    $status.html('<span class="dashicons dashicons-info"></span> <?php _e('Custom URL set', 'sewn-ws'); ?>');
                }
            }

            // Monitor URL changes
            $('#sewn_ws_local_site_url').on('input', function() {
                updateUrlStatus($(this).val());
            });

            // SSL certificate detection
            $('.detect-ssl').on('click', function() {
                var $button = $(this);
                var type = $button.data('type');
                var $input = type === 'cert' ? $('#sewn_ws_ssl_cert') : $('#sewn_ws_ssl_key');
                
                $button.prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sewn_ws_get_ssl_paths',
                        type: type,
                        nonce: sewnWsAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.paths.length > 0) {
                            $input.val(response.data.paths[0]);
                        } else {
                            alert('No SSL certificates found in common locations.');
                        }
                    },
                    error: function() {
                        alert('Failed to detect SSL certificates.');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                });
            });

            // SSL certificate file browser
            $('.browse-ssl').on('click', function() {
                var $button = $(this);
                var type = $button.data('type');
                var $input = type === 'cert' ? $('#sewn_ws_ssl_cert') : $('#sewn_ws_ssl_key');
                
                // Create a temporary file input
                var $fileInput = $('<input type="file">').hide();
                if (type === 'cert') {
                    $fileInput.attr('accept', '.crt,.pem');
                } else {
                    $fileInput.attr('accept', '.key');
                }
                
                $('body').append($fileInput);
                
                $fileInput.trigger('click');
                
                $fileInput.on('change', function() {
                    var file = this.files[0];
                    if (file) {
                        $input.val(file.name);
                    }
                    $fileInput.remove();
                });
            });

            // Test SSL configuration
            $('.test-ssl').on('click', function() {
                var $button = $(this);
                $button.prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sewn_ws_test_ssl',
                        cert: $('#sewn_ws_ssl_cert').val(),
                        key: $('#sewn_ws_ssl_key').val(),
                        nonce: sewnWsAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('SSL configuration is valid.');
                        } else {
                            alert('SSL configuration is invalid: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Failed to test SSL configuration.');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                });
            });

            // Initial state
            $('#sewn_ws_local_mode').trigger('change');
            updateUrlStatus($('#sewn_ws_local_site_url').val());
        });
        </script>

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

    <!-- Debug Panel -->
    <div class="sewn-ws-debug-panel" style="display: <?php echo get_option('sewn_ws_debug_enabled', false) ? 'block' : 'none'; ?>">
        <!-- Add debug info div -->
        <div id="sewn-ws-debug-info" style="background: #f8f9fa; padding: 10px; margin-bottom: 10px; display: none;">
            <h4 style="margin: 0 0 10px 0;">Debug Panel State:</h4>
            <pre style="margin: 0; white-space: pre-wrap;"></pre>
        </div>
        
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

