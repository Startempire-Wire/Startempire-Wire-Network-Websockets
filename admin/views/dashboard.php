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
// Define default values
$default_status = [
    'version' => 'N/A',
    'running' => false,
    'status' => 'uninitialized'
];

// Ensure $node_status is an array and merge with defaults
$node_status = is_array($node_status) ? array_merge($default_status, $node_status) : $default_status;

// Determine status class and text
$status_class = $node_status['running'] ? 'running' : 
    (isset($node_status['status']) && $node_status['status'] === 'uninitialized' ? 'uninitialized' : 'stopped');

$status_text = $node_status['running'] ? '✓ Operational' : 
    (isset($node_status['status']) && $node_status['status'] === 'uninitialized' ? 'Uninitialized' : '✗ Stopped');
?>

<div class="wrap sewn-ws-dashboard">
    <h1 class="wp-heading-inline"><?php _e('WebSocket Server Dashboard', 'sewn-ws'); ?></h1>
    
    <div class="sewn-ws-grid">
        <!-- Server Status Panel -->
        <div class="sewn-ws-card status-panel">
            <h2 class="card-title"><?php _e('Server Status', 'sewn-ws'); ?></h2>
            
            <div class="sewn-ws-status <?php echo esc_attr($status_class); ?>">
                <span class="status-dot"></span>
                <span class="status-text">
                    <?php echo esc_html($status_text); ?>
                </span>
            </div>

            <div class="sewn-ws-controls">
                <button class="button button-primary" data-action="start" <?php echo $node_status['running'] ? 'disabled' : ''; ?>>
                    <?php _e('Start Server', 'sewn-ws'); ?>
                </button>
                <button class="button button-secondary" data-action="stop" <?php echo !$node_status['running'] ? 'disabled' : ''; ?>>
                    <?php _e('Stop Server', 'sewn-ws'); ?>
                </button>
                <button class="button button-secondary" data-action="restart" <?php echo !$node_status['running'] ? 'disabled' : ''; ?>>
                    <?php _e('Restart Server', 'sewn-ws'); ?>
                </button>
            </div>
        </div>

        <!-- Server Metrics Panel -->
        <div class="sewn-ws-card metrics-panel">
            <h2 class="card-title"><?php _e('Server Metrics', 'sewn-ws'); ?></h2>
            <div id="server-stats" class="metrics-grid">
                <div class="metric-card">
                    <h3><?php _e('Connections', 'sewn-ws'); ?></h3>
                    <div class="metric-value" id="live-connections-count">0</div>
                </div>
                <div class="metric-card">
                    <h3><?php _e('Message Rate', 'sewn-ws'); ?></h3>
                    <div class="metric-value" id="message-throughput">0 msg/s</div>
                </div>
                <div class="metric-card">
                    <h3><?php _e('Memory Usage', 'sewn-ws'); ?></h3>
                    <div class="metric-value" id="memory-usage">0 MB</div>
                </div>
            </div>
        </div>

        <!-- Channel Statistics Panel -->
        <div class="sewn-ws-card channel-panel">
            <h2 class="card-title"><?php _e('Channel Statistics', 'sewn-ws'); ?></h2>
            <div class="channel-stats">
                <div class="channel-grid">
                    <div class="channel-metric">
                        <h3><?php _e('Messages Processed', 'sewn-ws'); ?></h3>
                        <div id="channel-messages" class="metric-value">0</div>
                    </div>
                    <div class="channel-metric">
                        <h3><?php _e('Active Subscribers', 'sewn-ws'); ?></h3>
                        <div id="channel-subscribers" class="metric-value">0</div>
                    </div>
                    <div class="channel-metric">
                        <h3><?php _e('Error Rate', 'sewn-ws'); ?></h3>
                        <div id="channel-errors" class="metric-value">0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Grid Layout */
.sewn-ws-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

/* Cards */
.sewn-ws-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
    border-radius: 4px;
}

.card-title {
    margin: 0 0 20px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    font-size: 16px;
    font-weight: 600;
}

/* Status Panel */
.sewn-ws-status {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}

.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.sewn-ws-status.running .status-dot { 
    background: #00c853; 
    animation: pulse 2s infinite; 
}
.sewn-ws-status.stopped .status-dot { background: #ff9800; }
.sewn-ws-status.error .status-dot { background: #ff1744; }
.sewn-ws-status.uninitialized .status-dot { background: #9e9e9e; }
.sewn-ws-status.starting .status-dot { 
    background: #2196f3; 
    animation: pulse 1s infinite; 
}

.sewn-ws-controls {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Metrics Grid */
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.metric-card {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
    text-align: center;
}

.metric-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
}

.metric-value {
    font-size: 24px;
    font-weight: 600;
    color: #1e1e1e;
}

/* Channel Statistics */
.channel-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.channel-metric {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
    text-align: center;
}

.channel-metric h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
}

/* Animations */
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

/* Responsive Design */
@media screen and (max-width: 782px) {
    .sewn-ws-grid {
        grid-template-columns: 1fr;
    }
    
    .sewn-ws-controls {
        flex-direction: column;
    }
    
    .sewn-ws-controls button {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Export initial status for admin.js
    window.SEWN_WS_INITIAL_STATUS = {
        running: <?php echo json_encode($node_status['running']); ?>,
        status: <?php echo json_encode($node_status['status']); ?>,
        version: <?php echo json_encode($node_status['version']); ?>
    };
});
</script> 