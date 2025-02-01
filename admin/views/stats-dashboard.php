<?php
// Add to existing dashboard.php
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