<?php

namespace SEWN\WebSockets;
use SEWN\WebSockets\Node_Check;

add_action('admin_notices', function() {
    if (!Node_Check::check_version()) {
        echo '<div class="notice notice-error">';
        echo '<p>' . __('WebSocket Server: Node.js 16.x or higher required. ', SEWN_WS_TEXT_DOMAIN);
        echo '<a href="' . admin_url('admin.php?page=' . SEWN_WS_ADMIN_MENU_SLUG) . '">' . __('Install Now') . '</a></p>';
        echo '</div>';
    }
}); 