<?php

/**
 * LOCATION: admin/views/dashboard.php
 * DEPENDENCIES: Admin_UI class, WordPress admin styles
 * VARIABLES: $node_status (server state data)
 * CLASSES: None (template file)
 * 
 * Renders primary WebSocket server dashboard with real-time connection metrics and controls. Displays network
 * health status aligned with Startempire Wire's WebRing architecture requirements. Integrates membership-tier
 * visualization for network operators.
 */

if (!defined('ABSPATH')) exit; 

/** @var array $node_status */
$node_status = $node_status ?? ['version' => 'N/A', 'running' => false, 'status' => 'stopped'];
$status_class = $node_status['running'] ? 'success' : 'error';
$status_text = $node_status['running'] ? '✓ Operational' : '✗ Needs Attention';
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (get_option('sewn_ws_dev_mode', false)): ?>
    <div class="notice notice-warning">
        <p>
            <span class="dashicons dashicons-code-standards"></span>
            <?php _e('Development Mode Enabled - Using HTTP WebSocket connections', 'sewn-ws'); ?>
        </p>
    </div>
    <?php endif; ?>
    
    <?php do_action('sewn_ws_before_dashboard'); ?>
    <div class="server-status-container">
        <!-- Server Status Card -->
        <div class="sewn-ws-card status-card">
            <h2><?php _e('Server Status', 'sewn-ws') ?></h2>
            <div class="status-indicator">
                <span class="status-dot <?php echo esc_attr($node_status['status'] ?? 'uninitialized'); ?>"></span>
                <span class="status-text">
                    <?php 
                    $status = $node_status['status'] ?? 'uninitialized';
                    switch($status) {
                        case 'running':
                            _e('Operational', 'sewn-ws');
                            break;
                        case 'stopped':
                            _e('Stopped', 'sewn-ws');
                            break;
                        case 'error':
                            _e('Error', 'sewn-ws');
                            break;
                        case 'uninitialized':
                        default:
                            _e('Not Initialized', 'sewn-ws');
                            break;
                    }
                    ?>
                </span>
                <span class="loading-spinner" style="display:none"></span>
            </div>
            <div class="server-controls">
                <button class="button-primary sewn-ws-control" data-action="start" 
                        <?php echo ($status === 'running') ? 'disabled' : ''; ?>>
                    <span class="button-text">
                        <?php echo ($status === 'uninitialized') ? __('Initialize Server', 'sewn-ws') : __('Start Server', 'sewn-ws'); ?>
                    </span>
                    <span class="loading-dots" style="display:none">...</span>
                </button>
                <button class="button-secondary sewn-ws-control" data-action="restart"
                        <?php echo ($status !== 'running') ? 'disabled' : ''; ?>>
                    <?php _e('Restart', 'sewn-ws') ?>
                </button>
                <button class="button sewn-ws-control" data-action="stop"
                        <?php echo ($status !== 'running') ? 'disabled' : ''; ?>>
                    <?php _e('Stop', 'sewn-ws') ?>
                </button>
            </div>

            <?php if ($status === 'running'): ?>
            <div class="server-metrics">
                <div class="metric">
                    <span class="metric-label"><?php _e('Uptime', 'sewn-ws'); ?></span>
                    <span class="metric-value" id="server-uptime">-</span>
                </div>
                <div class="metric">
                    <span class="metric-label"><?php _e('Memory', 'sewn-ws'); ?></span>
                    <span class="metric-value" id="server-memory">-</span>
                </div>
            </div>
            <?php endif; ?>

            <div class="emergency-controls">
                <button type="button" 
                        id="emergency-stop"
                        class="button button-danger"
                        <?php echo ($status !== 'running') ? 'disabled' : ''; ?>
                        title="<?php esc_attr_e('Emergency stop - cancels all pending operations', 'sewn-ws'); ?>">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php _e('Emergency Stop', 'sewn-ws'); ?>
                </button>
            </div>
        </div>

        <!-- Real-time Stats -->
        <div class="sewn-ws-stats-container">
            <div class="stats-card connections">
                <h3><?php _e('Active Connections', 'sewn-ws'); ?></h3>
                <div class="stat-value" id="live-connections-count">-</div>
                <div class="stat-graph" id="connections-graph"></div>
            </div>
            
            <div class="stats-card messages">
                <h3><?php _e('Message Throughput', 'sewn-ws'); ?></h3>
                <div class="stat-value" id="message-throughput">- msg/s</div>
                <div class="stat-graph" id="throughput-graph"></div>
            </div>
        </div>

        <!-- Server Logs -->
        <div class="sewn-ws-card logs-card">
            <h2>
                <?php _e('Server Logs', 'sewn-ws') ?>
                <span class="log-count" id="log-count">0</span>
            </h2>
            <div class="log-controls">
                <select id="log-level">
                    <option value="all"><?php _e('All Logs', 'sewn-ws'); ?></option>
                    <option value="error"><?php _e('Errors Only', 'sewn-ws'); ?></option>
                    <option value="info"><?php _e('Info', 'sewn-ws'); ?></option>
                </select>
                <button class="button" id="clear-logs">
                    <?php _e('Clear Logs', 'sewn-ws') ?>
                </button>
            </div>
            <div class="log-container" id="server-logs"></div>
        </div>
    </div>
