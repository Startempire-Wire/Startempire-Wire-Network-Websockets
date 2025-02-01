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