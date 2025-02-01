<?php

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