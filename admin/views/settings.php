<?php
namespace SEWN\WebSockets\Admin;


if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div id="sewn-ws-validation-summary"></div>

    <form method="post" action="options.php" id="sewn-ws-settings-form">
        <?php 
        settings_fields('sewn_ws_settings');
        wp_nonce_field('sewn_ws_settings', 'sewn_ws_nonce');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sewn_ws_port">WebSocket Port</label>
                </th>
                <td>
                    <input 
                        type="number" 
                        id="sewn_ws_port" 
                        name="sewn_ws_port" 
                        value="<?php echo esc_attr(get_option('sewn_ws_port', '8080')); ?>"
                        class="sewn-ws-validate-field"
                        data-validate="port"
                    >
                    <span class="sewn-ws-validation-feedback"></span>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="sewn_ws_host">WebSocket Host</label>
                </th>
                <td>
                    <input 
                        type="text" 
                        id="sewn_ws_host" 
                        name="sewn_ws_host" 
                        value="<?php echo esc_attr(get_option('sewn_ws_host', 'localhost')); ?>"
                        class="sewn-ws-validate-field"
                        data-validate="host"
                    >
                    <span class="sewn-ws-validation-feedback"></span>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="sewn_ws_rate_limit">Rate Limit (messages/minute)</label>
                </th>
                <td>
                    <input 
                        type="number" 
                        id="sewn_ws_rate_limit" 
                        name="sewn_ws_rate_limit" 
                        value="<?php echo esc_attr(get_option('sewn_ws_rate_limit', '60')); ?>"
                        class="sewn-ws-validate-field"
                        data-validate="rate"
                    >
                    <span class="sewn-ws-validation-feedback"></span>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="button" id="sewn-ws-test-config" class="button-secondary">
                Test Configuration
            </button>
            <div id="sewn-ws-test-result"></div>
            <?php submit_button(); ?>
        </p>
    </form>
</div>