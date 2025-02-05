/**
 * Location: node-server/server.js
 * Dependencies: Socket.io, Redis, WordPress DB
 * Variables/Classes: io, server, metrics
 * Purpose: Core WebSocket server handling real-time communications and tier-based connection management. Integrates with WordPress authentication and enforces network membership rules for data distribution.
 */
const express = require('express');
const app = express();
const server = require('http').createServer(app);
const io = require('socket.io')(server);
const fs = require('fs');
const path = require('path');
const jwt = require('jsonwebtoken');
const dotenv = require('dotenv');
const winston = require('winston');
const Redis = require('redis');
const wpdb = require('wpdb');

dotenv.config(); // Load environment variables from .env

// Configure Winston logger
const logger = winston.createLogger({
    level: 'info',
    format: winston.format.combine(
        winston.format.timestamp(),
        winston.format.json()
    ),
    transports: [
        new winston.transports.Console(), // Log to console
        new winston.transports.File({ filename: 'websocket-server.log' }) // Log to file
    ],
});

// Load configuration
const config = {
    port: process.env.PORT || 8080,
    logPath: path.join(__dirname, 'server.log'),
    statusPath: path.join(__dirname, 'status.json'),
    statsPath: path.join(__dirname, 'stats.json')
};

// Initialize Redis client (optional, for future scalability)
let redisClient;
if (process.env.REDIS_URL) {
    redisClient = Redis.createClient({ url: process.env.REDIS_URL });
    redisClient.on('error', err => logger.error('Redis Client Error', err));
    redisClient.connect().catch(err => logger.error('Error connecting to Redis', err));
}

// Stats tracking
const stats = {
    connections: 0,
    channels: {
        message: { messages: 0, errors: 0, subscribers: 0 },
        presence: { messages: 0, errors: 0, subscribers: 0 },
        status: { messages: 0, errors: 0, subscribers: 0 },
    },
    tiers: {
        free: { connections: 0, bandwidth: 0 },
        wire: { connections: 0, bandwidth: 0 },
        extraWire: { connections: 0, bandwidth: 0 }
    },
};

// Add these metrics collection endpoints
const metrics = {
    connections: 0,
    messagesIn: 0,
    messagesOut: 0
};

// JWT Authentication Middleware (for channels other than /admin)
const authenticateJWT = async (socket, next) => {
    try {
        if (!socket.handshake.auth?.token) {
            // Apply Free (Non-Verified - Public Access) tier
            socket.user = { tier: 'free' };
            return next();
        }

        // Follow the authentication flow from bigpicture.mdx
        if (await isWordPressAdmin(socket.handshake.auth.token)) {
            socket.user = { tier: 'admin' };
            return next();
        }

        if (await isValidApiKey(socket.handshake.auth.token)) {
            socket.user = { tier: 'api' };
            return next();
        }

        const ringLeaderToken = await verifyRingLeaderToken(socket.handshake.auth.token);
        if (ringLeaderToken) {
            socket.user = { tier: ringLeaderToken.tier };
            return next();
        }

        // Default to free tier
        socket.user = { tier: 'free' };
        next();
    } catch (error) {
        logger.warn('Auth failed:', error.message);
        next(new Error('Authentication failed'));
    }
};

// Admin namespace for stats
const adminNamespace = io.of('/admin');
adminNamespace.on('connection', socket => {
    logger.info('Admin dashboard connected');
    stats.connections++;
    emitStats(); // Send initial stats on connection

    socket.on('disconnect', () => {
        stats.connections--;
        logger.info('Admin dashboard disconnected');
    });
});

// Message channel namespace
const messageNamespace = io.of('/message');
messageNamespace.use(authenticateJWT); // Apply JWT auth
messageNamespace.on('connection', socket => {
    logger.info(`User connected to /message: ${socket.user ? socket.user.userId : 'unknown'}`);
    stats.channels.message.subscribers++;
    emitChannelStats('message');
    stats.tiers[socket.user.tier].connections++;
    stats.tiers[socket.user.tier].bandwidth += socket.bytesReceived;

    socket.on('message', (message) => {
        logger.info('Received message:', message, 'from user:', socket.user ? socket.user.userId : 'unknown');
        stats.channels.message.messages++;
        emitChannelStats('message');
        messageNamespace.emit('message', { ...message, senderId: socket.user.userId, timestamp: Date.now() }); // Broadcast to all in /message
    });

    socket.on('disconnect', () => {
        stats.channels.message.subscribers--;
        emitChannelStats('message');
        logger.info(`User disconnected from /message: ${socket.user ? socket.user.userId : 'unknown'}`);
    });
});

