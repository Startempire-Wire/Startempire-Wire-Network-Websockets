/**
 * Local development configuration
 * This file overrides the default config.js settings for local development
 */

const baseConfig = require('./config');

// Override environment config for local development
baseConfig.environment = {
    ...baseConfig.environment,
    debug: true,
    isLocal: true,
    ssl: {
        enabled: false // Disable SSL for local development
    }
};

// Add CORS settings for local development
baseConfig.cors = {
    origin: true, // Allow all origins in development
    methods: ['GET', 'POST'],
    credentials: true
};

// Export modified config
module.exports = baseConfig; 