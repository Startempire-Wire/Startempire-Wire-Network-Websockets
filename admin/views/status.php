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
        <div class="sewn-ws-status-indicator <?php echo esc_attr($server_status); ?>">
            <?php echo esc_html(ucfirst($server_status)); ?>
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