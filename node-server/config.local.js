/**
 * Local development configuration
 * This file overrides the default config.js settings for local development
 */

const baseConfig = require('./config');
const fs = require('fs');

// Helper to safely check file existence
const fileExists = (path) => {
    try {
        return fs.existsSync(path);
    } catch (e) {
        return false;
    }
};

// Detect environment type
const getEnvironmentType = () => {
    if (process.env.NODE_ENV) {
        return process.env.NODE_ENV;
    }
    // Default to development if not specified
    return 'development';
};

// Get SSL configuration based on environment
const getSSLConfig = () => {
    const sslConfig = {
        enabled: process.env.SEWN_WS_SSL_ENABLED === 'true',
        key: process.env.SEWN_WS_SSL_KEY || '',
        cert: process.env.SEWN_WS_SSL_CERT || ''
    };

    // Validate SSL configuration if enabled
    if (sslConfig.enabled) {
        if (!sslConfig.key || !sslConfig.cert) {
            console.warn('SSL enabled but certificates not configured. Falling back to non-SSL mode.');
            sslConfig.enabled = false;
        } else if (!fileExists(sslConfig.key) || !fileExists(sslConfig.cert)) {
            console.warn('SSL certificates not found. Falling back to non-SSL mode.');
            sslConfig.enabled = false;
        }
    }

    return sslConfig;
};

// Override environment config
baseConfig.environment = {
    ...baseConfig.environment,
    type: getEnvironmentType(),
    debug: process.env.SEWN_WS_ENV_DEBUG_ENABLED === 'true',
    isLocal: process.env.SEWN_WS_IS_LOCAL === 'true',
    ssl: getSSLConfig()
};

// Configure CORS based on environment
baseConfig.cors = {
    // Allow configuration via environment variables
    origin: process.env.SEWN_WS_CORS_ORIGINS ?
        process.env.SEWN_WS_CORS_ORIGINS.split(',') :
        ['*'],
    methods: ['GET', 'POST'],
    credentials: true
};

// Export modified config
module.exports = baseConfig; 