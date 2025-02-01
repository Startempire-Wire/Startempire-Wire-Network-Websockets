<?php

add_action('admin_notices', function() {
    if (!SEWN\WebSockets\Node_Check::check_version()) {
        echo '<div class="notice notice-error">';
        echo '<p>WebSocket Server: Node.js 16.x or higher required. ';
        echo '<a href="' . admin_url('admin.php?page=sewn-ws') . '">Install Now</a></p>';
        echo '</div>';
    }
}); 