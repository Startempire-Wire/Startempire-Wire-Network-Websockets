/**
 * Location: node-server/server.js
 * Dependencies: Socket.io, Redis, WordPress DB
 * Variables/Classes: io, server, metrics
 * Purpose: Core WebSocket server handling real-time communications and tier-based connection management. Integrates with WordPress authentication and enforces network membership rules for data distribution.
 */
const express = require('express');
const { Server } = require('socket.io');
const http = require('http');
const fs = require('fs');
const path = require('path');
const jwt = require('jsonwebtoken');
const dotenv = require('dotenv');
const winston = require('winston');
const Redis = require('redis');

// Remove wpdb require as it's not a standard module
// const wpdb = require('wpdb');

// Move error handlers to top, before any other initialization
process.on('uncaughtException', (err) => {
    console.error('FATAL: Uncaught Exception:', err);
    fs.appendFileSync('server.log', `FATAL: Uncaught Exception: ${err.stack}\n`);
    process.exit(1);
});

process.on('unhandledRejection', (reason, promise) => {
    console.error('FATAL: Unhandled Rejection:', reason);
    fs.appendFileSync('server.log', `FATAL: Unhandled Rejection: ${reason?.stack || reason}\n`);
    process.exit(1);
});

process.on('exit', (code) => {
    console.error(`Process exiting with code: ${code}`);
    fs.appendFileSync('server.log', `Process exit with code: ${code}\n`);
});

