/**
 * Location: node-server/server.js
 * Dependencies: Socket.io, Redis, WordPress DB
 * Variables/Classes: io, server, metrics
 * Purpose: Core WebSocket server handling real-time communications and tier-based connection management. Integrates with WordPress authentication and enforces network membership rules for data distribution.
 */
const express = require('express');
const { Server } = require('socket.io');
const http = require('http');
const https = require('https');
const fs = require('fs');
const fsPromises = require('fs').promises;
const path = require('path');
const jwt = require('jsonwebtoken');
const dotenv = require('dotenv');
const winston = require('winston');
const Redis = require('redis');
const { networkInterfaces } = require('os');
const { ensureDirectoryExists } = require('./utils');
const config = require('./config');

// Load environment variables first
dotenv.config({ path: path.join(__dirname, '.env') });

// Global variables
const port = process.env.WP_PORT ? parseInt(process.env.WP_PORT) : 49200;
const host = process.env.WP_HOST || 'localhost';
const baseDir = process.env.WP_PLUGIN_DIR || path.join(__dirname, '..');
const logFile = process.env.WP_LOG_FILE || path.join(baseDir, 'logs', 'server.log');
const statsFile = process.env.WP_STATS_FILE || path.join(baseDir, 'tmp', 'stats.json');
const pidFile = process.env.WP_PID_FILE || path.join(baseDir, 'tmp', 'server.pid');

// Global objects
let io;
let server;
let redisClient = null;
let adminNamespace;
let authManager;
let connectionManager;
let app = express(); // Initialize Express app globally
let metrics = {
    startTime: Date.now(),
    messagesIn: 0,
    messagesOut: 0,
    errors: 0,
    connections: 0,
    messageRateIn: 0,
    messageRateOut: 0
};
let namespaces = {};

// Initialize logger
const logger = winston.createLogger({
    level: config.environment.debug ? 'debug' : 'info',
    format: winston.format.combine(
        winston.format.timestamp(),
        winston.format.json()
    ),
    transports: [
        new winston.transports.File({ filename: config.paths.logs })
    ]
});

// Add console transport in debug mode
if (config.environment.debug) {
    logger.add(new winston.transports.Console({
        format: winston.format.simple()
    }));
}

// Store process information immediately
const processInfo = {
    nodePath: process.execPath,
    scriptPath: __filename,
    pid: process.pid,
    startTime: Date.now()
};

// Log process information right away
logger.info('Server process information:', {
    nodePath: processInfo.nodePath,
    scriptPath: processInfo.scriptPath,
    pid: processInfo.pid
});

// Add process monitoring
let serverShutdownInitiated = false;
let shutdownReason = null;

process.on('exit', (code) => {
    logger.info(`Process exit with code: ${code}, Reason: ${shutdownReason || 'Unknown'}`);
});

// Enhanced error handlers
process.on('uncaughtException', (err) => {
    shutdownReason = `Uncaught Exception: ${err.message}`;
    logger.error('FATAL: Uncaught Exception:', {
        error: err.message,
        stack: err.stack,
        pid: process.pid
    });
    cleanup('uncaughtException').catch(console.error);
});

process.on('unhandledRejection', (reason, promise) => {
    shutdownReason = `Unhandled Rejection: ${reason}`;
    logger.error('FATAL: Unhandled Rejection:', {
        reason: reason?.stack || reason,
        pid: process.pid
    });
    cleanup('unhandledRejection').catch(console.error);
});

// Add server state monitoring
const serverState = {
    isRunning: false,
    startTime: null,
    lastHealthCheck: null,
    errors: [],
    restartAttempts: 0
};

// Initialize stats object
let stats = {
    channels: {
        message: { subscribers: 0, messages: 0 },
        presence: { subscribers: 0, messages: 0 },
        status: { subscribers: 0, messages: 0 }
    },
    tiers: {
        free: { connections: 0, bandwidth: 0 },
        api: { connections: 0, bandwidth: 0 },
        admin: { connections: 0, bandwidth: 0 }
    },
    namespaces: {}
};

