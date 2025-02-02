<?php
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