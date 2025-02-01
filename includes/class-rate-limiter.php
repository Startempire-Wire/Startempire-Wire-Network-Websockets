<?php
namespace SEWN\WebSockets;

class Rate_Limiter {
    const TIER_LIMITS = [
        'free' => 10,    // 10 messages/minute
        'freewire' => 30,
        'wire' => 100,
        'extrawire' => 500
    ];
    
    public function check_limit($user_id) {
        $tier = get_user_meta($user_id, 'sewn_tier', true);
        $count = $this->get_message_count($user_id);
        
        return $count < self::TIER_LIMITS[$tier];
    }
} 