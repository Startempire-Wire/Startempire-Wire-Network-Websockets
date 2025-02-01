class AuthManager {
    constructor(io, connectionManager) {
        this.io = io;
        this.connectionManager = connectionManager;
    }

    async validateToken(token) {
        try {
            // Check if admin token (for any WordPress installation)
            if (await this.isWordPressAdmin(token)) {
                return {
                    isAdmin: true,
                    source: 'wordpress',
                    capabilities: ['all']
                };
            }

            // Check if Ring Leader token
            if (await this.isRingLeaderToken(token)) {
                // Trust Ring Leader's role/capability assignments
                return await this.getRingLeaderCapabilities(token);
            }

            // Check if valid API key
            if (await this.isValidApiKey(token)) {
                return {
                    isAdmin: false,
                    source: 'api',
                    capabilities: await this.getApiKeyCapabilities(token)
                };
            }

            // Default public access
            return {
                isAdmin: false,
                source: 'public',
                capabilities: ['connect', 'subscribe']
            };

        } catch (error) {
            console.error('Token validation error:', error);
            return {
                isAdmin: false,
                source: 'public',
                capabilities: ['connect', 'subscribe']
            };
        }
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