// Initialize Redis client
async function initializeRedis() {
    if (process.env.REDIS_URL) {
        try {
            redisClient = Redis.createClient({
                url: process.env.REDIS_URL,
                retry_strategy: function (options) {
                    if (options.total_retry_time > 1000 * 60 * 60) {
                        logger.error('Redis retry time exhausted');
                        return null;
                    }
                    return Math.min(options.attempt * 100, 3000);
                }
            });

            redisClient.on('error', (err) => {
                logger.error('Redis Client Error:', err);
            });

            redisClient.on('connect', () => {
                logger.info('Redis Client Connected');
            });

            redisClient.on('reconnecting', () => {
                logger.info('Redis Client Reconnecting');
            });

            await redisClient.connect();
            return true;
        } catch (error) {
            logger.error('Redis initialization failed:', error);
            redisClient = null;
            return false;
        }
    }
    logger.info('Redis URL not configured, skipping Redis initialization');
    return false;
}

// Verify admin token function
async function verifyAdminToken(token) {
    try {
        const validation = await authManager.validateToken(token);
        if (validation.source === 'wordpress' && validation.capabilities.includes('all')) {
            return true;
        }
        throw new Error('Not authorized as admin');
    } catch (error) {
        throw new Error('Admin token verification failed: ' + error.message);
    }
}

// Authentication helper functions
async function isWordPressAdmin(token) {
    try {
        const decoded = jwt.verify(token, process.env.WP_JWT_SECRET);
        return decoded.isAdmin === true;
    } catch (error) {
        logger.warn('WordPress admin token verification failed:', error.message);
        return false;
    }
}

async function isValidApiKey(token) {
    try {
        // Check if token matches API key pattern
        if (!token.match(/^sewn_ws_[a-zA-Z0-9]{32}$/)) {
            return false;
        }

        // Verify with Redis if available
        if (redisClient) {
            const apiKey = await redisClient.get(`api_key:${token}`);
            return !!apiKey;
        }

        // Fallback to environment check
        return token === process.env.WP_API_KEY;
    } catch (error) {
        logger.warn('API key verification failed:', error.message);
        return false;
    }
}

async function verifyRingLeaderToken(token) {
    try {
        const decoded = jwt.verify(token, process.env.WP_JWT_SECRET);
        if (!decoded.tier) {
            throw new Error('No tier specified in token');
        }
        return {
            tier: decoded.tier,
            userId: decoded.wp_user,
            capabilities: decoded.capabilities || []
        };
    } catch (error) {
        logger.warn('Ring Leader token verification failed:', error.message);
        return null;
    }
}

// Function to check if a port is available
async function isPortAvailable(port) {
    return new Promise((resolve) => {
        const tester = require('net').createServer()
            .once('error', (err) => {
                if (err.code === 'EADDRINUSE') {
                    resolve(false);
                } else {
                    resolve(false);
                }
            })
            .once('listening', () => {
                tester.once('close', () => resolve(true)).close();
            })
            .listen(port);

        // Add a timeout to prevent hanging
        setTimeout(() => {
            tester.removeAllListeners();
            tester.close();
            resolve(false);
        }, 1000);
    });
}

// Function to try binding to a port with retries
async function tryBindPort(server, startPort, maxRetries = 10) {
    let currentPort = startPort;
    let attempts = 0;

    while (attempts < maxRetries) {
        try {
            // Check if port is available
            const isAvailable = await isPortAvailable(currentPort);
            if (!isAvailable) {
                logger.warn(`Port ${currentPort} is in use, trying next port...`);
                currentPort++;
                attempts++;
                continue;
            }

            // Try to bind the server
            await new Promise((resolve, reject) => {
                const bindTimeout = setTimeout(() => {
                    server.removeAllListeners();
                    reject(new Error('Port binding timeout'));
                }, 5000);

                server.once('error', (err) => {
                    clearTimeout(bindTimeout);
                    reject(err);
                });

                server.once('listening', () => {
                    clearTimeout(bindTimeout);
                    resolve();
                });

                server.listen(currentPort, host);
            });

            logger.info(`Successfully bound to port ${currentPort}`);
            return { success: true, port: currentPort };
        } catch (err) {
            logger.warn(`Failed to bind to port ${currentPort}:`, err.message);
            if (err.code === 'EADDRINUSE') {
                currentPort++;
                attempts++;
                continue;
            }
            throw err;
        }
    }

    throw new Error(`Failed to find available port after ${maxRetries} attempts`);
}

