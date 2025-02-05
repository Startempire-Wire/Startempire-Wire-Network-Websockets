<?php
/**
 * Location: includes/class-role-manager.php
 * Dependencies: WordPress User Roles, Debug Logging
 * Variables/Classes: SEWN_WS_Role_Manager, $capabilities matrix
 * 
 * Enforces tier-based capabilities according to network membership levels. Maps FreeWire/Wire/ExtraWire tiers to specific permissions with debug logging for capability checks.
 */
namespace SEWN\WebSockets;

class SEWN_WS_Role_Manager {
    private $capabilities = [
        'free' => ['view'],
        'freewire' => ['view', 'chat'],
        'wire' => ['view', 'chat', 'stream'],
        'extrawire' => ['view', 'chat', 'stream', 'moderate']
    ];

    public function user_can($capability) {
        $tier = $this->get_current_tier();
        $allowed = in_array($capability, $this->capabilities[$tier]);
        
        // Log capability checks
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[SEWN_WS] Capability check: {$capability} for tier {$tier} - " 
                     . ($allowed ? 'Granted' : 'Denied'));
        }
        
        return $allowed;
    }

    public function get_current_tier() {
        // Implementation of get_current_tier method
    }
} 