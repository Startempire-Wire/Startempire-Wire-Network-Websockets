<?php
// Set safe defaults
$node_status = $node_status ?? ['version' => 'N/A', 'running' => false];
$status_class = $node_status['running'] ? 'success' : 'error';
$status_text = $node_status['running'] ? '✓ Operational' : '✗ Needs Attention';
?>

<div class="wrap sewn-ws-dashboard">
    <h1><?php _e('WebSocket Server Dashboard', 'sewn-ws') ?></h1>
    
    <!-- Server Status Card -->
    <div class="sewn-ws-card status-card">
        <h2><?php _e('Server Status', 'sewn-ws') ?></h2>
        <div class="sewn-ws-status <?php echo esc_attr($status_class); ?>">
            <span class="status-icon"></span>
            <span class="status-text"><?php echo esc_html($status_text); ?></span>
        </div>
        <div class="server-controls">
            <button class="button-primary sewn-ws-control" data-action="start" <?php echo $node_status['running'] ? 'disabled' : ''; ?>>
                <?php _e('Start Server', 'sewn-ws') ?>
            </button>
            <button class="button-secondary sewn-ws-control" data-action="restart">
                <?php _e('Restart', 'sewn-ws') ?>
            </button>
            <button class="button sewn-ws-control" data-action="stop" <?php echo !$node_status['running'] ? 'disabled' : ''; ?>>
                <?php _e('Stop', 'sewn-ws') ?>
            </button>
        </div>
    </div>

    <!-- Real-time Stats -->
    <div class="sewn-ws-stats-grid">
        <div class="stats-card">
            <h3><?php _e('Active Connections', 'sewn-ws'); ?></h3>
            <div class="stat-value" id="connections-count">-</div>
            <div class="stat-graph" data-metric="connections"></div>
        </div>
        
        <div class="stats-card">
            <h3><?php _e('Memory Usage', 'sewn-ws'); ?></h3>
            <div class="stat-value" id="memory-usage">-</div>
            <div class="stat-graph" data-metric="memory"></div>
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

<script>
jQuery(document).ready(function($) {
    // Server controls
    $('.sewn-ws-control').click(function() {
        var button = $(this);
        $.post(ajaxurl, {
            action: 'sewn_ws_server_control',
            command: button.data('action'),
            nonce: '<?php echo wp_create_nonce('sewn_ws_control') ?>'
        }, function(response) {
            location.reload();
        });
    });

    // Stats polling
    function updateStats() {
        $.get(ajaxurl, {
            action: 'sewn_ws_get_stats'
        }, function(response) {
            $('#connection-count').text(response.connections);
            $('#bandwidth-usage').text(response.bandwidth);
        });
    }
    setInterval(updateStats, 2000);

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