// Main server initialization
async function initializeServer() {
    try {
        logger.info('Starting server initialization...', {
            nodeVersion: process.version,
            platform: process.platform,
            pid: process.pid
        });

        // Ensure directories exist
        await Promise.all([
            ensureDirectoryExists(path.dirname(statsFile)),
            ensureDirectoryExists(path.dirname(pidFile)),
            ensureDirectoryExists(path.dirname(logFile))
        ]);

        // Initialize Redis if configured
        await initializeRedis();

        // Create Express app instance
        app = express();

        // Create HTTP/HTTPS server instance
        server = process.env.WP_SSL === 'true' && process.env.WP_SSL_KEY && process.env.WP_SSL_CERT
            ? https.createServer({
                key: fs.readFileSync(process.env.WP_SSL_KEY),
                cert: fs.readFileSync(process.env.WP_SSL_CERT)
            }, app)
            : http.createServer(app);

        // Try to bind to a port with retries
        let currentPort = port;
        let attempts = 0;
        const maxRetries = 10;

        while (attempts < maxRetries) {
            try {
                // Check if port is available
                const isAvailable = await isPortAvailable(currentPort);
                if (!isAvailable) {
                    logger.warn(`Port ${currentPort} is in use, trying next port...`);
                    currentPort++;
                    attempts++;
                    continue;
                }

                // Try to bind the server
                await new Promise((resolve, reject) => {
                    const bindTimeout = setTimeout(() => {
                        server.removeAllListeners();
                        reject(new Error('Port binding timeout'));
                    }, 5000);

                    server.once('error', (err) => {
                        clearTimeout(bindTimeout);
                        reject(err);
                    });

                    server.once('listening', () => {
                        clearTimeout(bindTimeout);
                        resolve();
                    });

                    server.listen(currentPort, host);
                });

                logger.info(`Server bound successfully to port ${currentPort}`);
                break;
            } catch (err) {
                logger.warn(`Failed to bind to port ${currentPort}:`, err.message);
                if (err.code === 'EADDRINUSE') {
                    currentPort++;
                    attempts++;
                    continue;
                }
                throw err;
            }
        }

        if (attempts >= maxRetries) {
            throw new Error(`Failed to find available port after ${maxRetries} attempts`);
        }

        // Write PID file
        await fsPromises.writeFile(pidFile, JSON.stringify({
            pid: process.pid,
            port: currentPort,
            host: host,
            startTime: Date.now()
        }));

        // Initialize Socket.IO with the server
        io = new Server(server, {
            transports: ['websocket', 'polling'],
            pingTimeout: 60000,
            pingInterval: 25000,
            cors: {
                origin: process.env.WP_SITE_URL || "*",
                methods: ["GET", "POST", "OPTIONS"],
                credentials: true
            },
            path: process.env.WP_PROXY_PATH || '/socket.io',
            allowEIO3: true,
            connectTimeout: 45000
        });

        // Initialize managers
        const ConnectionManager = require('./lib/connection-manager');
        const AuthManager = require('./lib/auth-manager');
        connectionManager = new ConnectionManager(io);
        authManager = new AuthManager(io, connectionManager);

        // Set up admin namespace
        adminNamespace = io.of('/admin');
        namespaces.admin = adminNamespace;

        // Set up admin namespace authentication
        adminNamespace.use(async (socket, next) => {
            try {
                const token = socket.handshake.auth.token;
                if (!token) {
                    return next(new Error('Authentication token required'));
                }

                const isValid = await verifyAdminToken(token);
                if (!isValid) {
                    return next(new Error('Invalid admin token'));
                }

                next();
            } catch (error) {
                next(new Error('Authentication failed'));
            }
        });

        // Set up connection logging
        io.on('connection', (socket) => {
            logger.info(`Client connected: ${socket.id}`);

            socket.on('disconnect', () => {
                logger.info(`Client disconnected: ${socket.id}`);
            });
        });

        // Start stats emission after server is initialized
        setInterval(emitStats, 1000);

        return server;
    } catch (error) {
        logger.error('Server initialization failed:', error);
        throw error;
    }
}

