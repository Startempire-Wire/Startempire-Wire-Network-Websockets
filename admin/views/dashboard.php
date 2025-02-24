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

namespace SEWN\WebSockets\Admin;

if (!defined('ABSPATH')) exit;

use SEWN\WebSockets\Config;
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

// Get configuration from WordPress constants with enhanced protocol handling
$server_config = [
    'port' => \SEWN_WS_DEFAULT_PORT,
    'host' => \SEWN_WS_HOST,
    'environment' => [
        'type' => \SEWN_WS_ENV_TYPE,
        'is_local' => \SEWN_WS_IS_LOCAL,
        'ssl_enabled' => \SEWN_WS_SSL,
        'debug_enabled' => defined('WP_DEBUG') && WP_DEBUG
    ],
    'ssl' => [
        'enabled' => \SEWN_WS_SSL,
        'cert_path' => get_option(\SEWN_WS_OPTION_SSL_CERT),
        'key_path' => get_option(\SEWN_WS_OPTION_SSL_KEY)
    ],
    'server' => [
        'status' => get_option(\SEWN_WS_OPTION_SERVER_STATUS, \SEWN_WS_SERVER_STATUS_UNINITIALIZED),
        'connection_timeout' => 45000,
        'reconnection_attempts' => 3
    ],
    'stats' => [
        'update_interval' => \SEWN_WS_STATS_UPDATE_INTERVAL,
        'max_history_points' => \SEWN_WS_HISTORY_MAX_POINTS
    ],
    'nonce' => wp_create_nonce(\SEWN_WS_NONCE_ACTION),
    'ajax_url' => admin_url('admin-ajax.php'),
    'protocol' => \SEWN_WS_PROTOCOL,
    'page_protocol' => is_ssl() ? 'https' : 'http',
    'transports' => ['polling', 'websocket'],
    'socket' => [
        'path' => '/socket.io',
        'reconnection' => true,
        'reconnectionAttempts' => 5,
        'reconnectionDelay' => 1000,
        'reconnectionDelayMax' => 5000,
        'timeout' => 20000,
        'autoConnect' => false,
        'rejectUnauthorized' => false,
        'secure' => \SEWN_WS_SSL
    ]
];

?>

