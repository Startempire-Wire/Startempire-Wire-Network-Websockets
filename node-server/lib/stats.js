/**
 * Location: node-server/lib/stats.js
 * Dependencies: ioredis, connection manager
 * Classes: Stats
 * Purpose: Collects and stores connection statistics and system metrics. Provides
 * both real-time and historical data for monitoring and analytics features.
 */

const Redis = require('ioredis');
const fs = require('fs');
const path = require('path');

class Stats {
    constructor(config = {}) {
        this.config = config;
        this.startTime = Date.now();
        this.statsFile = process.env.WP_STATS_FILE || path.join(__dirname, '../../tmp/stats.json');

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
                startTime: this.startTime
            };
        }

        // Start periodic stats writing
        this.startStatsWriter();
    }

    startStatsWriter() {
        // Write stats every second
        setInterval(() => this.writeStats(), 1000);
    }

    async writeStats() {
        try {
            const stats = await this.getStats();
            const statsData = {
                timestamp: Date.now(),
                memory: process.memoryUsage().heapUsed,
                connections: stats.currentConnections,
                messageRate: stats.messageRate,
                errorRate: stats.errorRate,
                uptime: stats.uptime
            };

            // Ensure directory exists
            const dir = path.dirname(this.statsFile);
            if (!fs.existsSync(dir)) {
                fs.mkdirSync(dir, { recursive: true });
            }

            // Write stats to file
            fs.writeFileSync(this.statsFile, JSON.stringify(statsData, null, 2));
        } catch (error) {
            console.error('Error writing stats:', error);
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

            const now = Date.now();
            const messageRate = totalMessages ? parseInt(totalMessages, 10) / ((now - this.startTime) / 1000) : 0;
            const errorRate = totalErrors ? parseInt(totalErrors, 10) / ((now - this.startTime) / 1000) : 0;

            return {
                currentConnections: Object.values(connections || {})
                    .reduce((sum, val) => sum + parseInt(val, 10), 0),
                totalConnections: parseInt(totalConnections, 10) || 0,
                totalMessages: parseInt(totalMessages, 10) || 0,
                totalErrors: parseInt(totalErrors, 10) || 0,
                messageRate,
                errorRate,
                uptime: now - this.startTime
            };
        } else {
            const now = Date.now();
            const messageRate = this.stats.totalMessages / ((now - this.stats.startTime) / 1000);
            const errorRate = this.stats.totalErrors / ((now - this.stats.startTime) / 1000);

            return {
                currentConnections: Array.from(this.stats.connections.values())
                    .reduce((sum, val) => sum + val, 0),
                totalConnections: this.stats.totalConnections,
                totalMessages: this.stats.totalMessages,
                totalErrors: this.stats.totalErrors,
                messageRate,
                errorRate,
                uptime: now - this.stats.startTime
            };
        }
    }

    async save() {
        await this.writeStats();
    }

    async close() {
        if (this.store) {
            await this.store.quit();
        }
    }
}

module.exports = Stats; 