/**
 * LOCATION: node-server/config.js
 * DEPENDENCIES: path module
 * VARIABLES: config.tls.certPath
 * CLASSES: None (configuration export)
 * 
 * Central configuration manager for Node.js server parameters. Maintains TLS certificate
 * paths and port settings synchronized with WordPress admin configurations.
 */
module.exports = {
    PORT: process.env.SEWN_WS_PORT || 8080,
    HOST: process.env.SEWN_WS_HOST || 'localhost',
    JWT_SECRET: process.env.SEWN_WS_JWT_SECRET
};