// Presence channel namespace
const presenceNamespace = io.of('/presence');
presenceNamespace.use(authenticateJWT); // Apply JWT auth
presenceNamespace.on('connection', socket => {
    logger.info(`User connected to /presence: ${socket.user ? socket.user.userId : 'unknown'}`);
    stats.channels.presence.subscribers++;
    emitChannelStats('presence');

    socket.on('join', (data) => {
        logger.info('User joined presence:', data, 'user:', socket.user ? socket.user.userId : 'unknown');
        presenceNamespace.emit('user_join', { userId: socket.user.userId, ...data });
    });

    socket.on('leave', (data) => {
        logger.info('User left presence:', data, 'user:', socket.user ? socket.user.userId : 'unknown');
        presenceNamespace.emit('user_leave', { userId: socket.user.userId, ...data });
    });

    socket.on('disconnect', () => {
        stats.channels.presence.subscribers--;
        emitChannelStats('presence');
        logger.info(`User disconnected from /presence: ${socket.user ? socket.user.userId : 'unknown'}`);
    });
});

// Status channel namespace
const statusNamespace = io.of('/status');
statusNamespace.use(authenticateJWT); // Apply JWT auth
statusNamespace.on('connection', socket => {
    logger.info(`User connected to /status: ${socket.user ? socket.user.userId : 'unknown'}`);
    stats.channels.status.subscribers++;
    emitChannelStats('status');

    socket.on('update', (data) => {
        logger.info('Status update received:', data, 'user:', socket.user ? socket.user.userId : 'unknown');
        statusNamespace.emit('status_update', { userId: socket.user.userId, ...data });
    });

    socket.on('disconnect', () => {
        stats.channels.status.subscribers--;
        emitChannelStats('status');
        logger.info(`User disconnected from /status: ${socket.user ? socket.user.userId : 'unknown'}`);
    });
});

function emitStats() {
    const stats = {
        connections: io.engine.clientsCount,
        rooms: Array.from(io.sockets.adapter.rooms.keys()),
        uptime: process.uptime(),
        memory: process.memoryUsage()
    };

    // Emit to admin namespace
    io.of('/admin').emit('stats_update', stats);
    stats.tiers = this.tiers;
}

function emitChannelStats(channelName) {
    adminNamespace.emit('channelStatsUpdate', { channel: channelName, stats: stats.channels[channelName] });
}

// Stats update interval - send stats to admin dashboard every 2 seconds
setInterval(emitStats, 2000);

// Add these metrics collection endpoints
const metrics = {
    connections: 0,
    messagesIn: 0,
    messagesOut: 0
};

setInterval(() => {
    // Store rates in transients for PHP access
    wpdb.setTransient('sewn_ws_msg_in_rate', metrics.messagesIn);
    wpdb.setTransient('sewn_ws_msg_out_rate', metrics.messagesOut);
    metrics.messagesIn = 0;
    metrics.messagesOut = 0;
}, 1000);

