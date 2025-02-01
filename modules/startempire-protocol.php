<?php
namespace SEWN\WebSockets\Modules;

use SEWN\WebSockets\Protocol_Base;

class Startempire_Protocol extends Protocol_Base {
    /**
     * Register the protocol
     */
    public function register() {
        add_action('sewn_ws_register_protocols', [$this, 'register_protocol']);
    }

    /**
     * Register protocol with the handler
     */
    public function register_protocol($handler) {
        $handler->register_protocol('startempire', $this);
    }

    /**
     * Handle incoming messages
     */
    public function handle_message($message, $context) {
        // Get user level from Ring Leader
        $user_data = $this->get_user_data($context['client_id']);
        
        return $this->distribute_message(
            $context['bridge'],
            $message,
            [
                'source' => 'startempire',
                'user_data' => $user_data,
                'capabilities' => $user_data['capabilities'] ?? ['connect']
            ]
        );
    }

    /**
     * Get user data from Ring Leader
     */
    private function get_user_data($client_id) {
        // Delegate to Ring Leader for user information
        return apply_filters('sewn_ringleader_user_data', [], $client_id);
    }
}

// Initialize module
add_action('sewn_ws_init', function() {
    $protocol = new Startempire_Protocol();
    $protocol->register();
}); 