// Start server with error handling
initializeServer().catch(async (error) => {
    logger.error('Fatal error during server initialization:', error);

    try {
        await cleanup('initialization_error');
    } catch (cleanupError) {
        logger.error('Error during cleanup:', cleanupError);
    }

    process.exit(1);
});

// Add helper function to find available port
async function findAvailablePort(startPort) {
    const maxPort = 65535;
    for (let port = startPort; port <= maxPort; port++) {
        try {
            await new Promise((resolve, reject) => {
                const tester = require('net').createServer()
                    .once('error', reject)
                    .once('listening', () => {
                        tester.once('close', resolve).close();
                    })
                    .listen(port);
            });
            return port;
        } catch (err) {
            if (err.code !== 'EADDRINUSE') throw err;
        }
    }
    throw new Error('No available ports');
}

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

// Function to safely emit stats
function emitStats() {
    try {
        const now = Date.now();
        const uptime = Math.floor((now - metrics.startTime) / 1000); // Uptime in seconds
        const memoryUsage = process.memoryUsage();
        const heapUsed = Math.round(memoryUsage.heapUsed / 1024 / 1024 * 100) / 100; // MB with 2 decimal places

        const stats = {
            uptime,
            memory: {
                heapUsed: `${heapUsed}MB`,
                external: Math.round(memoryUsage.external / 1024 / 1024 * 100) / 100,
                rss: Math.round(memoryUsage.rss / 1024 / 1024 * 100) / 100
            },
            connections: metrics.connections,
            messages: {
                in: metrics.messagesIn,
                out: metrics.messagesOut,
                errors: metrics.errors,
                rateIn: metrics.messageRateIn,
                rateOut: metrics.messageRateOut
            }
        };

        // Write stats to file if statsFile is defined
        if (statsFile) {
            try {
                // Ensure the directory exists
                const statsDir = path.dirname(statsFile);
                if (!fs.existsSync(statsDir)) {
                    fs.mkdirSync(statsDir, { recursive: true });
                }
                fs.writeFileSync(statsFile, JSON.stringify(stats, null, 2));
            } catch (err) {
                logger.error('Error writing stats file:', err);
            }
        }

        // Emit to admin namespace if it exists
        if (adminNamespace) {
            try {
                adminNamespace.emit('stats', stats);
            } catch (err) {
                logger.error('Error emitting stats:', err);
            }
        }

        logger.debug('Stats updated:', stats);
    } catch (err) {
        logger.error('Error in emitStats:', err);
    }
}

