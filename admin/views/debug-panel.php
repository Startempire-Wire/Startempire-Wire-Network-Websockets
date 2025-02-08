<?php

namespace SEWN\WebSockets\Admin;

if (!defined('ABSPATH')) exit;

$logger = Error_Logger::get_instance();
$monitor = Environment_Monitor::get_instance();

$recent_errors = $logger->get_recent_errors();
$environment_status = $monitor->get_environment_status();
?>

<div class="wrap sewn-ws-debug-panel">
    <h1><?php _e('WebSocket Server Debug Panel', 'sewn-ws'); ?></h1>

    <!-- Environment Status -->
    <div class="sewn-ws-debug-section">
        <h2>
            <span class="dashicons dashicons-desktop"></span>
            <?php _e('Environment Status', 'sewn-ws'); ?>
            <span class="status-badge <?php echo esc_attr($environment_status['validation']['status']); ?>">
                <?php echo esc_html(ucfirst($environment_status['validation']['status'])); ?>
            </span>
        </h2>

        <?php if (!empty($environment_status['validation']['errors'])): ?>
            <div class="notice notice-error inline">
                <h3><?php _e('Errors:', 'sewn-ws'); ?></h3>
                <ul>
                    <?php foreach ($environment_status['validation']['errors'] as $error): ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($environment_status['validation']['warnings'])): ?>
            <div class="notice notice-warning inline">
                <h3><?php _e('Warnings:', 'sewn-ws'); ?></h3>
                <ul>
                    <?php foreach ($environment_status['validation']['warnings'] as $warning): ?>
                        <li><?php echo esc_html($warning); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="environment-details">
            <div class="detail-column">
                <h3><?php _e('System Information', 'sewn-ws'); ?></h3>
                <table class="widefat">
                    <tbody>
                        <?php foreach ($environment_status['environment']['system_info'] as $key => $value): ?>
                            <tr>
                                <th><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></th>
                                <td><?php echo is_array($value) ? '<pre>' . esc_html(print_r($value, true)) . '</pre>' : esc_html($value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($environment_status['environment']['type'] === 'local_by_flywheel'): ?>
            <div class="detail-column">
                <h3><?php _e('Local Environment Details', 'sewn-ws'); ?></h3>
                <table class="widefat">
                    <tbody>
                        <?php foreach ($environment_status['environment']['local_info'] as $key => $value): ?>
                            <tr>
                                <th><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></th>
                                <td><?php echo is_array($value) ? '<pre>' . esc_html(print_r($value, true)) . '</pre>' : esc_html($value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Errors -->
    <div class="sewn-ws-debug-section">
        <h2>
            <span class="dashicons dashicons-warning"></span>
            <?php _e('Recent Errors', 'sewn-ws'); ?>
            <button class="button-secondary clear-logs"><?php _e('Clear Logs', 'sewn-ws'); ?></button>
            <button class="button-secondary export-logs"><?php _e('Export Logs', 'sewn-ws'); ?></button>
        </h2>

        <div class="error-log-container">
            <?php if (empty($recent_errors)): ?>
                <p class="no-errors"><?php _e('No recent errors found.', 'sewn-ws'); ?></p>
            <?php else: ?>
                <table class="widefat error-log-table">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'sewn-ws'); ?></th>
                            <th><?php _e('Level', 'sewn-ws'); ?></th>
                            <th><?php _e('Message', 'sewn-ws'); ?></th>
                            <th><?php _e('Context', 'sewn-ws'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_errors as $error): ?>
                            <tr class="error-level-<?php echo esc_attr(strtolower($error['level'])); ?>">
                                <td><?php echo esc_html($error['time']); ?></td>
                                <td>
                                    <span class="error-level"><?php echo esc_html($error['level']); ?></span>
                                </td>
                                <td><?php echo esc_html($error['message']); ?></td>
                                <td>
                                    <pre><?php echo esc_html(json_encode($error['context'], JSON_PRETTY_PRINT)); ?></pre>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.sewn-ws-debug-panel {
    margin: 20px 0;
}

.sewn-ws-debug-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-bottom: 20px;
    padding: 20px;
}

.sewn-ws-debug-section h2 {
    display: flex;
    align-items: center;
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.sewn-ws-debug-section h2 .dashicons {
    margin-right: 10px;
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.status-badge {
    margin-left: auto;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: normal;
    text-transform: uppercase;
}

.status-badge.valid { background: #46b450; color: #fff; }
.status-badge.warning { background: #ffb900; color: #fff; }
.status-badge.invalid { background: #dc3232; color: #fff; }

.environment-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.detail-column h3 {
    margin: 0 0 10px 0;
}

.error-log-container {
    margin-top: 20px;
    max-height: 500px;
    overflow-y: auto;
}

.error-log-table {
    border-collapse: collapse;
    width: 100%;
}

.error-log-table th,
.error-log-table td {
    padding: 8px;
    text-align: left;
}

.error-level {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    text-transform: uppercase;
}

.error-level-error .error-level { background: #dc3232; color: #fff; }
.error-level-warning .error-level { background: #ffb900; color: #fff; }
.error-level-critical .error-level { background: #dc3232; color: #fff; }
.error-level-info .error-level { background: #00a0d2; color: #fff; }

.no-errors {
    text-align: center;
    padding: 20px;
    color: #666;
}

.clear-logs,
.export-logs {
    margin-left: 10px;
}

pre {
    margin: 0;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 3px;
    overflow-x: auto;
    font-size: 12px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Clear logs
    $('.clear-logs').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to clear all logs?', 'sewn-ws'); ?>')) {
            $.post(ajaxurl, {
                action: 'sewn_ws_clear_logs',
                nonce: '<?php echo wp_create_nonce('sewn_ws_clear_logs'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('<?php _e('Failed to clear logs. Please try again.', 'sewn-ws'); ?>');
                }
            });
        }
    });

    // Export logs
    $('.export-logs').on('click', function() {
        window.location.href = ajaxurl + '?action=sewn_ws_export_logs&nonce=<?php echo wp_create_nonce('sewn_ws_export_logs'); ?>';
    });
});
</script> 