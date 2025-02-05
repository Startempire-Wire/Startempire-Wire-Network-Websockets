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
    <h1>WebSocket Server Dashboard</h1>
    <?php do_action('sewn_ws_before_dashboard'); ?>
    <div class="server-status-container">
        <!-- Server Status Card -->
        <div class="sewn-ws-card status-card">
            <h2><?php _e('Server Status', 'sewn-ws') ?></h2>
            <div class="status-indicator">
                <span class="status-dot <?php echo esc_attr($status_class); ?>"></span>
                <span class="status-text">
                    <?php if($node_status['running']): ?>
                        <?php _e('Operational', 'sewn-ws') ?>
                    <?php else: ?>
                        <?php _e('Offline', 'sewn-ws') ?>
                    <?php endif; ?>
                </span>
                <span class="loading-spinner" style="display:none"></span>
            </div>
            <div class="server-controls">
                <button class="button-primary sewn-ws-control" data-action="start" 
                        <?php echo $node_status['running'] ? 'disabled' : ''; ?>>
                    <span class="button-text"><?php _e('Start Server', 'sewn-ws') ?></span>
                    <span class="loading-dots" style="display:none">...</span>
                </button>
                <button class="button-secondary sewn-ws-control" data-action="restart">
                    <?php _e('Restart', 'sewn-ws') ?>
                </button>
                <button class="button sewn-ws-control" data-action="stop" <?php echo !$node_status['running'] ? 'disabled' : ''; ?>>
                    <?php _e('Stop', 'sewn-ws') ?>
                </button>
            </div>
            <div class="emergency-controls">
                <button type="button" 
                        id="emergency-stop"
                        class="button button-danger"
                        title="Cancel all pending requests">
                    <span class="dashicons dashicons-dismiss"></span>
                    Emergency Stop
                </button>
            </div>
        </div>

        <!-- Real-time Stats -->
        <div class="sewn-ws-stats-container">
            <div class="stats-card connections">
                <h3><?php _e('Active Connections', 'sewn-ws'); ?></h3>
                <div id="live-connections-count">-</div>
                <div id="connections-graph"></div>
            </div>
            
            <div class="stats-card messages">
                <h3><?php _e('Message Throughput', 'sewn-ws'); ?></h3>
                <div id="message-throughput">- msg/s</div>
                <div id="throughput-graph"></div>
            </div>
        </div>

        <!-- Server Logs -->
        <div class="sewn-ws-card logs-card">
            <h2><?php _e('Server Logs', 'sewn-ws') ?></h2>
            <div class="log-controls">
                <select id="log-level">
                    <option value="all">All Logs</option>
                    <option value="error">Errors Only</option>
                    <option value="info">Info</option>
                </select>
                <button class="button" id="clear-logs"><?php _e('Clear Logs', 'sewn-ws') ?></button>
            </div>
            <div class="log-container" id="server-logs"></div>
        </div>
    </div>
</div>

<style>
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

.status-dot.success { background: #00c853; animation: pulse 2s infinite; }
.status-dot.error { background: #ff1744; }
.status-dot.starting { background: #ffc107; animation: pulse 1s infinite; }

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

button[disabled] .loading-dots {
    display: inline-block;
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