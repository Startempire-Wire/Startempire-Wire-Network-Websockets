<?php
/**
 * Location: includes/
 * Dependencies: WordPress Options API
 * Variables: None
 * Classes: SEWN_WS_API_Keys
 * 
 * Generates and validates API keys for tiered access to WebSocket services. Manages key storage, usage tracking, and tier-based authentication through WordPress options system.
 */
namespace SEWN\WebSockets;

class SEWN_WS_API_Keys {
    public function generate_key($tier) {
        $key = bin2hex(random_bytes(32));
        update_option('sewn_ws_key_'.$key, [
            'tier' => $tier,
            'created' => time(),
            'last_used' => 0,
            'uses' => 0
        ]);
        return $key;
    }

    public function validate_key($key) {
        $data = get_option('sewn_ws_key_'.$key, false);
        if ($data) {
            update_option('sewn_ws_key_'.$key, [
                ...$data,
                'last_used' => time(),
                'uses' => $data['uses'] + 1
            ]);
            return $data;
        }
        return false;
    }
} 