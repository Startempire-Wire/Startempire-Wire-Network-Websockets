<?php

/**
 * Location: includes/class-webhooks.php
 * Dependencies: WordPress HTTP API, Unified_Roles
 * Classes: (Anonymous)
 * 
 * Handles role synchronization events between WebSocket service and parent network. Transmits tier updates to central Startempire Wire system for cross-service consistency.
 */

namespace SEWN\WebSockets;

add_action('sewn_ws_role_updated', function($tier) {
    $payload = [
        'tier' => $tier,
        'systems' => [
            'discord' => SEWN_WS_Unified_Roles::get_system_id($tier, 'discord'),
            'memberpress' => SEWN_WS_Unified_Roles::get_system_id($tier, 'memberpress')
        ]
    ];
    
    wp_remote_post('https://startempirewire.com/webhooks/role-update', [
        'body' => json_encode($payload)
    ]);
}); 