// Update stats every second
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
    try {
        // First check if process exists
        try {
            process.kill(pid, 0);
        } catch (err) {
            if (err.code === 'ESRCH') {
                logger.info(`No process found with PID ${pid}`);
                return { running: false, isOurs: false };
            }
            throw err;
        }

        // Process exists, now verify it's our Node.js server
        if (process.platform === 'darwin') {
            const { execSync } = require('child_process');
            try {
                // Get full command with path on macOS
                const psOutput = execSync(`ps -p ${pid} -o command=`, { encoding: 'utf8' }).trim();
                logger.info(`Process command for PID ${pid}: ${psOutput}`);

                // Split command into parts and normalize paths
                const [nodeBin, ...args] = psOutput.split(' ');
                const scriptPath = args[args.length - 1];

                const normalizedNodeBin = path.resolve(nodeBin);
                const normalizedExpectedNode = path.resolve(processInfo.nodePath);
                const normalizedScriptPath = path.resolve(scriptPath);
                const normalizedExpectedScript = path.resolve(processInfo.scriptPath);

                logger.info('Process verification paths:', {
                    nodeBin: normalizedNodeBin,
                    expectedNode: normalizedExpectedNode,
                    scriptPath: normalizedScriptPath,
                    expectedScript: normalizedExpectedScript
                });

                return {
                    running: true,
                    isOurs: normalizedNodeBin === normalizedExpectedNode &&
                        normalizedScriptPath === normalizedExpectedScript,
                    nodePath: normalizedNodeBin,
                    scriptPath: normalizedScriptPath
                };
            } catch (psError) {
                if (psError.status === 1) {
                    logger.info(`No process found with PID ${pid}`);
                    return { running: false, isOurs: false };
                }
                throw psError;
            }
        } else {
            // Linux/Unix systems
            try {
                const cmdline = await fsPromises.readFile(`/proc/${pid}/cmdline`, 'utf8');
                const parts = cmdline.split('\0').filter(Boolean); // Remove empty strings
                const nodeBin = parts[0];
                const scriptPath = parts[parts.length - 1];

                const normalizedNodeBin = path.resolve(nodeBin);
                const normalizedExpectedNode = path.resolve(processInfo.nodePath);
                const normalizedScriptPath = path.resolve(scriptPath);
                const normalizedExpectedScript = path.resolve(processInfo.scriptPath);

                logger.info('Process verification paths (Linux):', {
                    nodeBin: normalizedNodeBin,
                    expectedNode: normalizedExpectedNode,
                    scriptPath: normalizedScriptPath,
                    expectedScript: normalizedExpectedScript
                });

                return {
                    running: true,
                    isOurs: normalizedNodeBin === normalizedExpectedNode &&
                        normalizedScriptPath === normalizedExpectedScript,
                    nodePath: normalizedNodeBin,
                    scriptPath: normalizedScriptPath
                };
            } catch (procError) {
                logger.warn('Could not verify process details via /proc:', procError);
                // Fallback to basic check
                return {
                    running: true,
                    isOurs: true,
                    nodePath: processInfo.nodePath,
                    scriptPath: processInfo.scriptPath
                };
            }
        }
    } catch (error) {
        logger.error('Error in process verification:', error);
        throw error;
    }
}

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
const initServer = async () => {
    // Create Express app
    const app = express();

    // Setup proxy support
    setupProxySupport(app);

    // Setup debug tools if enabled
    setupDebugTools(app, config);

    // Create HTTP/HTTPS server based on SSL configuration
    let server;
    if (config.environment.ssl_enabled && config.ssl?.key && config.ssl?.cert) {
        server = https.createServer({
            key: fs.readFileSync(config.ssl.key),
            cert: fs.readFileSync(config.ssl.cert)
        }, app);
        logger.info('Created HTTPS server with SSL');
    } else {
        server = http.createServer(app);
        logger.info('Created HTTP server');
    }

    // Initialize Socket.IO with the server
    io = new Server(server, {
        transports: ['websocket', 'polling'],
        pingTimeout: 60000,
        pingInterval: 25000,
        cors: {
            origin: "*",
            methods: ["GET", "POST", "OPTIONS"],
            credentials: true
        },
        path: '/socket.io',
        allowEIO3: true,
        connectTimeout: 45000
    });

    // Initialize managers
    const ConnectionManager = require('./lib/connection-manager');
    const AuthManager = require('./lib/auth-manager');
    connectionManager = new ConnectionManager(io);
    authManager = new AuthManager(io, connectionManager);

    // Set up admin namespace
    adminNamespace = io.of('/admin');
    namespaces.admin = adminNamespace;

    return { app, server, io };
};

