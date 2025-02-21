/**
 * LOCATION: node-server/config.js
 * DEPENDENCIES: WordPress constants from constants.php
 * 
 * Central configuration manager for Node.js server parameters.
 * Uses WordPress defined constants from wp-constants.json
 */

const path = require('path');

// Import WordPress constants via generated JSON
let WP_CONSTANTS = {};
try {
    WP_CONSTANTS = require('./wp-constants.json');
} catch (err) {
    console.error('Failed to load wp-constants.json:', err.message);
    console.log('Using default values');
}

// Get environment configuration
function getEnvironmentConfig() {
    return {
        debug: WP_CONSTANTS.SEWN_WS_ENV_DEBUG_ENABLED || false,
        isLocal: WP_CONSTANTS.SEWN_WS_IS_LOCAL || false,
        isContainer: WP_CONSTANTS.SEWN_WS_ENV_CONTAINER_MODE || false,
        ssl: {
            enabled: WP_CONSTANTS.SEWN_WS_SSL_ENABLED || false,
            cert: WP_CONSTANTS.SEWN_WS_SSL_CERT || '',
            key: WP_CONSTANTS.SEWN_WS_SSL_KEY || ''
        }
    };
}

const config = {
    // Core server settings
    port: parseInt(process.env.WP_PORT) || WP_CONSTANTS.SEWN_WS_DEFAULT_PORT || 49200,
    host: process.env.WP_HOST || '0.0.0.0',

    // File paths from constants
    paths: {
        stats: process.env.WP_STATS_FILE || WP_CONSTANTS.SEWN_WS_SERVER_CONTROL_PATH + '/stats.json',
        pid: process.env.WP_PID_FILE || WP_CONSTANTS.SEWN_WS_SERVER_PID_FILE,
        logs: process.env.WP_LOG_FILE || WP_CONSTANTS.SEWN_WS_SERVER_LOG_FILE,
        server: WP_CONSTANTS.SEWN_WS_NODE_SERVER || __dirname
    },

    // Environment settings
    environment: getEnvironmentConfig(),

    // Server status constants
    status: {
        running: WP_CONSTANTS.SEWN_WS_SERVER_STATUS_RUNNING || 'running',
        stopped: WP_CONSTANTS.SEWN_WS_SERVER_STATUS_STOPPED || 'stopped',
        error: WP_CONSTANTS.SEWN_WS_SERVER_STATUS_ERROR || 'error',
        uninitialized: WP_CONSTANTS.SEWN_WS_SERVER_STATUS_UNINITIALIZED || 'uninitialized'
    },

    // Stats configuration
    stats: {
        updateInterval: WP_CONSTANTS.SEWN_WS_STATS_UPDATE_INTERVAL || 10000,
        maxHistoryPoints: WP_CONSTANTS.SEWN_WS_HISTORY_MAX_POINTS || 100
    },

    // Optional services
    services: {
        redis: {
            url: process.env.REDIS_URL || null
        }
    }
};

// Validate port number
if (config.port < 1024 || config.port > 65535) {
    console.error('Invalid port number. Using SEWN_WS_DEFAULT_PORT: 49200');
    config.port = WP_CONSTANTS.SEWN_WS_DEFAULT_PORT || 49200;
}

// Ensure all paths are absolute
config.paths = Object.keys(config.paths).reduce((acc, key) => {
    if (config.paths[key] && typeof config.paths[key] === 'string') {
        acc[key] = path.resolve(__dirname, config.paths[key]);
    } else {
        acc[key] = config.paths[key];
    }
    return acc;
}, {});

module.exports = config;
