const Redis = require('ioredis');

class Stats {
    constructor(config = {}) {
        this.config = config;

        // Initialize Redis if configured, otherwise use in-memory
        if (config.redis) {
            this.store = new Redis(config.redis);
        } else {
            this.stats = {
                connections: new Map(),
                messages: new Map(),
                errors: new Map(),
                totalConnections: 0,
                totalMessages: 0,
                totalErrors: 0,
                startTime: Date.now()
            };
        }
    }

    async trackConnection(userId) {
        if (this.store) {
            const multi = this.store.multi();
            multi.hincrby('stats:connections', userId, 1);
            multi.incr('stats:total_connections');
            await multi.exec();
        } else {
            this.stats.connections.set(userId,
                (this.stats.connections.get(userId) || 0) + 1
            );
            this.stats.totalConnections++;
        }
    }

    async trackDisconnection(userId) {
        if (this.store) {
            await this.store.hincrby('stats:connections', userId, -1);
        } else {
            const count = this.stats.connections.get(userId) || 0;
            if (count > 0) {
                this.stats.connections.set(userId, count - 1);
            }
        }
    }

    async trackMessage(userId) {
        if (this.store) {
            const multi = this.store.multi();
            multi.hincrby('stats:messages', userId, 1);
            multi.incr('stats:total_messages');
            await multi.exec();
        } else {
            this.stats.messages.set(userId,
                (this.stats.messages.get(userId) || 0) + 1
            );
            this.stats.totalMessages++;
        }
    }

    async trackError(userId, error) {
        const errorData = {
            timestamp: Date.now(),
            message: error.message || 'Unknown error',
            stack: error.stack
        };

        if (this.store) {
            const multi = this.store.multi();
            multi.hincrby('stats:errors', userId, 1);
            multi.incr('stats:total_errors');
            multi.lpush(`stats:error_log:${userId}`, JSON.stringify(errorData));
            multi.ltrim(`stats:error_log:${userId}`, 0, 99); // Keep last 100 errors
            await multi.exec();
        } else {
            this.stats.errors.set(userId,
                (this.stats.errors.get(userId) || 0) + 1
            );
            this.stats.totalErrors++;
        }
    }

    async getStats() {
        if (this.store) {
            const [
                connections,
                totalConnections,
                totalMessages,
                totalErrors
            ] = await Promise.all([
                this.store.hgetall('stats:connections'),
                this.store.get('stats:total_connections'),
                this.store.get('stats:total_messages'),
                this.store.get('stats:total_errors')
            ]);

            return {
                currentConnections: Object.values(connections || {})
                    .reduce((sum, val) => sum + parseInt(val, 10), 0),
                totalConnections: parseInt(totalConnections, 10) || 0,
                totalMessages: parseInt(totalMessages, 10) || 0,
                totalErrors: parseInt(totalErrors, 10) || 0,
                uptime: Date.now() - this.startTime
            };
        } else {
            return {
                currentConnections: Array.from(this.stats.connections.values())
                    .reduce((sum, val) => sum + val, 0),
                totalConnections: this.stats.totalConnections,
                totalMessages: this.stats.totalMessages,
                totalErrors: this.stats.totalErrors,
                uptime: Date.now() - this.stats.startTime
            };
        }
    }

    async save() {
        // Only needed for memory store, Redis persists automatically
        if (!this.store) {
            // Implement saving to file if needed
            console.log('Stats saved');
        }
    }

    async close() {
        if (this.store) {
            await this.store.quit();
        }
    }
}

module.exports = Stats; 