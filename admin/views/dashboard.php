<?php

/**
 * LOCATION: admin/views/dashboard.php
 * DEPENDENCIES: Server_Controller stats data
 * VARIABLES: $data (array containing environment and stats)
 * CLASSES: None (template file)
 * 
 * Renders primary WebSocket server dashboard with real-time connection metrics and controls. Displays network
 * health status aligned with Startempire Wire's WebRing architecture requirements. Integrates membership-tier
 * visualization for network operators.
 */

if (!defined('ABSPATH')) exit;

use SEWN\WebSockets\Process_Manager;

// Extract environment data
$environment = $data['environment'] ?? [];
$node_status = $data['node_status'] ?? [];

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
    
    <?php 
    $env_type = $environment['container_info']['type'] ?? 'production';

    $env_classes = [
        'local_by_flywheel' => 'notice-info',
        'xampp' => 'notice-warning',
        'mamp' => 'notice-warning',
        'development' => 'notice-warning',
        'production' => 'notice-success'
    ];
    $env_icons = [
        'local_by_flywheel' => 'dashicons-desktop',
        'xampp' => 'dashicons-laptop',
        'mamp' => 'dashicons-laptop',
        'development' => 'dashicons-code-standards',
        'production' => 'dashicons-cloud'
    ];
    $env_labels = [
        'local_by_flywheel' => __('Local by Flywheel Environment', 'sewn-ws'),
        'xampp' => __('XAMPP Development Environment', 'sewn-ws'),
        'mamp' => __('MAMP Development Environment', 'sewn-ws'),
        'development' => __('Development Environment', 'sewn-ws'),
        'production' => __('Production Environment', 'sewn-ws')
    ];
    ?>

    <div class="sewn-ws-environment-panel">
        <div class="notice <?php echo esc_attr($env_classes[$env_type] ?? 'notice-info'); ?> inline">
            <div class="environment-header">
                <span class="dashicons <?php echo esc_attr($env_icons[$env_type] ?? 'dashicons-info'); ?>"></span>
                <h2><?php echo esc_html($env_labels[$env_type] ?? __('Unknown Environment', 'sewn-ws')); ?></h2>
            </div>
            
            <div class="environment-details">
                <div class="detail-column">
                    <h3><?php _e('Server Information', 'sewn-ws'); ?></h3>
                    <ul>
                        <li>
                            <strong><?php _e('Server Software:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($environment['server_info']['software'] ?? 'Unknown'); ?>
                        </li>
                        <li>
                            <strong><?php _e('Operating System:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($environment['server_info']['os'] ?? 'Unknown'); ?>
                        </li>
                        <li>
                            <strong><?php _e('PHP Version:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($environment['php_info']['version'] ?? 'Unknown'); ?>
                        </li>
                        <li>
                            <strong><?php _e('MySQL Version:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($environment['wordpress_info']['mysql_version'] ?? 'Unknown'); ?>
                        </li>
                    </ul>
                </div>

                <div class="detail-column">
                    <h3><?php _e('WordPress Configuration', 'sewn-ws'); ?></h3>
                    <ul>
                        <li>
                            <strong><?php _e('WordPress Version:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($environment['wordpress_info']['version'] ?? 'Unknown'); ?>
                        </li>
                        <li>
                            <strong><?php _e('SSL Enabled:', 'sewn-ws'); ?></strong>
                            <?php echo $environment['ssl_info']['has_valid_cert'] ? '✓' : '✗'; ?>
                        </li>
                        <li>
                            <strong><?php _e('Memory Limit:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($environment['php_info']['memory_limit'] ?? 'Unknown'); ?>
                        </li>
                        <li>
                            <strong><?php _e('Max Execution Time:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($environment['php_info']['max_execution_time'] ?? 'Unknown'); ?>s
                        </li>
                    </ul>
                </div>

                <?php if ($env_type !== 'production'): ?>
                <div class="detail-column development-info">
                    <h3><?php _e('Development Settings', 'sewn-ws'); ?></h3>
                    <ul>
                        <li>
                            <strong><?php _e('Upload Max Filesize:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($environment['php_info']['upload_max_filesize'] ?? 'Unknown'); ?>
                        </li>
                        <li>
                            <strong><?php _e('Post Max Size:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($environment['php_info']['post_max_size'] ?? 'Unknown'); ?>
                        </li>
                        <li>
                            <strong><?php _e('Max Input Vars:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($environment['php_info']['max_input_vars'] ?? 'Unknown'); ?>
                        </li>
                        <li>
                            <strong><?php _e('Max Input Time:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($environment['php_info']['max_input_time'] ?? 'Unknown'); ?>s
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (get_option('sewn_ws_local_mode', false)): ?>
    <div class="notice notice-info inline">
        <p>
            <span class="dashicons dashicons-desktop"></span>
            <?php _e('Running in Local Environment Mode', 'sewn-ws'); ?>
            <?php if (get_option('sewn_ws_container_mode', false)): ?>
                <span class="badge">Container Mode</span>
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>

    <div class="sewn-ws-grid">
        <!-- Server Status Panel -->
        <div class="sewn-ws-card status-panel">
            <h2 class="card-title">
                <span class="real-time-indicator <?php echo esc_attr($status_class); ?>"></span>
                <?php _e('Server Status', 'sewn-ws'); ?>
            </h2>
            
            <div class="sewn-ws-status <?php echo esc_attr($status_class); ?>">
                <span class="status-dot"></span>
                <span class="status-text">
                    <?php echo esc_html($status_text); ?>
                </span>
                <?php if (get_option('sewn_ws_local_mode', false)): ?>
                    <div class="environment-info">
                        <p>
                            <strong><?php _e('Environment:', 'sewn-ws'); ?></strong> 
                            <?php _e('Local Development', 'sewn-ws'); ?>
                        </p>
                        <p>
                            <strong><?php _e('Site URL:', 'sewn-ws'); ?></strong> 
                            <?php echo esc_html(get_option('sewn_ws_local_site_url', 'Not set')); ?>
                        </p>
                        <?php if (get_option('sewn_ws_container_mode', false)): ?>
                            <p>
                                <strong><?php _e('Container Mode:', 'sewn-ws'); ?></strong> 
                                <?php _e('Enabled', 'sewn-ws'); ?>
                            </p>
                        <?php endif; ?>
                        <p>
                            <strong><?php _e('SSL:', 'sewn-ws'); ?></strong> 
                            <?php echo get_option('sewn_ws_ssl_key', '') ? __('Custom Certificate', 'sewn-ws') : __('Local SSL', 'sewn-ws'); ?>
                        </p>
                    </div>
                <?php endif; ?>
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
            <h2 class="card-title">
                <span class="real-time-indicator <?php echo esc_attr($status_class); ?>"></span>
                <?php _e('Server Metrics', 'sewn-ws'); ?>
            </h2>
            <div id="server-stats" class="metrics-grid">
                <div class="metric-card">
                    <h3><?php _e('Connections', 'sewn-ws'); ?></h3>
                    <div class="metric-value" id="live-connections-count">
                        <?php 
                        $connections = Process_Manager::get_active_connections();
                        echo esc_html(count($connections));
                        ?>
                    </div>
                    <div class="connection-graph" id="connection-graph" 
                         data-history="<?php echo esc_attr(json_encode(get_option('sewn_ws_connection_history', []))); ?>">
                    </div>
                </div>
                <div class="metric-card">
                    <h3><?php _e('Message Rate', 'sewn-ws'); ?></h3>
                    <div class="metric-value" id="message-throughput">
                        <?php 
                        $throughput = Process_Manager::get_message_throughput();
                        echo esc_html(number_format($throughput['current'], 1) . ' msg/s');
                        ?>
                    </div>
                    <span class="trend-indicator" data-peak="<?php echo esc_attr($throughput['max']); ?>"></span>
                </div>
                <div class="metric-card">
                    <h3><?php _e('Memory Usage', 'sewn-ws'); ?></h3>
                    <div class="metric-value" id="memory-usage">
                        <?php 
                        $memory = get_option('sewn_ws_last_memory_usage', 0);
                        echo esc_html(number_format($memory / 1024 / 1024, 1) . ' MB');
                        ?>
                    </div>
                    <div class="memory-graph" id="memory-graph" 
                         data-history="<?php echo esc_attr(json_encode(get_option('sewn_ws_memory_history', []))); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Channel Statistics Panel -->
        <div class="sewn-ws-card channel-panel">
            <h2 class="card-title">
                <span class="real-time-indicator <?php echo esc_attr($status_class); ?>"></span>
                <?php _e('Channel Statistics', 'sewn-ws'); ?>
            </h2>
            <div class="channel-stats">
                <div class="channel-grid">
                    <div class="metric-card">
                        <h3><?php _e('Messages Processed', 'sewn-ws'); ?></h3>
                        <div id="channel-messages" class="metric-value">
                            <?php 
                            $queue = Process_Manager::get_message_queue_depth();
                            echo esc_html(number_format($queue['current']));
                            ?>
                        </div>
                        <span class="trend-indicator" 
                              data-warning="<?php echo esc_attr($queue['warning_threshold']); ?>"
                              data-max="<?php echo esc_attr($queue['max_capacity']); ?>">
                        </span>
                    </div>
                    <div class="metric-card">
                        <h3><?php _e('Active Subscribers', 'sewn-ws'); ?></h3>
                        <div id="channel-subscribers" class="metric-value">
                            <?php 
                            $subscribers = get_option('sewn_ws_active_subscribers', 0);
                            echo esc_html(number_format($subscribers));
                            ?>
                        </div>
                        <span class="trend-indicator" 
                              data-peak="<?php echo esc_attr(get_option('sewn_ws_peak_subscribers', 0)); ?>">
                        </span>
                    </div>
                    <div class="metric-card">
                        <h3><?php _e('Error Rate', 'sewn-ws'); ?></h3>
                        <div id="channel-errors" class="metric-value">
                            <?php 
                            $failure_rates = Process_Manager::get_failure_rates();
                            echo esc_html(number_format($failure_rates['last_hour'] * 100, 2) . '%');
                            ?>
                        </div>
                        <span class="trend-indicator" 
                              data-threshold="<?php echo esc_attr($failure_rates['threshold'] * 100); ?>%"
                              data-24h="<?php echo esc_attr($failure_rates['last_24h'] * 100); ?>%">
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Server History Panel -->
    <div class="sewn-ws-card history-panel">
        <h2 class="card-title">
            <span class="dashicons dashicons-backup"></span>
            <?php _e('Server History', 'sewn-ws'); ?>
        </h2>
        <div class="server-history">
            <?php 
            $history = Process_Manager::get_server_history();
            $last_stats = Process_Manager::get_last_stats();
            
            if (empty($history)): ?>
                <p class="no-history"><?php _e('No server history available yet.', 'sewn-ws'); ?></p>
            <?php else: ?>
                <div class="history-stats">
                    <div class="stat-item">
                        <h4><?php _e('Last Run Statistics', 'sewn-ws'); ?></h4>
                        <ul>
                            <li>
                                <strong><?php _e('Uptime:', 'sewn-ws'); ?></strong>
                                <?php echo human_time_diff(0, $last_stats['uptime']); ?>
                            </li>
                            <li>
                                <strong><?php _e('Peak Memory:', 'sewn-ws'); ?></strong>
                                <?php echo size_format($last_stats['memory'], 2); ?>
                            </li>
                            <li>
                                <strong><?php _e('Peak Connections:', 'sewn-ws'); ?></strong>
                                <?php echo number_format($last_stats['connections']); ?>
                            </li>
                            <li>
                                <strong><?php _e('Message Rate:', 'sewn-ws'); ?></strong>
                                <?php echo number_format($last_stats['message_rate'], 1); ?> msg/s
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="history-timeline">
                    <h4><?php _e('Recent Activity', 'sewn-ws'); ?></h4>
                    <ul>
                    <?php 
                    $history = array_reverse($history);
                    foreach (array_slice($history, 0, 10) as $entry): 
                        $time_diff = human_time_diff($entry['time'], time());
                        $icon = $entry['action'] === 'start' ? 'play' : 'stop';
                        $class = $entry['action'] === 'start' ? 'start' : 'stop';
                    ?>
                        <li class="history-entry <?php echo esc_attr($class); ?>">
                            <span class="dashicons dashicons-controls-<?php echo esc_attr($icon); ?>"></span>
                            <div class="entry-details">
                                <span class="action">
                                    <?php 
                                    echo $entry['action'] === 'start' 
                                        ? __('Server Started', 'sewn-ws')
                                        : __('Server Stopped', 'sewn-ws');
                                    ?>
                                </span>
                                <span class="time"><?php echo esc_html($time_diff . ' ' . __('ago', 'sewn-ws')); ?></span>
                                <?php if ($entry['action'] === 'start'): ?>
                                    <span class="port">
                                        <?php echo sprintf(__('Port: %d', 'sewn-ws'), $entry['port']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($entry['action'] === 'stop' && isset($entry['uptime'])): ?>
                                    <span class="uptime">
                                        <?php echo sprintf(__('Uptime: %s', 'sewn-ws'), human_time_diff(0, $entry['uptime'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
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
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.metric-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.metric-card h3 {
    margin: 0 0 10px 0;
    color: #666;
    font-size: 14px;
}

.metric-value {
    font-size: 28px;
    font-weight: 600;
    color: #2c3e50;
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
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
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

/* Add smooth transitions */
.metric-value {
    transition: all 0.3s ease-out;
}

.metric-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.3s ease;
    position: relative;
    overflow: hidden;
}

.metric-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
    transform: translateX(-100%);
}

.metric-card.updating::after {
    animation: shimmer 1s ease-out;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Add pulse animation for status dot */
.status-dot {
    transition: all 0.3s ease;
}

.sewn-ws-status.running .status-dot {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}

/* Add trend indicators */
.trend-indicator {
    display: inline-block;
    margin-left: 5px;
    font-size: 12px;
    transition: all 0.3s ease;
}

.trend-up { color: #28a745; }
.trend-down { color: #dc3545; }

/* Add connection graph */
.connection-graph {
    width: 100%;
    height: 100px;
    margin-top: 15px;
    background: rgba(0,0,0,0.02);
    border-radius: 4px;
}

/* Update real-time indicator styles */
.real-time-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 5px;
    transition: all 0.3s ease;
}

.real-time-indicator.running {
    background: #28a745;
    animation: blink 1s infinite;
}

.real-time-indicator.stopped {
    background: #dc3545;
}

.real-time-indicator.error {
    background: #dc3545;
    animation: pulse 1s infinite;
}

.real-time-indicator.uninitialized {
    background: #6c757d;
}

.real-time-indicator.starting {
    background: #ffc107;
    animation: pulse 1s infinite;
}

@keyframes blink {
    0% { opacity: 1; }
    50% { opacity: 0.4; }
    100% { opacity: 1; }
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}

/* Add loading states */
.loading {
    position: relative;
}

.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.notice.inline {
    margin: 15px 0;
    padding: 10px 15px;
    display: flex;
    align-items: center;
}

.notice .dashicons {
    margin-right: 10px;
    font-size: 20px;
}

.badge {
    background: #2271b1;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    margin-left: 10px;
    font-size: 12px;
}

.environment-info {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.environment-info p {
    margin: 5px 0;
    font-size: 13px;
}

.environment-info strong {
    color: #1d2327;
}

/* Environment Panel Styles */
.sewn-ws-environment-panel {
    margin: 20px 0;
}

.sewn-ws-environment-panel .notice {
    margin: 0;
    padding: 20px;
    border-left-width: 5px;
}

.environment-header {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.environment-header .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    margin-right: 10px;
}

.environment-header h2 {
    margin: 0;
    padding: 0;
    font-size: 1.3em;
    line-height: 1.4;
}

.environment-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
}

.detail-column h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #1d2327;
}

.detail-column ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.detail-column ul li {
    margin-bottom: 8px;
    font-size: 13px;
    line-height: 1.5;
}

.detail-column ul li strong {
    display: inline-block;
    min-width: 140px;
    color: #50575e;
}

.development-info {
    background: rgba(0, 0, 0, 0.03);
    padding: 15px;
    border-radius: 4px;
}

/* Responsive Design */
@media screen and (max-width: 782px) {
    .environment-details {
        grid-template-columns: 1fr;
    }
    
    .detail-column ul li strong {
        display: block;
        margin-bottom: 2px;
    }
}

/* Server History Panel Styles */
.history-panel {
    margin-top: 20px;
}

.history-panel .card-title {
    display: flex;
    align-items: center;
}

.history-panel .dashicons {
    margin-right: 10px;
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.server-history {
    padding: 15px;
}

.history-stats {
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}

.history-stats ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.history-stats li {
    margin-bottom: 8px;
    font-size: 13px;
}

.history-stats strong {
    display: inline-block;
    min-width: 120px;
    color: #50575e;
}

.history-timeline {
    border-top: 1px solid #eee;
    padding-top: 15px;
}

.history-timeline h4 {
    margin: 0 0 15px 0;
    color: #1d2327;
}

.history-timeline ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.history-entry {
    display: flex;
    align-items: flex-start;
    padding: 10px;
    margin-bottom: 10px;
    background: #f8f9fa;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.history-entry:hover {
    background: #f0f0f1;
}

.history-entry.start {
    border-left: 3px solid #00c853;
}

.history-entry.stop {
    border-left: 3px solid #ff9800;
}

.history-entry .dashicons {
    margin-right: 10px;
    color: #50575e;
}

.entry-details {
    flex: 1;
}

.entry-details .action {
    display: block;
    font-weight: 600;
    color: #1d2327;
}

.entry-details .time,
.entry-details .port,
.entry-details .uptime {
    display: inline-block;
    margin-right: 15px;
    color: #666;
    font-size: 12px;
}

.no-history {
    text-align: center;
    color: #666;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 4px;
}

@media screen and (max-width: 782px) {
    .history-stats li strong {
        display: block;
        margin-bottom: 4px;
    }
    
    .entry-details .time,
    .entry-details .port,
    .entry-details .uptime {
        display: block;
        margin: 5px 0;
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