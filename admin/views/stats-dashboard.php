<?php
/**
 * LOCATION: admin/views/stats-dashboard.php
 * DEPENDENCIES: Server_Controller stats data
 * VARIABLES: $stats_data (array)
 * CLASSES: None (template file)
 * 
 * Displays real-time network performance metrics and connection analytics. Visualizes WebRing content distribution
 * patterns and membership-tier usage statistics. Correlates server load with authentication system activity.
 */
?>
<div class="sewn-ws-stats-container">
    <div class="stats-card connections">
        <h3><?php _e('Active Connections', 'sewn-ws'); ?></h3>
        <div id="live-connections-count">-</div>
        <div id="connections-graph"></div>
    </div>
    
    <div class="stats-card rooms">
        <h3><?php _e('Active Rooms', 'sewn-ws'); ?></h3>
        <div id="active-rooms-list"></div>
    </div>
    
    <div class="stats-card events">
        <h3><?php _e('Event Log', 'sewn-ws'); ?></h3>
        <div id="event-log"></div>
    </div>
</div> 