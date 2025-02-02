<?php
namespace SEWN\WebSockets;

class Auth {
    public function check_permissions($user_id) {
        // Admin override first
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Temporary simplified check
        $tier = $this->user_level($user_id);
        return in_array($tier, ['freewire', 'wire', 'extrawire']);
    }

    public function authenticate($token) {
        do_action('sewn_ws_pre_auth', $token); // Future hook
        
        // Temporary admin bypass
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Connect to central auth service
        $response = wp_remote_get(SEWN_CENTRAL_AUTH_URL.'/validate?token='.$token);
        return json_decode($response['body'])->valid;
    }

    public function user_level($user_id) {
        return get_user_meta($user_id, 'sewn_network_level', true) ?: 'free';
    }

    public function encrypt_payload($data) {
        return sodium_crypto_box_seal(
            json_encode($data),
            SODIUM_CRYPTO_BOX_PUBLICKEY
        );
    }
}