// Wrap the entire initialization in a try/catch
try {
    // Load and validate environment variables
    dotenv.config();

    // Get environment variables with validation and defaults
    /**
     * Port Configuration:
     * - Default: 49200 (IANA Dynamic Port Range 49152-65535)
     * - Chosen to avoid conflicts with common development ports
     * - Can be overridden via WP_PORT environment variable
     */
    const port = process.env.WP_PORT ? parseInt(process.env.WP_PORT) : 49200;
    console.log('Server starting with port:', process.env.WP_PORT, 'parsed as:', port);

    // Validate port is in acceptable range
    if (port < 1024 || port > 65535) {
        console.error('Invalid port number. Must be between 1024 and 65535');
        process.exit(1);
    }

    const baseDir = process.env.WP_PLUGIN_DIR || path.join(__dirname);
    const statsFile = process.env.WP_STATS_FILE || path.join(baseDir, 'tmp/stats.json');
    const pidFile = process.env.WP_PID_FILE || path.join(baseDir, 'server.pid');
    const logFile = process.env.WP_LOG_FILE || path.join(baseDir, 'server.log');
    const debug = process.argv.includes('--debug=true');

    // Ensure directories exist with error handling
    const ensureDirectoryExists = (filePath) => {
        const dirname = path.dirname(filePath);
        if (!fs.existsSync(dirname)) {
            fs.mkdirSync(dirname, { recursive: true, mode: 0o755 });
        }
    };

    // Initialize stats first
    const Stats = require('./lib/stats');
    const stats = new Stats({
        statsFile,
        debug
    });

    // Create necessary directories with explicit error handling
    [statsFile, pidFile, logFile].forEach(file => {
        try {
            ensureDirectoryExists(file);
        } catch (error) {
            console.error('FATAL: Failed to create directory for file:', file, error);
            fs.appendFileSync('server.log', `FATAL: Directory creation failed for ${file}: ${error.stack}\n`);
            process.exit(1);
        }
    });

    // Write initial stats
    await stats.writeStats();

    // Create Express app and HTTP server
    const app = express();
    const server = http.createServer(app);

    // Enhanced Socket.IO configuration
    const io = new Server(server, {
        transports: ['websocket', 'polling'],
        pingTimeout: 60000,
        pingInterval: 25000,
        cors: {
            origin: "*",
            methods: ["GET", "POST"]
        }
    });

    // Initialize stats processor
    const StatsProcessor = require('./lib/stats-processor');
    const statsProcessor = new StatsProcessor(io, stats);

    // Initialize admin stats
    const AdminStats = require('./lib/admin-stats');
    const adminStats = new AdminStats(io, stats);

    // Handle admin namespace
    const adminNamespace = io.of('/admin');
    adminNamespace.on('connection', socket => {
        adminStats.handleAdminConnection(socket);
    });

    // Start server with proper error handling
    server.listen(port, () => {
        console.log(`Server listening on port ${port}`);
        fs.writeFileSync(pidFile, process.pid.toString());
    });

    // Handle cleanup on exit
    const cleanup = async () => {
        console.log('Cleaning up before exit...');
        try {
            await stats.save();
            await stats.close();
            if (fs.existsSync(pidFile)) {
                fs.unlinkSync(pidFile);
            }
        } catch (error) {
            console.error('Error during cleanup:', error);
        }
        process.exit(0);
    };

    process.on('SIGTERM', cleanup);
    process.on('SIGINT', cleanup);

    // Load configuration with validation
    const config = {
        port: port,
        logPath: logFile,
        statusPath: path.join(path.dirname(statsFile), 'status.json'),
        statsPath: statsFile
    };

    // Validate configuration
    if (!config.port || !config.logPath || !config.statusPath || !config.statsPath) {
        logger.error('Invalid configuration:', config);
        process.exit(1);
    }

    // Initialize Redis client (optional, for future scalability)
    let redisClient;
    if (process.env.REDIS_URL) {
        redisClient = Redis.createClient({ url: process.env.REDIS_URL });
        redisClient.on('error', err => logger.error('Redis Client Error', err));
        redisClient.connect().catch(err => logger.error('Error connecting to Redis', err));
    }

    // Initialize metrics tracking with safe defaults
    const metrics = {
        messagesIn: 0,
        messagesOut: 0,
        lastUpdate: Date.now(),
        messageRates: {
            in: 0,
            out: 0
        },
        resetCounters() {
            this.messagesIn = 0;
            this.messagesOut = 0;
            this.lastUpdate = Date.now();
        }
    };

    // Socket.IO error handling
    io.on('connect_error', (err) => {
        console.error('Socket.IO connection error:', err);
        logger.error('Socket.IO connection error:', err);
    });

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
            metrics.messagesIn++;
            logger.info('Received message:', message, 'from user:', socket.user ? socket.user.userId : 'unknown');
            stats.channels.message.messages++;
            emitChannelStats('message');
            messageNamespace.emit('message', { ...message, senderId: socket.user.userId, timestamp: Date.now() }); // Broadcast to all in /message
            metrics.messagesOut++;
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

    // Update emitStats function to use metrics safely
    function emitStats() {
        try {
            const currentStats = {
                ...stats,
                status: 'running',
                connections: io?.engine?.clientsCount || 0,
                rooms: Array.from(io?.sockets?.adapter?.rooms?.keys() || []),
                uptime: process.uptime(),
                memory: process.memoryUsage(),
                timestamp: Date.now(),
                message_rate: {
                    in: metrics.messageRates.in || 0,
                    out: metrics.messageRates.out || 0
                }
            };

            // Calculate memory usage in MB
            currentStats.memory_formatted = {
                heapUsed: Math.round((currentStats.memory.heapUsed / 1024 / 1024) * 100) / 100,
                heapTotal: Math.round((currentStats.memory.heapTotal / 1024 / 1024) * 100) / 100,
                rss: Math.round((currentStats.memory.rss / 1024 / 1024) * 100) / 100
            };

            // Calculate message rates safely
            const timeDiff = Math.max((Date.now() - metrics.lastUpdate) / 1000, 1); // Ensure minimum 1 second
            metrics.messageRates.in = Math.round(metrics.messagesIn / timeDiff);
            metrics.messageRates.out = Math.round(metrics.messagesOut / timeDiff);

            // Reset counters after calculating rates
            metrics.resetCounters();

            // Add system load (Unix-like systems only)
            try {
                currentStats.load = require('os').loadavg();
            } catch (e) {
                currentStats.load = [0, 0, 0];
            }

            // Emit to admin namespace if available
            if (adminNamespace) {
                adminNamespace.emit('stats_update', currentStats);
            }

            // Update stats file
            updateStats(currentStats);
        } catch (error) {
            logger.error('Error in emitStats:', error);
            // Don't throw the error, just log it
            // We want the stats emission to continue even if there's an error
        }
    }

    // Update stats more frequently (every second)
    setInterval(emitStats, 1000);

    // Enhanced channel stats emission
    function emitChannelStats(channelName) {
        const channelStats = stats.channels[channelName];
        adminNamespace.to(`monitor:${channelName}`).emit('channel_update', {
            channel: channelName,
            stats: channelStats,
            timestamp: Date.now()
        });
    }

    // Add real-time error reporting
    function reportError(error, context = {}) {
        const errorReport = {
            message: error.message,
            stack: error.stack,
            context,
            timestamp: Date.now()
        };

        adminNamespace.emit('error_report', errorReport);
        logger.error('Error occurred:', errorReport);
    }

    // Add real-time connection tracking
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

        // Track connection duration
        const connectionStart = Date.now();

        socket.on('disconnect', () => {
            const duration = Date.now() - connectionStart;
            const connectionStats = {
                duration,
                socketId: socket.id,
                timestamp: Date.now()
            };
            adminNamespace.emit('connection_ended', connectionStats);
        });
    });

    // Add port checking
    const checkPort = (port) => new Promise((resolve, reject) => {
        const tempServer = http.createServer();
        tempServer.listen(port, () => {
            tempServer.close(() => resolve(true));
        });
        tempServer.on('error', (err) => {
            if (err.code === 'EADDRINUSE') {
                reject(new Error(`Port ${port} is already in use`));
            } else {
                reject(err);
            }
        });
    });

    // Enhanced process verification function
    async function verifyProcess(pid) {
        const logger = winston.createLogger({
            level: 'info',
            format: winston.format.combine(
                winston.format.timestamp(),
                winston.format.json()
            ),
            transports: [
                new winston.transports.Console(),
                new winston.transports.File({ filename: 'server.log' })
            ]
        });

        try {
            if (process.platform === 'darwin') {
                const { execSync } = require('child_process');
                try {
                    const psOutput = execSync(`ps -p ${pid} -o command=`, { encoding: 'utf8' });
                    logger.info(`Process command for PID ${pid}: ${psOutput.trim()}`);

                    // Check if it's our Node.js server
                    if (psOutput.includes('node') && psOutput.includes('server.js')) {
                        return { running: true, isOurs: true };
                    }
                    return { running: true, isOurs: false };
                } catch (psError) {
                    if (psError.status === 1) {
                        logger.info(`No process found with PID ${pid}`);
                        return { running: false, isOurs: false };
                    }
                    throw psError;
                }
            } else {
                try {
                    process.kill(pid, 0);
                    logger.info(`Process ${pid} exists, but cannot determine if it's ours`);
                    return { running: true, isOurs: true };
                } catch (error) {
                    if (error.code === 'ESRCH') {
                        logger.info(`No process found with PID ${pid}`);
                        return { running: false, isOurs: false };
                    }
                    throw error;
                }
            }
        } catch (error) {
            logger.error('Error verifying process:', error);
            throw error;
        }
    }

    // Enhanced environment configuration with Local by Flywheel support
    const getEnvironmentConfig = () => {
        // Load environment variables
        dotenv.config();

        // Default configuration
        const config = {
            port: process.env.WP_PORT ? parseInt(process.env.WP_PORT) : 49200,
            host: process.env.WP_HOST || '0.0.0.0', // Listen on all interfaces by default
            ssl: {
                enabled: process.env.WP_SSL === 'true',
                key: process.env.WP_SSL_KEY,
                cert: process.env.WP_SSL_CERT
            },
            containerMode: process.env.WP_CONTAINER_MODE === 'true',
            baseDir: process.env.WP_PLUGIN_DIR || path.join(__dirname),
            debug: process.argv.includes('--debug=true')
        };

        // Detect Local by Flywheel environment
        if (process.env.LOCAL_SITE_URL) {
            config.containerMode = true;
            config.localSiteUrl = process.env.LOCAL_SITE_URL;

            // Parse Local site URL for SSL detection
            if (config.localSiteUrl.startsWith('https')) {
                config.ssl.enabled = true;
                // Use Local's SSL certificates if available
                if (!config.ssl.key && process.env.LOCAL_SSL_KEY) {
                    config.ssl.key = process.env.LOCAL_SSL_KEY;
                    config.ssl.cert = process.env.LOCAL_SSL_CERT;
                }
            }
        }

        // Container IP detection
        if (config.containerMode) {
            try {
                // Try to get container IP
                const { networkInterfaces } = require('os');
                const nets = networkInterfaces();
                const results = {};

                for (const name of Object.keys(nets)) {
                    for (const net of nets[name]) {
                        // Skip internal and non-IPv4 addresses
                        if (net.family === 'IPv4' && !net.internal) {
                            results[name] = net.address;
                        }
                    }
                }

                // Use first available non-internal IPv4 address
                const containerIp = Object.values(results)[0];
                if (containerIp) {
                    config.host = containerIp;
                    logger.info(`Detected container IP: ${containerIp}`);
                }
            } catch (error) {
                logger.warn('Failed to detect container IP:', error);
            }
        }

        return config;
    };

    // Add proxy configuration detection and setup
    const setupProxySupport = (app) => {
        // Detect proxy headers
        app.set('trust proxy', ['loopback', 'linklocal', 'uniquelocal']);

        // Add WebSocket upgrade handling for proxy
        app.use((req, res, next) => {
            // Handle WebSocket upgrade requests
            if (req.headers.upgrade && req.headers.upgrade.toLowerCase() === 'websocket') {
                res.setHeader('Connection', 'upgrade');
                res.setHeader('Upgrade', 'websocket');
            }
            next();
        });

        // Add CORS headers for WebSocket
        app.use((req, res, next) => {
            res.setHeader('Access-Control-Allow-Origin', '*');
            res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            res.setHeader('Access-Control-Allow-Headers', 'X-Requested-With,content-type');
            res.setHeader('Access-Control-Allow-Credentials', true);
            next();
        });
    };

    // Add debugging tools for Local environment
    const setupDebugTools = (app, config) => {
        if (config.debug) {
            // Add debug endpoint
            app.get('/debug', (req, res) => {
                const debugInfo = {
                    environment: {
                        nodeVersion: process.version,
                        platform: process.platform,
                        arch: process.arch,
                        containerMode: config.containerMode
                    },
                    network: {
                        host: config.host,
                        port: config.port,
                        ssl: config.ssl.enabled,
                        proxy: app.get('trust proxy')
                    },
                    headers: req.headers,
                    connection: {
                        protocol: req.protocol,
                        secure: req.secure,
                        ip: req.ip,
                        ips: req.ips
                    },
                    server: {
                        uptime: process.uptime(),
                        memoryUsage: process.memoryUsage(),
                        cpuUsage: process.cpuUsage()
                    }
                };

                // Format response based on accept header
                if (req.accepts('html')) {
                    res.send(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>WebSocket Server Debug Info</title>
                            <style>
                                body { font-family: monospace; padding: 20px; }
                                pre { background: #f5f5f5; padding: 10px; }
                            </style>
                        </head>
                        <body>
                            <h1>WebSocket Server Debug Information</h1>
                            <h2>Environment</h2>
                            <pre>${JSON.stringify(debugInfo.environment, null, 2)}</pre>
                            <h2>Network Configuration</h2>
                            <pre>${JSON.stringify(debugInfo.network, null, 2)}</pre>
                            <h2>Request Headers</h2>
                            <pre>${JSON.stringify(debugInfo.headers, null, 2)}</pre>
                            <h2>Connection Info</h2>
                            <pre>${JSON.stringify(debugInfo.connection, null, 2)}</pre>
                            <h2>Server Status</h2>
                            <pre>${JSON.stringify(debugInfo.server, null, 2)}</pre>
                            <p><a href="/socket-test">WebSocket Test Page</a></p>
                        </body>
                        </html>
                    `);
                } else {
                    res.json(debugInfo);
                }
            });

            // Add WebSocket debug logging
            app.use((req, res, next) => {
                if (req.headers.upgrade && req.headers.upgrade.toLowerCase() === 'websocket') {
                    logger.debug('WebSocket upgrade request:', {
                        headers: req.headers,
                        url: req.url,
                        method: req.method,
                        ip: req.ip
                    });
                }
                next();
            });

            // Enhanced error handling for debugging
            app.use((err, req, res, next) => {
                logger.error('Express error:', err);
                res.status(500).json({
                    error: err.message,
                    stack: config.debug ? err.stack : undefined
                });
            });
        }
    };

    // Modify initServer to include proxy support and debug tools
    const initServer = async (config) => {
        // Create Express app
        const app = express();

        // Setup proxy support
        setupProxySupport(app);

        // Setup debug tools if enabled
        setupDebugTools(app, config);

        // Create HTTP/HTTPS server based on SSL configuration
        let server;
        if (config.ssl.enabled && config.ssl.key && config.ssl.cert) {
            const https = require('https');
            const sslOptions = {
                key: fs.readFileSync(config.ssl.key),
                cert: fs.readFileSync(config.ssl.cert),
                // Add options for Local by Flywheel SSL
                requestCert: false,
                rejectUnauthorized: false
            };
            server = https.createServer(sslOptions, app);
            logger.info('Created HTTPS server with SSL');
        } else {
            server = http.createServer(app);
            logger.info('Created HTTP server');
        }

        // Enhanced Socket.IO configuration for proxy compatibility
        const io = new Server(server, {
            transports: ['websocket', 'polling'],
            pingTimeout: 60000,
            pingInterval: 25000,
            cors: {
                origin: "*",
                methods: ["GET", "POST", "OPTIONS"],
                credentials: true
            },
            path: '/socket.io/', // Explicit path for proxy compatibility
            allowEIO3: true, // Enable Engine.IO v3 for better proxy support
            handlePreflightRequest: (req, res) => {
                res.writeHead(200, {
                    "Access-Control-Allow-Origin": "*",
                    "Access-Control-Allow-Methods": "GET,POST,OPTIONS",
                    "Access-Control-Allow-Headers": "content-type",
                    "Access-Control-Allow-Credentials": "true"
                });
                res.end();
            },
            // Add Local by Flywheel specific settings
            allowUpgrades: true,
            transports: ['polling', 'websocket'],
            upgradeTimeout: 10000,
            pingInterval: 10000,
            pingTimeout: 5000
        });

        // Add health check endpoint for container orchestration
        app.get('/health', (req, res) => {
            res.json({
                status: 'healthy',
                uptime: process.uptime(),
                timestamp: Date.now(),
                containerMode: config.containerMode,
                ssl: config.ssl.enabled
            });
        });

        // Add WebSocket test endpoint
        app.get('/socket-test', (req, res) => {
            res.send(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>WebSocket Test</title>
                    <script src="/socket.io/socket.io.js"></script>
                    <script>
                        const socket = io({
                            path: '/socket.io/',
                            transports: ['websocket', 'polling'],
                            secure: ${config.ssl.enabled},
                            rejectUnauthorized: false
                        });
                        
                        socket.on('connect', () => {
                            document.getElementById('status').textContent = 'Connected';
                            console.log('Connected to WebSocket server');
                        });
                        
                        socket.on('disconnect', () => {
                            document.getElementById('status').textContent = 'Disconnected';
                            console.log('Disconnected from WebSocket server');
                        });
                        
                        socket.on('error', (error) => {
                            document.getElementById('status').textContent = 'Error: ' + error;
                            console.error('WebSocket error:', error);
                        });
                    </script>
                </head>
                <body>
                    <h1>WebSocket Test Page</h1>
                    <p>Status: <span id="status">Connecting...</span></p>
                    <p>Server Info:</p>
                    <pre>
                        Host: ${config.host}
                        Port: ${config.port}
                        SSL: ${config.ssl.enabled}
                        Container Mode: ${config.containerMode}
                    </pre>
                </body>
                </html>
            `);
        });

        return { app, server, io };
    };

    // Modify startServer to use new configuration
    const startServer = async () => {
        try {
            console.log('Starting server initialization...');
            fs.appendFileSync('server.log', `Starting server initialization...\n`);

            // Get environment configuration
            const config = getEnvironmentConfig();

            // Initialize server components
            const { app, server, io } = await initServer(config);

            // Rest of the startup code...

            // Modified server listen with host binding
            return new Promise((resolve, reject) => {
                const serverStartTimeout = setTimeout(() => {
                    const error = new Error('Server start timeout after 10 seconds');
                    logger.error(error.message);
                    reject(error);
                }, 10000);

                try {
                    server.listen(config.port, config.host, () => {
                        clearTimeout(serverStartTimeout);
                        const protocol = config.ssl.enabled ? 'wss' : 'ws';
                        logger.info(`Server listening on ${protocol}://${config.host}:${config.port}`);

                        // Write startup files...
                        resolve();
                    });
                } catch (error) {
                    clearTimeout(serverStartTimeout);
                    logger.error('Error starting server:', error);
                    reject(error);
                }
            });
        } catch (error) {
            logger.error('Server startup failed:', error);
            throw error;
        }
    };

    // Enhanced signal handling
    function setupSignalHandlers() {
        let cleanupInProgress = false;

        async function handleSignal(signal) {
            if (cleanupInProgress) {
                logger.warn(`Received ${signal} while cleanup in progress, ignoring`);
                return;
            }

            cleanupInProgress = true;
            logger.info(`Received ${signal} signal, starting cleanup`);

            try {
                const cleanupResult = await cleanup();
                if (cleanupResult.success) {
                    logger.info(`${signal} cleanup completed successfully`);
                } else {
                    logger.warn(`${signal} cleanup completed with errors:`, cleanupResult.errors);
                }
            } catch (error) {
                logger.error(`Error during ${signal} cleanup:`, error);
            } finally {
                process.exit(signal === 'SIGTERM' ? 0 : 1);
            }
        }

        // Handle termination signals
        process.on('SIGTERM', () => handleSignal('SIGTERM'));
        process.on('SIGINT', () => handleSignal('SIGINT'));

        // Handle uncaught errors
        process.on('uncaughtException', async (error) => {
            logger.error('Uncaught Exception:', error);
            await handleSignal('uncaughtException');
        });

        process.on('unhandledRejection', async (reason, promise) => {
            logger.error('Unhandled Rejection at:', promise, 'reason:', reason);
            await handleSignal('unhandledRejection');
        });
    }

    // Add error recovery function
    async function recoverFromError(error) {
        logger.error('Attempting to recover from error:', error);

        try {
            // Update stats to reflect error
            stats.status = 'error';
            stats.last_error = error.message;
            stats.error_time = Date.now();
            await fs.promises.writeFile(statsFile, JSON.stringify(stats, null, 2));

            // Check if we need to clean up files
            const processStatus = await verifyProcess(process.pid);
            if (!processStatus.running || !processStatus.isOurs) {
                logger.info('Process status indicates cleanup needed');
                await cleanup();
            }

            // Attempt to restart server components
            if (io) {
                try {
                    await new Promise((resolve) => io.close(resolve));
                    logger.info('Successfully closed Socket.IO server');
                } catch (err) {
                    logger.error('Error closing Socket.IO server:', err);
                }
            }

            if (server) {
                try {
                    await new Promise((resolve) => server.close(resolve));
                    logger.info('Successfully closed HTTP server');
                } catch (err) {
                    logger.error('Error closing HTTP server:', err);
                }
            }

            // Return recovery status
            return {
                recovered: true,
                error: error.message,
                timestamp: Date.now()
            };
        } catch (recoveryError) {
            logger.error('Failed to recover from error:', recoveryError);
            return {
                recovered: false,
                error: error.message,
                recoveryError: recoveryError.message,
                timestamp: Date.now()
            };
        }
    }

    // Modify startServer to use new error handling
    startServer().then(() => {
        logger.info('Server started successfully');
        setupSignalHandlers();
    }).catch(async (err) => {
        logger.error('Failed to start server:', err);

        try {
            const recoveryResult = await recoverFromError(err);
            if (recoveryResult.recovered) {
                logger.info('Successfully recovered from startup error');
                // Attempt restart
                setTimeout(() => {
                    startServer().catch(console.error);
                }, 5000);
            } else {
                logger.error('Failed to recover from startup error:', recoveryResult);
                process.exit(1);
            }
        } catch (recoveryErr) {
            logger.error('Fatal error during recovery:', recoveryErr);
            process.exit(1);
        }
    });

    // Enhanced cleanup function with proper shutdown sequence
    async function cleanup() {
        logger.info('Starting enhanced server cleanup...');

        let cleanupSuccess = true;
        const errors = [];

        // Update stats first
        try {
            stats.status = 'stopping';
            stats.stop_time = Date.now();
            stats.uptime = process.uptime();
            await fs.promises.writeFile(statsFile, JSON.stringify(stats, null, 2))
                .catch(err => {
                    cleanupSuccess = false;
                    errors.push(['Failed to update stats file', err]);
                    logger.error('Failed to update stats file:', err);
                });
        } catch (err) {
            cleanupSuccess = false;
            errors.push(['Stats update error', err]);
            logger.error('Error updating stats:', err);
        }

        // Close all socket connections gracefully
        try {
            const sockets = await io.fetchSockets();
            await Promise.all(sockets.map(socket =>
                socket.disconnect(true)
                    .catch(err => logger.warn(`Error disconnecting socket ${socket.id}:`, err))
            ));
        } catch (err) {
            cleanupSuccess = false;
            errors.push(['Socket cleanup error', err]);
            logger.error('Error cleaning up sockets:', err);
        }

        // Remove PID file with verification
        try {
            if (await fs.promises.access(pidFile).then(() => true).catch(() => false)) {
                const filePid = parseInt(await fs.promises.readFile(pidFile, 'utf8'));
                if (filePid === process.pid) {
                    await fs.promises.unlink(pidFile);
                    logger.info('PID file removed successfully');
                } else {
                    logger.warn(`PID file exists but contains different PID (${filePid}), not removing`);
                }
            }
        } catch (err) {
            cleanupSuccess = false;
            errors.push(['PID file cleanup error', err]);
            logger.error('Error cleaning up PID file:', err);
        }

        // Final cleanup and status report
        return new Promise((resolve) => {
            const cleanupTimeout = setTimeout(() => {
                logger.warn('Cleanup timeout after 5 seconds, forcing shutdown');
                Promise.all([
                    fs.promises.unlink(pidFile).catch(() => { }),
                    fs.promises.unlink(statsFile).catch(() => { })
                ]).finally(() => {
                    if (errors.length > 0) {
                        logger.error('Cleanup completed with errors:', errors);
                    }
                    resolve({ success: cleanupSuccess, errors });
                });
            }, 5000);

            // Try graceful shutdown first
            server.close(() => {
                clearTimeout(cleanupTimeout);
                if (errors.length > 0) {
                    logger.error('Cleanup completed with errors:', errors);
                } else {
                    logger.info('Cleanup completed successfully');
                }
                resolve({ success: cleanupSuccess, errors });
            });
        });
    }

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

    // Function to update stats file
    function updateStats(newStats) {
        stats = { ...stats, ...newStats, timestamp: Date.now() };
        try {
            fs.writeFileSync(config.statsPath, JSON.stringify(stats, null, 2));
        } catch (err) {
            console.error('Error writing stats file:', err);
            fs.appendFileSync(config.logPath, `Stats File Error: ${err}\n`);
        }
    }
} catch (error) {
    console.error('FATAL: Server initialization failed:', error);
    fs.appendFileSync('server.log', `FATAL: Server initialization failed: ${error.stack}\n`);
    process.exit(1);
}