// Modify startServer to use config directly
const startServer = async () => {
    try {
        logger.info('Starting server initialization...', {
            nodeVersion: process.version,
            platform: process.platform,
            pid: process.pid
        });

        // Initialize server components
        const { app, server, io } = await initServer();

        // Start server
        return new Promise((resolve, reject) => {
            const serverStartTimeout = setTimeout(() => {
                const error = new Error('Server start timeout after 10 seconds');
                logger.error(error.message);
                reject(error);
            }, 10000);

            try {
                server.listen(config.port, config.host, () => {
                    clearTimeout(serverStartTimeout);
                    const protocol = config.environment.ssl_enabled ? 'wss' : 'ws';
                    logger.info(`Server listening on ${protocol}://${config.host}:${config.port}`);
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
        await fsPromises.writeFile(statsFile, JSON.stringify(stats, null, 2));

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
async function cleanup(reason = 'normal') {
    if (serverShutdownInitiated) {
        logger.warn('Cleanup already in progress, skipping...');
        return { success: true, message: 'Cleanup already in progress' };
    }

    serverShutdownInitiated = true;
    shutdownReason = reason;
    serverState.isRunning = false;

    logger.info(`Starting cleanup... (Reason: ${reason})`);
    const errors = [];

    try {
        // Close namespace connections gracefully
        for (const [name, namespace] of Object.entries(namespaces)) {
            try {
                const sockets = await namespace.fetchSockets();
                logger.info(`Closing ${sockets.length} connections in /${name} namespace`);

                await Promise.all(sockets.map(socket =>
                    new Promise((resolve) => {
                        socket.emit('server_shutdown', { reason });
                        socket.disconnect(true);
                        resolve();
                    })
                ));
            } catch (error) {
                errors.push(`Error closing namespace ${name}: ${error.message}`);
                logger.error(`Error closing namespace ${name}:`, error);
            }
        }

        // Close Redis connection if active
        if (redisClient) {
            try {
                await redisClient.quit();
                logger.info('Redis connection closed');
            } catch (error) {
                errors.push(`Error closing Redis: ${error.message}`);
                logger.error('Error closing Redis:', error);
            }
        }

        // Save final stats
        try {
            const finalStats = {
                ...stats,
                shutdown_time: Date.now(),
                shutdown_reason: reason
            };
            await fsPromises.writeFile(statsFile, JSON.stringify(finalStats, null, 2));
            logger.info('Final stats saved');
        } catch (error) {
            errors.push(`Error saving final stats: ${error.message}`);
            logger.error('Error saving final stats:', error);
        }

        // Remove PID file
        try {
            if (await fsPromises.access(pidFile).then(() => true).catch(() => false)) {
                await fsPromises.unlink(pidFile);
                logger.info('PID file removed');
            }
        } catch (error) {
            errors.push(`Error removing PID file: ${error.message}`);
            logger.error('Error removing PID file:', error);
        }

        // Close server if still listening
        if (server && server.listening) {
            await new Promise((resolve) => {
                server.close(() => {
                    logger.info('Server closed');
                    resolve();
                });
            });
        }

        logger.info('Cleanup completed' + (errors.length ? ' with errors' : ' successfully'));
        return {
            success: errors.length === 0,
            errors: errors.length ? errors : undefined
        };
    } catch (error) {
        logger.error('Fatal error during cleanup:', error);
        return {
            success: false,
            errors: [...errors, `Fatal error: ${error.message}`]
        };
    }
}

// Logging helper
function logEvent(event, data) {
    const logEntry = {
        timestamp: new Date().toISOString(),
        event,
        data
    };

    fs.appendFileSync(logFile, JSON.stringify(logEntry) + '\n');
}

// Update status file writing for admin UI
async function updateAdminStatus(status) {
    try {
        const processStatus = await verifyProcess(processInfo.pid);
        await fsPromises.writeFile(
            path.join(path.dirname(statsFile),
                'status.json'),
            JSON.stringify({
                running: processStatus.running && processStatus.isOurs,
                pid: processInfo.pid,
                nodePath: processStatus.nodePath || processInfo.nodePath,
                scriptPath: processStatus.scriptPath || processInfo.scriptPath,
                connections: io?.engine?.clientsCount || 0,
                uptime: process.uptime(),
                ...status
            }, null, 2)
        );
    } catch (error) {
        logger.error('Error updating admin status:', error);
    }
}

// Update stats file writing for admin UI
function updateAdminStats() {
    fs.writeFileSync(statsFile, JSON.stringify({
        timestamp: Date.now(),
        connections: io?.engine?.clientsCount || 0,
        rooms: Array.from(io?.sockets?.adapter?.rooms?.keys() || []),
        uptime: process.uptime(),
        memory: process.memoryUsage()
    }, null, 2));
}

// Update stats periodically for admin UI
setInterval(() => {
    updateAdminStats();
}, 5000); // Every 5 seconds

// Function to update stats file
function updateStats(newStats) {
    stats = { ...stats, ...newStats, timestamp: Date.now() };
    try {
        fs.writeFileSync(statsFile, JSON.stringify(stats, null, 2));
    } catch (err) {
        console.error('Error writing stats file:', err);
        fs.appendFileSync(logFile, `Stats File Error: ${err}\n`);
    }
}

// Enhanced environment logging
console.log('Environment variables:', {
    WP_PORT: process.env.WP_PORT,
    NODE_ENV: process.env.NODE_ENV,
    WP_DEBUG: process.env.WP_DEBUG
});

// Add startup error handling
process.on('uncaughtException', (error) => {
    console.error('Uncaught Exception:', error);
    fs.appendFileSync(logFile, `Uncaught Exception: ${error}\n${error.stack}\n`);
    process.exit(1);
});

process.on('unhandledRejection', (reason, promise) => {
    console.error('Unhandled Rejection at:', promise, 'reason:', reason);
    fs.appendFileSync(logFile, `Unhandled Rejection: ${reason}\n`);
    process.exit(1);
});

// Log server startup phases
console.log('Starting WebSocket server initialization...');

// After port is declared, add port configuration logging
console.log(`Using port: ${port} (from ${process.env.WP_PORT ? 'environment' : 'default'})`);

// Add port validation
if (port < 1024 || port > 65535) {
    const error = `Invalid port number: ${port}. Must be between 1024 and 65535.`;
    console.error(error);
    fsPromises.appendFileSync(logFile, `${error}\n`);
    process.exit(1);
}

// Add health check endpoint
app.get('/health', (req, res) => {
    try {
        const health = {
            status: serverState.isRunning ? 'healthy' : 'unhealthy',
            uptime: serverState.startTime ? (Date.now() - serverState.startTime) / 1000 : 0,
            timestamp: Date.now(),
            pid: process.pid,
            memory: process.memoryUsage(),
            connections: io?.engine?.clientsCount || 0,
            errors: serverState.errors.slice(-5), // Last 5 errors
            lastHealthCheck: serverState.lastHealthCheck
        };

        serverState.lastHealthCheck = Date.now();

        if (!serverState.isRunning) {
            health.shutdownReason = shutdownReason;
        }

        res.json(health);
    } catch (error) {
        logger.error('Health check error:', error);
        res.status(500).json({
            status: 'error',
            error: error.message
        });
    }
});

// Add server monitoring
function startServerMonitoring() {
    // Monitor server health every 5 seconds
    const healthInterval = setInterval(() => {
        if (!serverState.isRunning) {
            logger.warn('Server state marked as not running');
            return;
        }

        try {
            const memUsage = process.memoryUsage();
            if (memUsage.heapUsed > 500 * 1024 * 1024) { // 500MB
                logger.warn('High memory usage detected', memUsage);
            }

            // Check if server is still listening
            if (!server.listening) {
                logger.error('Server is no longer listening');
                serverState.errors.push({
                    time: Date.now(),
                    type: 'server_not_listening'
                });
                cleanup('server_not_listening').catch(console.error);
            }
        } catch (error) {
            logger.error('Error in health monitoring:', error);
            serverState.errors.push({
                time: Date.now(),
                type: 'monitoring_error',
                error: error.message
            });
        }
    }, 5000);

    // Clean up old errors every hour
    const cleanupInterval = setInterval(() => {
        const oneHourAgo = Date.now() - (60 * 60 * 1000);
        serverState.errors = serverState.errors.filter(error => error.time > oneHourAgo);
    }, 60 * 60 * 1000);

    // Cleanup intervals on server shutdown
    process.on('SIGTERM', () => {
        clearInterval(healthInterval);
        clearInterval(cleanupInterval);
    });
}