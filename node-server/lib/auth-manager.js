class AuthManager {
    constructor(io, connectionManager) {
        this.io = io;
        this.connectionManager = connectionManager;
    }

    async validateToken(token) {
        try {
            // First check WordPress admin status
            const wpDecoded = jwt.verify(token, process.env.WP_JWT_SECRET);
            if (wpDecoded.isAdmin) {
                return this.adminPayload(wpDecoded);
            }

            // Then check API keys
            const apiKey = await this.validateApiKey(token);
            if (apiKey.valid) {
                return {
                    source: 'api-key',
                    tier: apiKey.tier,
                    capabilities: this.getTierCapabilities(apiKey.tier)
                };
            }

            // Finally check user tier
            return {
                source: 'wordpress',
                capabilities: this.getTierCapabilities(wpDecoded.tier),
                tier: wpDecoded.tier
            };

        } catch (err) {
            return this.handleAuthError(err);
        }
    }

    getTierCapabilities(tier) {
        const tiers = {
            free: ['connect', 'subscribe'],
            freewire: ['connect', 'subscribe', 'publish'],
            wire: ['connect', 'subscribe', 'publish', 'moderate'],
            admin: ['all']
        };
        return tiers[tier] || tiers.free;
    }

    async getRingLeaderCapabilities(token) {
        // Query Ring Leader for capabilities
        const response = await fetch(`${process.env.RING_LEADER_URL}/api/v1/capabilities`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await response.json();
        return {
            isAdmin: false,
            source: 'ringleader',
            capabilities: data.capabilities,
            metadata: data.metadata // Additional context from Ring Leader
        };
    }

    // Connection limit enforcement moved to connection manager
    // Role-specific features handled by individual socket handlers
    // This maintains agnostic architecture
}

module.exports = AuthManager; 