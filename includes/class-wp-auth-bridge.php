<?php

class SEWN_WS_WP_Auth {
    public function generate_socket_token($user_id) {
        return JWT::encode([
            'iat' => time(),
            'exp' => time() + 3600,
            'wp_user' => $user_id,
            'tier' => get_user_meta($user_id, 'sewn_tier', true)
        ], SECURE_AUTH_KEY);
    }
} 