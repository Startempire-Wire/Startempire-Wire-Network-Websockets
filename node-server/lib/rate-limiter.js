const Redis = require('ioredis');

class RateLimiter {
    constructor(config) {
        this.config = config;
        this.tierLimits = {
            'free': 10,      // 10 messages/minute
            'freewire': 30,  // 30 messages/minute
            'wire': 100,     // 100 messages/minute
            'extrawire': 500 // 500 messages/minute
        };

        // Initialize Redis if configured, otherwise use in-memory
        if (config.redis) {
            this.store = new Redis(config.redis);
        } else {
            this.store = new Map();
            this.cleanup();
        }
    }

    async checkLimit(userId, userTier) {
        const key = `rate_limit:${userId}`;
        const now = Date.now();
        const limit = this.tierLimits[userTier] || this.tierLimits.free;

        try {
            if (this.store instanceof Redis) {
                return await this.checkRedisLimit(key, now, limit);
            } else {
                return this.checkMemoryLimit(key, now, limit);
            }
        } catch (error) {
            console.error('Rate limit check failed:', error);
            // Fail open to prevent blocking on rate limit errors
            return true;
        }
    }

    // ... rest of the implementation remains the same ...
}

module.exports = { RateLimiter }; 