</div>

<style>
.sewn-ws-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
    margin-bottom: 20px;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 15px 0;
}

.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #ccc;
    transition: all 0.3s ease;
}

.status-dot.running { background: #00c853; animation: pulse 2s infinite; }
.status-dot.error { background: #ff1744; }
.status-dot.stopped { background: #ff9800; }
.status-dot.starting { background: #ffc107; animation: pulse 1s infinite; }
.status-dot.uninitialized { background: #9e9e9e; }

.server-controls {
    margin: 20px 0;
    display: flex;
    gap: 10px;
}

.server-metrics {
    display: flex;
    gap: 20px;
    margin: 15px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}

.metric {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.metric-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}

.metric-value {
    font-size: 16px;
    font-weight: 500;
}

.sewn-ws-stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stats-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 15px;
    border-radius: 4px;
}

.stat-value {
    font-size: 24px;
    font-weight: 500;
    margin: 10px 0;
}

.stat-graph {
    height: 100px;
    background: #f8f9fa;
    border-radius: 4px;
}

.logs-card {
    margin-top: 20px;
}

.log-controls {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.log-container {
    height: 300px;
    overflow-y: auto;
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    font-family: monospace;
}

.log-count {
    font-size: 12px;
    background: #e5e5e5;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 8px;
    vertical-align: middle;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.loading-spinner {
    border: 2px solid #f3f3f3;
    border-top: 2px solid #3498db;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    animation: spin 1s linear infinite;
    display: none;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.button-danger {
    background: #dc3232;
    border-color: #dc3232;
    color: white;
}

.button-danger:hover {
    background: #a82828;
    border-color: #a82828;
    color: white;
}

.button-danger:disabled {
    background: #e5e5e5 !important;
    border-color: #ddd !important;
    color: #999 !important;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Server controls
    $('.sewn-ws-control').click(function() {
        var button = $(this);
        var action = button.data('action');
        
        // Store original text
        button.data('original-text', button.find('.button-text').text());
        
        // Show loading state
        $('.status-dot').removeClass('success error')
                       .addClass('starting');
        $('.loading-spinner').show();
        button.prop('disabled', true)
              .find('.loading-dots').show();
        button.find('.button-text').text(
            action.charAt(0).toUpperCase() + action.slice(1) + 'ing...'
        );

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'sewn_ws_control',
                command: action,
                nonce: sewn_ws_admin.nonce
            },
            success: function(response) {
                if (!response.success) {
                    console.error('Server control failed:', response.data);
                    alert('Error: ' + response.data.message);
                }
                // Poll for updated status
                var pollStatus = setInterval(function() {
                    $.get(ajaxurl, {
                        action: 'sewn_ws_get_server_status'
                    }, function(statusResponse) {
                        if (statusResponse.running === (action !== 'stop')) {
                            clearInterval(pollStatus);
                            updateUI(statusResponse);
                        }
                    });
                }, 1000);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                let errorMessage = 'Server communication failed. Technical details:';
                const response = jqXHR.responseJSON || {};
                const data = response.data || {};
                
                errorMessage += '\n\n- ' + (data.message || 'No error message received');
                errorMessage += '\n- PHP: ' + (data.debug?.php_version || 'unknown');
                errorMessage += '\n- Node Path: ' + (data.debug?.node_path || 'undefined');
                
                console.error('Full error details:', response);
                alert(errorMessage);
                
                // Reset UI
                $('.status-dot').removeClass('starting').addClass('error');
                $('.loading-spinner').hide();
                button.prop('disabled', false)
                      .find('.loading-dots').hide();
                button.find('.button-text').text(button.data('original-text'));
            }
        });
    });

    function updateUI(status) {
        $('.status-dot')
            .removeClass('starting')
            .toggleClass('success', status.running)
            .toggleClass('error', !status.running);
        
        $('.status-text').text(
            status.running ? 'Operational' : 'Offline'
        );
        
        $('.loading-spinner').hide();
        $('.sewn-ws-control')
            .prop('disabled', function() {
                return $(this).data('action') === 'start' ? status.running : 
                       $(this).data('action') === 'stop' ? !status.running : false;
            })
            .find('.loading-dots').hide()
            .prev('.button-text').text(function() {
                return $(this).closest('button').data('action').replace(
                    /^\w/, c => c.toUpperCase()
                ) + ' Server';
            });
    }

    // Stats polling
    let statsPollInterval = null;

    function startStatsPolling() {
        if (!statsPollInterval) {
            statsPollInterval = setInterval(updateStats, 5000);
        }
    }

    function stopStatsPolling() {
        if (statsPollInterval) {
            clearInterval(statsPollInterval);
            statsPollInterval = null;
        }
    }

    // Update the initial status check to control polling
    $.get(ajaxurl, {action: 'sewn_ws_get_status'}, function(response) {
        if (response.status.running) {
            startStatsPolling();
        }
    });

    function updateStats() {
        $.get(ajaxurl, {
            action: 'sewn_ws_get_stats'
        }, function(response) {
            $('#live-connections-count').text(response.connections);
            $('#message-throughput').text(response.message_rate + ' msg/s');
        });
    }

    function updateChannelStats() {
        $.post(ajaxurl, {
            action: 'sewn_ws_get_channel_stats',
            _ajax_nonce: '<?php echo wp_create_nonce("sewn_ws_admin"); ?>'
        }, function(response) {
            if (response.success) {
                Object.keys(response.data).forEach(function(channel) {
                    $('#' + channel + '-messages').text(response.data[channel].messages_processed);
                    $('#' + channel + '-subscribers').text(response.data[channel].subscribers);
                    $('#' + channel + '-errors').text(response.data[channel].errors);
                });
            }
        });
    }
    
    setInterval(updateChannelStats, 5000);

    $('#initialize-server-button').on('click', function() {
        $(this).prop('disabled', true).text('Initializing...');
        $.post(ajaxurl, {
            action: 'sewn_ws_initialize_server',
            nonce: '<?php echo wp_create_nonce('sewn_ws_initialize_server_nonce') ?>'
        }, function(response) {
            if (response.success) {
                alert('WebSocket server initialized successfully!');
            } else {
                alert('Server initialization failed: ' + response.data.message);
            }
            $('#initialize-server-button').prop('disabled', false).text('Initialize WebSocket Server');
        }).fail(function() {
            alert('Server initialization request failed.');
            $('#initialize-server-button').prop('disabled', false).text('Initialize WebSocket Server');
        });
    });
});
</script> 