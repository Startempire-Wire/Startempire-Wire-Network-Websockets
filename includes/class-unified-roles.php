<?php
namespace SEWN\WebSockets;

class Unified_Roles {
    const TIERS = [
        'free' => [
            'discord_id' => 'unverified',
            'memberpress' => 'free'
        ],
        'freewire' => [
            'discord_id' => '851487148183781427',
            'memberpress' => 'freewire'
        ],
        // ... other tiers ...
    ];
    
    public static function get_tier($system, $id) {
        foreach(self::TIERS as $tier => $mappings) {
            if($mappings[$system] === $id) {
                return $tier;
            }
        }
        return 'free';
    }

    public static function get_all_tiers() {
        return ['free', 'freewire', 'wire', 'extrawire'];
    }
    
    public static function get_system_id($tier, $system) {
        // Temporary implementation
        return $tier . '_' . $system;
    }

    public static function sync_tiers() {
        // Temporary empty implementation
        error_log('Tier sync called - no action taken');
    }
} 