class SEWN_WS_Role_Mapper {
    const MAPPINGS = [
        'discord' => [
            '851487148183781427' => 'freewire', // Discord role ID → WP tier
            '851487148183781428' => 'wire'
        ],
        'memberpress' => [
            'free_membership' => 'free',
            'premium_membership' => 'extrawire'
        ]
    ];

    // Add validation to mappings
    const TIER_VALIDATION = [
        'freewire' => ['min_entropy' => 20],
        'wire' => ['require_2fa' => true]
    ];

    public static function to_wp_tier($source_system, $external_id) {
        $tier = self::MAPPINGS[$source_system][$external_id] ?? 'free';
        
        // Validate tier requirements
        if (isset(self::TIER_VALIDATION[$tier])) {
            $validator = new Tier_Validator();
            if (!$validator->validate($tier)) {
                return 'free'; // Fail-safe
            }
        }
        
        return $tier;
    }

    public static function to_external_id($source_system, $wp_tier) {
        return array_search($wp_tier, self::MAPPINGS[$source_system]) ?? null;
    }

    // Maps Discord roles → WP tiers
    public static function discord_to_wp($external_role) {
        return self::to_wp_tier('discord', $external_role);
    }

    // Converts MemberPress → custom roles
    public static function memberpress_to_tier($external_role) {
        return self::to_wp_tier('memberpress', $external_role);
    }
}
