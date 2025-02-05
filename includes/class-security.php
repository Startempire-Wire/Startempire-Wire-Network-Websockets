<?php
/**
 * Location: includes/class-security.php
 * Dependencies: WordPress Nonce System, Capability API
 * Variables/Classes: SEWN_WS_Security, verify_nonce(), check_capability()
 * 
 * Provides security foundations for admin operations and API endpoints. Implements nonce verification and privilege checks aligned with network membership tiers.
 */
namespace SEWN\WebSockets;

class SEWN_WS_Security {
    public function verify_nonce($action) {
        if(!wp_verify_nonce($_REQUEST['_wpnonce'], $action)) {
            wp_die(__('Security check failed', 'sewn-ws'));
        }
    }

    public function check_capability($cap = 'manage_options') {
        if(!current_user_can($cap)) {
            wp_die(__('Unauthorized access', 'sewn-ws'));
        }
    }
} 