// Add Socket.io event handlers for server management
io.on('connection', (socket) => {
    // Existing connection logging
    logEvent('connection', { socketId: socket.id });

    // Server status events
    socket.on('get_status', (callback) => {
        const status = {
            running: true,
            connections: io.engine.clientsCount,
            uptime: process.uptime(),
            rooms: Array.from(io.sockets.adapter.rooms.entries()).map(([roomId, members]) => ({
                roomId,
                memberCount: members.size
            }))
        };
        callback(status);
    });

    // Server stats events
    socket.on('get_stats', (callback) => {
        const stats = {
            timestamp: Date.now(),
            connections: io.engine.clientsCount,
            rooms: Array.from(io.sockets.adapter.rooms.entries()).map(([roomId, members]) => ({
                roomId,
                memberCount: members.size
            }))
        };
        callback(stats);
    });

    // Room management events
    socket.on('join_room', (roomId, callback) => {
        try {
            socket.join(roomId);
            if (!io.sockets.adapter.rooms.has(roomId)) {
                io.sockets.adapter.rooms.set(roomId, new Set());
            }
            io.sockets.adapter.rooms.get(roomId).add(socket.id);
            logEvent('room_join', { socketId: socket.id, roomId });

            // Notify room members
            io.to(roomId).emit('room_update', {
                roomId,
                memberCount: io.sockets.adapter.rooms.get(roomId).size
            });

            callback({ success: true });
        } catch (error) {
            callback({ success: false, error: error.message });
        }
    });

    socket.on('leave_room', (roomId, callback) => {
        try {
            socket.leave(roomId);
            io.sockets.adapter.rooms.get(roomId)?.delete(socket.id);
            logEvent('room_leave', { socketId: socket.id, roomId });

            // Notify room members
            io.to(roomId).emit('room_update', {
                roomId,
                memberCount: io.sockets.adapter.rooms.get(roomId)?.size || 0
            });

            callback({ success: true });
        } catch (error) {
            callback({ success: false, error: error.message });
        }
    });

    // Admin events (requires admin privileges)
    socket.on('server_control', async (action, callback) => {
        if (!socket.user?.isAdmin) {
            callback({ success: false, error: 'Unauthorized' });
            return;
        }

        try {
            switch (action) {
                case 'restart':
                    logEvent('server_restart', { user: socket.user.id });
                    callback({ success: true, message: 'Server restarting' });
                    process.exit(0); // PM2 will restart
                    break;

                case 'stop':
                    logEvent('server_stop', { user: socket.user.id });
                    callback({ success: true, message: 'Server stopping' });
                    io.close(() => process.exit(0));
                    break;

                default:
                    callback({ success: false, error: 'Invalid action' });
            }
        } catch (error) {
            callback({ success: false, error: error.message });
        }
    });

    // Handle disconnection
    socket.on('disconnect', () => {
        // Clean up room memberships
        io.sockets.adapter.rooms.forEach((members, roomId) => {
            if (members.delete(socket.id)) {
                // Notify room members of departure
                io.to(roomId).emit('room_update', {
                    roomId,
                    memberCount: members.size
                });
            }
        });
        logEvent('disconnect', { socketId: socket.id });
    });

    // Add status file writing for admin UI
    updateAdminStatus({ lastConnection: Date.now() });
    updateAdminStats();
});

// Start server
server.listen(config.port, () => {
    const status = {
        pid: process.pid,
        port: config.port,
        startTime: Date.now()
    };

    // Write status file for PHP process
    fs.writeFileSync(config.statusPath, JSON.stringify(status));
    logEvent('server_start', status);
});

// Handle shutdown
process.on('SIGTERM', () => {
    logEvent('shutdown', { pid: process.pid });
    server.close(() => {
        process.exit(0);
    });
});

// Logging helper
function logEvent(event, data) {
    const logEntry = {
        timestamp: new Date().toISOString(),
        event,
        data
    };

    fs.appendFileSync(config.logPath, JSON.stringify(logEntry) + '\n');
}

// Add status file writing for admin UI
function updateAdminStatus(status) {
    fs.writeFileSync(config.statusPath, JSON.stringify({
        running: true,
        connections: io.engine.clientsCount,
        uptime: process.uptime(),
        rooms: Array.from(io.sockets.adapter.rooms.entries()).map(([roomId, members]) => ({
            roomId,
            memberCount: members.size
        })),
        ...status
    }));
}

// Add stats file writing for admin UI
function updateAdminStats() {
    fs.writeFileSync(config.statsPath, JSON.stringify({
        timestamp: Date.now(),
        connections: io.engine.clientsCount,
        rooms: Array.from(io.sockets.adapter.rooms.entries()).map(([roomId, members]) => ({
            roomId,
            memberCount: members.size
        }))
    }));
}

// Update stats periodically for admin UI
setInterval(() => {
    updateAdminStats();
}, 5000); // Every 5 seconds