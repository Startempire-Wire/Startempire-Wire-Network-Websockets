/**
 * LOCATION: node-server/config.js
 * DEPENDENCIES: WordPress constants from constants.php
 * 
 * Central configuration manager for Node.js server parameters.
 * Uses WordPress defined constants from wp-constants.json
 */

const path = require('path');
const fs = require('fs');

// Import WordPress constants via generated JSON
let WP_CONSTANTS = {};
const constantsPath = path.join(__dirname, 'wp-constants.json');

try {
    // Check if file exists first
    if (!fs.existsSync(constantsPath)) {
        throw new Error('wp-constants.json not found. Please ensure the WordPress plugin is properly initialized.');
    }

    // Read and parse the file
    const rawData = fs.readFileSync(constantsPath, 'utf8');
    WP_CONSTANTS = JSON.parse(rawData);

    // Validate required constants
    const requiredConstants = [
        'SEWN_WS_DEFAULT_PORT',
        'SEWN_WS_SERVER_CONTROL_PATH',
        'SEWN_WS_SERVER_LOG_FILE',
        'SEWN_WS_NODE_SERVER'
    ];

    const missingConstants = requiredConstants.filter(constant => !(constant in WP_CONSTANTS));
    if (missingConstants.length > 0) {
        throw new Error(`Missing required constants: ${missingConstants.join(', ')}`);
    }

} catch (err) {
    console.error('Configuration Error:', err.message);
    console.error('Stack trace:', err.stack);
    console.log('Using default values and continuing with limited functionality');
}

// Get environment configuration with enhanced validation
function getEnvironmentConfig() {
    const config = {
        debug: WP_CONSTANTS.SEWN_WS_ENV_DEBUG_ENABLED || false,
        isLocal: WP_CONSTANTS.SEWN_WS_IS_LOCAL || false,
        isContainer: WP_CONSTANTS.SEWN_WS_ENV_CONTAINER_MODE || false,
        ssl: {
            enabled: WP_CONSTANTS.SEWN_WS_SSL_ENABLED || false,
            cert: WP_CONSTANTS.SEWN_WS_SSL_CERT || '',
            key: WP_CONSTANTS.SEWN_WS_SSL_KEY || ''
        }
    };

    // Validate SSL configuration
    if (config.ssl.enabled && (!config.ssl.cert || !config.ssl.key)) {
        console.warn('SSL is enabled but certificate or key path is missing. SSL will be disabled.');
        config.ssl.enabled = false;
    }

    return config;
}

const config = {
    // Core server settings with validation
    port: (() => {
        const port = parseInt(process.env.WP_PORT) || WP_CONSTANTS.SEWN_WS_DEFAULT_PORT || 49200;
        if (port < 1024 || port > 65535) {
            console.warn(`Invalid port ${port}, using default 49200`);
            return 49200;
        }
        return port;
    })(),
    host: process.env.WP_HOST || '0.0.0.0',

    // File paths with validation
    paths: {
        stats: (() => {
            const statsPath = process.env.WP_STATS_FILE ||
                path.join(WP_CONSTANTS.SEWN_WS_SERVER_CONTROL_PATH || './tmp', 'stats.json');
            try {
                fs.mkdirSync(path.dirname(statsPath), { recursive: true });
            } catch (err) {
                console.warn(`Failed to create stats directory: ${err.message}`);
            }
            return statsPath;
        })(),
        pid: (() => {
            const pidPath = process.env.WP_PID_FILE || WP_CONSTANTS.SEWN_WS_SERVER_PID_FILE || './tmp/server.pid';
            try {
                fs.mkdirSync(path.dirname(pidPath), { recursive: true });
            } catch (err) {
                console.warn(`Failed to create PID directory: ${err.message}`);
            }
            return pidPath;
        })(),
        logs: (() => {
            const logsPath = process.env.WP_LOG_FILE || WP_CONSTANTS.SEWN_WS_SERVER_LOG_FILE || './logs/server.log';
            try {
                fs.mkdirSync(path.dirname(logsPath), { recursive: true });
            } catch (err) {
                console.warn(`Failed to create logs directory: ${err.message}`);
            }
            return logsPath;
        })(),
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

    // Stats configuration with validation
    stats: {
        updateInterval: (() => {
            const interval = WP_CONSTANTS.SEWN_WS_STATS_UPDATE_INTERVAL || 10000;
            return interval < 1000 ? 10000 : interval; // Ensure minimum 1 second
        })(),
        maxHistoryPoints: (() => {
            const points = WP_CONSTANTS.SEWN_WS_HISTORY_MAX_POINTS || 100;
            return points < 10 ? 100 : points; // Ensure minimum 10 points
        })()
    },

    // Optional services
    services: {
        redis: {
            url: process.env.REDIS_URL || null
        }
    }
};

// Create necessary directories
Object.values(config.paths).forEach(path => {
    if (typeof path === 'string' && !path.endsWith('.json') && !path.endsWith('.pid') && !path.endsWith('.log')) {
        try {
            fs.mkdirSync(path, { recursive: true });
        } catch (err) {
            console.warn(`Failed to create directory ${path}: ${err.message}`);
        }
    }
});

module.exports = config;
