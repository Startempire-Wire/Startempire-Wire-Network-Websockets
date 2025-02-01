<?php

namespace SEWN\WebSockets;

class Core {
    public function init() {
        error_log('[SEWN] Core::init() called');
        
        if (!defined('SEWN_WS_ADMIN_LOADED')) {
            return; // Prevent initialization if admin not loaded
        }
        // Prevent double initialization
        if (defined('SEWN_WS_INITIALIZED')) return;
        define('SEWN_WS_INITIALIZED', true);

        // Check requirements WITHOUT triggering errors
        if (!Node_Check::check_version()) {
            add_action('admin_notices', [$this, 'show_node_warning']);
            return; // STOP here - don't load other features
        }
        
        // Only load these if requirements met
        require_once __DIR__ . '/class-auth.php';
        require_once __DIR__ . '/class-rate-limiter.php';
        
        // Load admin classes
        require_once __DIR__ . '/../admin/class-admin-ui.php';
        require_once __DIR__ . '/../admin/class-settings.php';
        
        // Add explicit Socket Manager initialization
        Socket_Manager::init();
        
        // Initialize admin UI
        if (is_admin()) {
            error_log('[SEWN] Loading admin components');
            new Admin_UI(); // Now autoloaded correctly
        }
        
        // Mandatory admin initialization
        if (is_admin()) {
            require_once __DIR__ . '/../admin/class-admin-notices.php';
            new Admin_Notices();
        }
        
        $this->init_ring_leader_integration();
        
        // ... rest of initialization code ...
    }

    public function show_node_warning() {
        echo '<div class="notice notice-error"><p>';
        echo 'WebSocket Error: Node.js 16.x or higher required. ';
        echo '<a href="' . admin_url('admin.php?page=sewn-ws') . '">Install Now</a></p></div>';
    }

    public function authenticate_ring_leader() {
        $cache_key = 'sewn_rl_auth_' . get_current_user_id();
        $cached = wp_cache_get($cache_key);
        
        if ($cached) {
            return $cached;
        }
        
        try {
            $token = get_option('sewn_ring_leader_token');
            $response = wp_remote_get('https://startempirewire.com/wp-json/startempire/v1/auth/validate', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'X-SEWN-Site' => site_url()
                ],
                'timeout' => 15
            ]);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $data = json_decode(wp_remote_retrieve_body($response));
            wp_cache_set($cache_key, $data, '', 300); // Cache for 5 minutes
            
            return $data;
        } catch (Exception $e) {
            error_log('Ring Leader auth failed: ' . $e->getMessage());
            return $this->fallback_auth();
        }
    }

    private function init_ring_leader_integration() {
        // PRESERVE AND EXPAND EXISTING ROUTER
        add_action('sewn_ringleader_message', function($message) {
            $router = [
                'parent-to-extension' => 'chrome-extension',
                'connect-to-parent' => 'wordpress-admin',
                'user-to-user' => 'direct-message',
                'parent-to-connect' => 'connect-plugin',
                'connect-to-extension' => 'chrome-extension',
                'extension-to-parent' => 'wordpress-admin',
                'extension-to-connect' => 'connect-plugin',
                'extension-to-extension' => 'direct-message'
            ];
            
            if(isset($router[$message['type']])) {
                Server_Controller::broadcast(
                    $router[$message['type']], 
                    json_encode($message['data'])
                );
            }
        });

        // ADD NEW CHANNEL SYSTEM ALONGSIDE EXISTING
        add_action('sewn_ws_ready', function() {
            $this->message_broker = new MessageBroker(
                get_option('sewn_ws_ringleader_endpoint'),
                $this->get_auth_token()
            );
            
            $this->create_content_channels([
                'member_updates',
                'content_distribution',
                'network_health'
            ]);
        });
    }
}

class WS_Auth_Handler {
    public function authenticate_connection($connection) {
        $token = $this->get_auth_token($connection);
        
        // Verify against Ring Leader's system (handles WP admins)
        if (!function_exists('startempire_wire_network_ring_leader_verify_token')) {
            require_once WP_PLUGIN_DIR . '/startempire-wire-network-ring-leader/includes/auth-functions.php';
        }
        
        $user_id = startempire_wire_network_ring_leader_verify_token($token);
        
        // First check WordPress admin status from verification
        if ($user_id && user_can($user_id, 'manage_network')) {
            return $this->grant_admin_access($connection, $user_id);
        }
        
        // Then check membership tier for non-admins
        if ($user_id && $this->check_membership_access($user_id)) {
            return $this->grant_member_access($connection, $user_id);
        }
        
        return $this->reject_connection($connection);
    }
    
    private function check_membership_access($user_id) {
        $tier = get_user_meta($user_id, 'startempire_membership_tier', true);
        return in_array($tier, ['freewire', 'wire', 'extrawire']);
    }
    
    private function grant_admin_access($connection, $user_id) {
        $connection->user_id = $user_id;
        $connection->access_level = 'admin';
        do_action('sewn_ws_admin_connected', $connection);
        return true;
    }
} 