<div class="wrap sewn-ws-dashboard">
    <h1 class="wp-heading-inline"><?php _e('WebSocket Server Dashboard', 'sewn-ws'); ?></h1>
    
    <?php 
    // Enhanced environment type detection
    $env_type = $server_config['environment']['type'];
    $is_local = $server_config['environment']['is_local'];
    $ssl_enabled = $server_config['ssl']['enabled'];

    $env_classes = [
        'local' => 'notice-info',
        'staging' => 'notice-warning',
        'production' => 'notice-success'
    ];
    
    $env_icons = [
        'local' => 'dashicons-desktop',
        'staging' => 'dashicons-code-standards',
        'production' => 'dashicons-cloud'
    ];
    
    $env_labels = [
        'local' => __('Local Development Environment', 'sewn-ws'),
        'staging' => __('Staging Environment', 'sewn-ws'),
        'production' => __('Production Environment', 'sewn-ws')
    ];
    
    // Add protocol information to the environment panel
    $protocol_info = sprintf(
        '%s://%s:%d',
        $server_config['protocol'],
        $server_config['host'],
        $server_config['port']
    );
    ?>

    <div class="sewn-ws-environment-panel">
        <div class="notice <?php echo esc_attr($env_classes[$env_type] ?? 'notice-info'); ?> inline">
            <div class="environment-header">
                <span class="dashicons <?php echo esc_attr($env_icons[$env_type] ?? 'dashicons-info'); ?>"></span>
                <h2><?php echo esc_html($env_labels[$env_type] ?? __('Unknown Environment', 'sewn-ws')); ?></h2>
            </div>
            
            <div class="environment-details">
                <div class="detail-column">
                    <h3><?php _e('WebSocket Configuration', 'sewn-ws'); ?></h3>
                    <ul>
                        <li>
                            <strong><?php _e('Protocol:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($server_config['protocol']); ?>
                        </li>
                        <li>
                            <strong><?php _e('Host:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($server_config['host']); ?>
                        </li>
                        <li>
                            <strong><?php _e('Port:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($server_config['port']); ?>
                        </li>
                        <li>
                            <strong><?php _e('Full URL:', 'sewn-ws'); ?></strong>
                            <?php echo esc_html($protocol_info); ?>
                        </li>
                    </ul>
                </div>

                <div class="detail-column">
                    <h3><?php _e('SSL Configuration', 'sewn-ws'); ?></h3>
                    <ul>
                        <li>
                            <strong><?php _e('SSL Enabled:', 'sewn-ws'); ?></strong>
                            <?php echo $ssl_enabled ? '✓' : '✗'; ?>
                        </li>
                        <?php if ($ssl_enabled): ?>
                        <li>
                            <strong><?php _e('Certificate:', 'sewn-ws'); ?></strong>
                            <?php echo $server_config['ssl']['cert_path'] ? '✓' : '✗'; ?>
                        </li>
                        <li>
                            <strong><?php _e('Private Key:', 'sewn-ws'); ?></strong>
                            <?php echo $server_config['ssl']['key_path'] ? '✓' : '✗'; ?>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <?php if ($is_local): ?>
                <div class="detail-column development-info">
                    <h3><?php _e('Local Development Settings', 'sewn-ws'); ?></h3>
                    <ul>
                        <li>
                            <strong><?php _e('Debug Mode:', 'sewn-ws'); ?></strong>
                            <?php echo $server_config['environment']['debug_enabled'] ? '✓' : '✗'; ?>
                        </li>
                        <li>
                            <strong><?php _e('SSL Mode:', 'sewn-ws'); ?></strong>
                            <?php 
                            if ($ssl_enabled) {
                                echo $server_config['ssl']['cert_path'] ? __('Custom Certificate', 'sewn-ws') : __('Auto SSL', 'sewn-ws');
                            } else {
                                _e('Disabled', 'sewn-ws');
                            }
                            ?>
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
                <div class="server-info">
                    <p>
                        <strong><?php _e('Process ID:', 'sewn-ws'); ?></strong>
                        <span class="pid"><?php echo esc_html($node_status['pid'] ?? 'N/A'); ?></span>
                    </p>
                    <p>
                        <strong><?php _e('Uptime:', 'sewn-ws'); ?></strong>
                        <span class="uptime"><?php echo esc_html($node_status['uptime_formatted'] ?? '0s'); ?></span>
                    </p>
                    <p>
                        <strong><?php _e('Port:', 'sewn-ws'); ?></strong>
                        <span class="port"><?php echo esc_html(\SEWN_WS_DEFAULT_PORT); ?></span>
                    </p>
                </div>
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

    <!-- Connection Test Card -->
    <div class="sewn-ws-card connection-test">
        <h2><?php _e('WebSocket Test Connection', 'sewn-ws'); ?></h2>
        <div class="test-connection-status">
            <span class="connection-dot"></span>
            <span class="connection-text"><?php _e('Not Connected', 'sewn-ws'); ?></span>
        </div>
        <div class="test-controls">
            <button class="button button-primary test-connection"><?php _e('Test Connection', 'sewn-ws'); ?></button>
            <button class="button button-secondary disconnect-test" disabled><?php _e('Disconnect', 'sewn-ws'); ?></button>
        </div>
        <div class="connection-details" style="display: none;">
            <h3><?php _e('Connection Details', 'sewn-ws'); ?></h3>
            <p><strong><?php _e('URL:', 'sewn-ws'); ?></strong> <span class="connection-url"></span></p>
            <p><strong><?php _e('Status:', 'sewn-ws'); ?></strong> <span class="connection-status"></span></p>
            <p><strong><?php _e('Latency:', 'sewn-ws'); ?></strong> <span class="connection-latency">-</span></p>
        </div>
        <div class="test-console">
            <h3><?php _e('Test Console', 'sewn-ws'); ?></h3>
            <div class="console-messages"></div>
            <div class="message-input">
                <input type="text" placeholder="<?php esc_attr_e('Type a message...', 'sewn-ws'); ?>" disabled>
                <button class="button button-secondary send-message" disabled><?php _e('Send', 'sewn-ws'); ?></button>
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

/* Connection Test Card Styles */
.connection-test {
    margin-top: 20px;
}

.test-connection-status {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.connection-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 8px;
    background-color: #ccc;
}

.connection-dot.connected {
    background-color: #46b450;
}

.connection-dot.disconnected {
    background-color: #dc3232;
}

.test-controls {
    margin-top: 15px;
}

.test-controls button {
    margin-right: 10px;
}

.test-console {
    margin-top: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.console-messages {
    height: 200px;
    overflow-y: auto;
    padding: 10px;
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
    font-family: monospace;
}

.message-input {
    display: flex;
    padding: 10px;
    background: #fff;
}

.message-input input {
    flex: 1;
    margin-right: 10px;
}

.connection-details {
    margin: 15px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}

.console-messages .message {
    margin-bottom: 5px;
    padding: 5px;
    border-radius: 3px;
}

.console-messages .message.received {
    background: #e8f5e9;
}

.console-messages .message.sent {
    background: #e3f2fd;
    text-align: right;
}

.console-messages .message.system {
    background: #fff3e0;
    font-style: italic;
}

.console-messages .timestamp {
    color: #666;
    font-size: 0.8em;
    margin-right: 5px;
}

.sewn-ws-status .status-text {
    font-weight: 600;
    font-size: 16px;
    margin-left: 5px;
}

.server-info {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid rgba(0,0,0,0.1);
}

.server-info p {
    margin: 5px 0;
    font-size: 13px;
    display: flex;
    align-items: center;
}

.server-info strong {
    min-width: 100px;
    display: inline-block;
    color: #50575e;
}

.server-info .pid,
.server-info .uptime,
.server-info .port {
    font-family: monospace;
    background: rgba(0,0,0,0.05);
    padding: 2px 6px;
    border-radius: 3px;
}

.sewn-ws-status.error {
    background-color: #ffebee;
    border-color: #ef5350;
}

.sewn-ws-status.ssl-error {
    background-color: #fff3e0;
    border-color: #ff9800;
}

.sewn-ws-status .error-message {
    margin-top: 10px;
    padding: 10px;
    background-color: rgba(0, 0, 0, 0.05);
    border-radius: 4px;
}

.sewn-ws-status.connected .status-dot {
    background-color: #4caf50;
}

.sewn-ws-status.disconnected .status-dot {
    background-color: #f44336;
}

.sewn-ws-status .status-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    const serverConfig = <?php echo json_encode($server_config); ?>;
    const isLocal = serverConfig.environment.is_local;
    const protocol = serverConfig.protocol;
    const host = serverConfig.host;
    const port = serverConfig.port;
    const nonce = serverConfig.nonce;

    // Debug logging in local environment
    if (serverConfig.environment.debug_enabled) {
        console.log('Server Configuration:', serverConfig);
    }

    // Initialize Socket.IO monitoring
    function initializeSocketMonitoring() {
        const socketUrl = `${serverConfig.protocol}://${serverConfig.host}:${serverConfig.port}`;
        
        if (serverConfig.environment.debug_enabled) {
            console.log('Socket Configuration:', {
                url: socketUrl,
                isLocal: serverConfig.environment.is_local,
                ssl: serverConfig.ssl,
                protocol: serverConfig.protocol
            });
        }

        const socket = io(socketUrl, {
            transports: serverConfig.transports,
            secure: serverConfig.socket.secure,
            rejectUnauthorized: serverConfig.socket.rejectUnauthorized,
            forceNew: true,
            reconnection: serverConfig.socket.reconnection,
            reconnectionAttempts: serverConfig.socket.reconnectionAttempts,
            reconnectionDelay: serverConfig.socket.reconnectionDelay,
            timeout: serverConfig.socket.timeout,
            path: serverConfig.socket.path,
            auth: {
                nonce: serverConfig.nonce
            },
            // Debug settings for local environment
            debug: serverConfig.environment.debug_enabled
        });

        // Socket connection handlers
        socket.on('connect', function() {
            updateServerStatus('running');
            if (serverConfig.environment.debug_enabled) {
                console.log('Socket connected successfully');
                console.log('Transport:', socket.io.engine.transport.name);
            }
        });

        socket.on('connect_error', function(error) {
            if (serverConfig.environment.debug_enabled) {
                console.error('Socket connection error:', error);
                console.log('Current transport:', socket.io.engine.transport?.name);
                console.log('Available transports:', socket.io.engine.opts.transports);
            }
            updateServerStatus('error');
        });

        socket.on('disconnect', function(reason) {
            if (serverConfig.environment.debug_enabled) {
                console.log('Socket disconnected:', reason);
            }
            updateServerStatus('stopped');
        });

        // Transport upgrade handling
        socket.io.engine.on("upgrade", () => {
            if (serverConfig.environment.debug_enabled) {
                console.log('Transport upgraded to:', socket.io.engine.transport.name);
            }
        });

        return socket;
    }

    // Don't initialize socket monitoring automatically
    // It will be initialized when the start button is clicked
    let socket = null;

    // Server control button handlers with proper state management
    $('.sewn-ws-controls button').on('click', function(e) {
        e.preventDefault();
        const action = $(this).data('action');
        if (window.wsAdmin) {
            window.wsAdmin.handleServerAction(action);
        }
    });
});
</script> 