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
module.exports = {
    PORT: process.env.SEWN_WS_PORT || 49200,
    HOST: process.env.SEWN_WS_HOST || 'localhost',
    JWT_SECRET: process.env.SEWN_WS_JWT_SECRET
};
