<?php

namespace SEWN\WebSockets;

class SEWN_WS_Hybrid_Role_System {
    
    // Parent-defined canonical tiers
    const CANONICAL_TIERS = [
        'free', 'freewire', 'wire', 'extrawire'
    ];
    
    // Local cache of parent-defined mappings
    private $parent_mappings = [];
    
    public function sync_with_parent() {
        // Get authoritative mappings from parent site
        $response = wp_remote_get('https://startempirewire.com/wp-json/startempire/v1/role-mappings');
        
        if(!is_wp_error($response)) {
            $this->parent_mappings = json_decode($response['body'], true);
            update_option('sewn_ws_role_cache', $this->parent_mappings);
        }
    }
    
    public function map_external_role($system, $external_id) {
        // Check local cache first
        if(isset($this->parent_mappings[$system][$external_id])) {
            return $this->parent_mappings[$system][$external_id];
        }
        
        // Fallback to hardcoded defaults
        return $this->get_default_mapping($system, $external_id);
    }
    
    private function get_default_mapping($system, $external_id) {
        // Default mappings approved by parent site
        $defaults = [
            'discord' => [
                '851487148183781427' => 'freewire',
                '851487148183781428' => 'wire'
            ],
            'memberpress' => [
                'free_membership' => 'free',
                'premium_membership' => 'extrawire'
            ]
        ];
        
        return $defaults[$system][$external_id] ?? 'free';
    }
} 