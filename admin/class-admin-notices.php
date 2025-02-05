<?php

namespace SEWN\WebSockets;
use SEWN\WebSockets\Node_Check;

/**
 * LOCATION: admin/class-admin-notices.php
 * DEPENDENCIES: Node_Check class, WordPress admin hooks
 * VARIABLES: SEWN_WS_TEXT_DOMAIN (translation domain)
 * CLASSES: None (anonymous function)
 * 
 * Handles system requirement notifications in WordPress admin. Specifically monitors Node.js version compatibility
 * and displays persistent warnings until minimum requirements are met. Integrates with the plugin's environment
 * validation system to maintain network stability across Startempire Wire Network components.
 */

add_action('admin_notices', function() {
    if (!Node_Check::check_version()) {
        echo '<div class="notice notice-error">';
        echo '<p>' . __('WebSocket Server: Node.js 16.x or higher required. ', SEWN_WS_TEXT_DOMAIN);
        echo '<a href="' . admin_url('admin.php?page=' . SEWN_WS_ADMIN_MENU_SLUG) . '">' . __('Install Now') . '</a></p>';
        echo '</div>';
    }
}); 