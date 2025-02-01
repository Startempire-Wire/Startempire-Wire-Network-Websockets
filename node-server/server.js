const { Server } = require("socket.io");
const jwt = require('jsonwebtoken');
const dotenv = require('dotenv');
const winston = require('winston');
const Redis = require('redis');

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

const PORT = process.env.WEBSOCKET_PORT || 8080;
const JWT_SECRET = process.env.JWT_SECRET;
if (!JWT_SECRET) {
    logger.error('JWT_SECRET is not defined in .env file!');
    process.exit(1);
}

// Initialize Redis client (optional, for future scalability)
let redisClient;
if (process.env.REDIS_URL) {
    redisClient = Redis.createClient({ url: process.env.REDIS_URL });
    redisClient.on('error', err => logger.error('Redis Client Error', err));
    redisClient.connect().catch(err => logger.error('Error connecting to Redis', err));
}

const io = new Server({
    cors: {
        origin: "*", // For development, configure for production
        methods: ["GET", "POST"]
    }
});

// Stats tracking
const stats = {
    connections: 0,
    channels: {
        message: { messages: 0, errors: 0, subscribers: 0 },
        presence: { messages: 0, errors: 0, subscribers: 0 },
        status: { messages: 0, errors: 0, subscribers: 0 },
    },
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
}

function emitChannelStats(channelName) {
    adminNamespace.emit('channelStatsUpdate', { channel: channelName, stats: stats.channels[channelName] });
}


// Stats update interval - send stats to admin dashboard every 2 seconds
setInterval(emitStats, 2000);

io.listen(PORT, () => {
    logger.info(`WebSocket server listening on port ${PORT}`);
});