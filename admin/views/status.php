<?php
/**
 * LOCATION: admin/views/status.php
 * DEPENDENCIES: Server_Controller status data
 * VARIABLES: $server_status, $connection_stats
 * CLASSES: None (template file)
 * 
 * Displays real-time server status and connection statistics. Provides an overview of active connections,
 * error counts, memory usage, and uptime. Integrates with network authentication providers for secure
 * credential storage.
 */
?>

<div class="wrap">
    <h1><?php _e('WebSocket Server Status', 'sewn-ws'); ?></h1>
    
    <div class="sewn-ws-status-panel">
        <h2><?php _e('Server Status', 'sewn-ws'); ?></h2>
        <div class="sewn-ws-status-indicator">
            <div class="status-indicator">
                <span class="status-dot <?php echo esc_attr($server_status); ?>"></span>
                <span class="status-text">
                    <?php 
                    switch($server_status) {
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
            </div>
        </div>
        
        <h2><?php _e('Connection Statistics', 'sewn-ws'); ?></h2>
        <table class="widefat">
            <tr>
                <th><?php _e('Active Connections', 'sewn-ws'); ?></th>
                <td><?php echo esc_html($connection_stats['active_connections']); ?></td>
            </tr>
            <tr>
                <th><?php _e('Error Count', 'sewn-ws'); ?></th>
                <td><?php echo esc_html($connection_stats['error_count']); ?></td>
            </tr>
            <tr>
                <th><?php _e('Memory Usage', 'sewn-ws'); ?></th>
                <td><?php echo esc_html(size_format($connection_stats['memory_usage'])); ?></td>
            </tr>
            <tr>
                <th><?php _e('Uptime', 'sewn-ws'); ?></th>
                <td><?php echo esc_html(human_time_diff(time() - $connection_stats['uptime'])); ?></td>
            </tr>
        </table>
    </div>
</div>

<style>
.sewn-ws-status-panel {
    margin: 20px 0;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.sewn-ws-status-indicator {
    margin: 15px 0;
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
.status-dot.uninitialized { background: #9e9e9e; }

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.widefat {
    margin-top: 15px;
    width: 100%;
    border-spacing: 0;
}

.widefat th {
    text-align: left;
    padding: 8px;
    background: #f8f9fa;
    border-bottom: 1px solid #e2e4e7;
}

.widefat td {
    padding: 8px;
    border-bottom: 1px solid #f0f0f1;
}
</style> 