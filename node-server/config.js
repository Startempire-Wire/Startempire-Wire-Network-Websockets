/**
 * LOCATION: node-server/config.js
 * DEPENDENCIES: path module
 * VARIABLES: config.tls.certPath
 * CLASSES: None (configuration export)
 * 
 * Central configuration manager for Node.js server parameters. Maintains TLS certificate
 * paths and port settings synchronized with WordPress admin configurations.
 * 
 * Default port is 49200 because:
 * - Falls within IANA Dynamic/Private port range (49152-65535)
 * - Avoids conflicts with common development ports
 * - Consistent with WordPress plugin settings
 */

const dotenv = require('dotenv');
const path = require('path');

// Load environment variables
dotenv.config();

const config = {
    // Server will always run on 49200 internally
    port: parseInt(process.env.WP_PORT || '49200', 10),

    // Host defaults to localhost
    host: process.env.WP_HOST || 'localhost',

    // SSL configuration for Local environment
    ssl: {
        enabled: process.env.WP_SSL === 'true',
        cert: process.env.WP_SSL_CERT || null,
        key: process.env.WP_SSL_KEY || null
    },

    // Proxy configuration (for client connections)
    proxy: {
        enabled: true,
        path: process.env.WP_PROXY_PATH || '/websocket'
    },

    JWT_SECRET: process.env.WP_JWT_SECRET,
    DEBUG: process.env.WP_DEBUG === 'true',
    LOG_FILE: process.env.WP_LOG_FILE,
    STATS_FILE: process.env.WP_STATS_FILE,
    PID_FILE: process.env.WP_PID_FILE
};

// Validate port number
if (config.port < 1024 || config.port > 65535) {
    console.error('Invalid port number. Using default port 49200');
    config.port = 49200;
}

module.exports = config;
