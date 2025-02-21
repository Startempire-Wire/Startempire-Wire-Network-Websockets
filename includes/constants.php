<?php
/**
 * Location: includes/constants.php
 * Dependencies: WordPress core, plugin bootstrap
 * Variables/Classes: SEWN_WS_PATH, SEWN_WS_URL, SEWN_WS_NS
 * Purpose: Centralizes plugin-wide constant definitions and configuration values. Ensures consistent access to critical paths and settings across all plugin components.
 */

// namespace SEWN\WebSockets; <-- COMMENT THIS OUT

defined('ABSPATH') || exit;

// Define constants
define('SEWN_WS_PATH', plugin_dir_path(__FILE__));
define('SEWN_WS_URL', plugin_dir_url(__FILE__));
define('SEWN_WS_NS', __NAMESPACE__);
define('SEWN_WS_NODE_SERVER', SEWN_WS_PATH . 'node-server' . DIRECTORY_SEPARATOR);

// Text domains and internationalization
!defined('SEWN_WS_TEXT_DOMAIN') && define('SEWN_WS_TEXT_DOMAIN', 'sewn-ws');
!defined('SEWN_WS_ADMIN_MENU_SLUG') && define('SEWN_WS_ADMIN_MENU_SLUG', 'sewn-ws');

// Options and transients
!defined('SEWN_WS_OPTION_SERVER_STATUS') && define('SEWN_WS_OPTION_SERVER_STATUS', 'sewn_ws_server_status');
!defined('SEWN_WS_OPTION_PORT') && define('SEWN_WS_OPTION_PORT', 'sewn_ws_port');
!defined('SEWN_WS_TRANSIENT_CONNECTIONS') && define('SEWN_WS_TRANSIENT_CONNECTIONS', 'sewn_ws_connections');

// Nonce and security
!defined('SEWN_WS_NONCE_ACTION') && define('SEWN_WS_NONCE_ACTION', 'sewn_ws_nonce');

// Script handles
!defined('SEWN_WS_SCRIPT_HANDLE_ADMIN') && define('SEWN_WS_SCRIPT_HANDLE_ADMIN', 'sewn-ws-admin');

// REST API
!defined('SEWN_WS_REST_NAMESPACE') && define('SEWN_WS_REST_NAMESPACE', 'sewn-ws/v1');

// Server defaults
/**
 * Default WebSocket port - Using 49200 because:
 * 1. Falls within IANA Dynamic/Private port range (49152-65535)
 * 2. Early in dynamic range for easy remembering
 * 3. Avoids common development ports (3000, 8080, etc)
 * 4. Reduces likelihood of conflicts with other services
 */
!defined('SEWN_WS_DEFAULT_PORT') && define('SEWN_WS_DEFAULT_PORT', 49200);

/**
 * WebSocket proxy configuration
 * While server runs on SEWN_WS_DEFAULT_PORT (49200),
 * clients connect through standard HTTPS port (443) via proxy
 */
!defined('SEWN_WS_PROXY_PATH') && define('SEWN_WS_PROXY_PATH', '/websocket');

/**
 * @deprecated 1.1.0 Use SEWN_WS_DEFAULT_PORT instead.
 * This constant will be removed in version 2.0.0
 */
!defined('SEWN_WS_ENV_DEFAULT_PORT') && define('SEWN_WS_ENV_DEFAULT_PORT', SEWN_WS_DEFAULT_PORT);

// Add deprecation notice
if (defined('SEWN_WS_ENV_DEFAULT_PORT') && !(defined('DOING_AJAX') && DOING_AJAX)) {
    trigger_error(
        'SEWN_WS_ENV_DEFAULT_PORT is deprecated and will be removed in version 2.0.0. Use SEWN_WS_DEFAULT_PORT instead.',
        E_USER_DEPRECATED
    );
}

!defined('SEWN_WS_SETTINGS_GROUP') && define('SEWN_WS_SETTINGS_GROUP', 'sewn_ws_settings');

// Module system
!defined('SEWN_WS_MODULE_SETTINGS_PREFIX') && define('SEWN_WS_MODULE_SETTINGS_PREFIX', 'sewn_ws_module_%s_settings');

// Add version constant
!defined('SEWN_WS_VERSION') && define('SEWN_WS_VERSION', '1.0.0');

// WebSocket Server Constants
!defined('SEWN_WS_SERVER_STATUS_RUNNING') && define('SEWN_WS_SERVER_STATUS_RUNNING', 'running');
!defined('SEWN_WS_SERVER_STATUS_STOPPED') && define('SEWN_WS_SERVER_STATUS_STOPPED', 'stopped');
!defined('SEWN_WS_SERVER_STATUS_ERROR') && define('SEWN_WS_SERVER_STATUS_ERROR', 'error');
!defined('SEWN_WS_SERVER_STATUS_UNINITIALIZED') && define('SEWN_WS_SERVER_STATUS_UNINITIALIZED', 'uninitialized');

// Server Control Constants
!defined('SEWN_WS_SERVER_CONTROL_PATH') && define('SEWN_WS_SERVER_CONTROL_PATH', SEWN_WS_PATH . 'tmp/');
!defined('SEWN_WS_SERVER_PID_FILE') && define('SEWN_WS_SERVER_PID_FILE', SEWN_WS_SERVER_CONTROL_PATH . 'server.pid');
!defined('SEWN_WS_SERVER_LOG_FILE') && define('SEWN_WS_SERVER_LOG_FILE', SEWN_WS_PATH . 'logs/server.log');
!defined('SEWN_WS_SERVER_CONFIG_FILE') && define('SEWN_WS_SERVER_CONFIG_FILE', SEWN_WS_NODE_SERVER . 'config.json');
!defined('SEWN_WS_SERVER_STATUS_CHECK_ACTION') && define('SEWN_WS_SERVER_STATUS_CHECK_ACTION', 'sewn_ws_check_server_status');

// WebSocket Stats Constants
!defined('SEWN_WS_STATS_UPDATE_INTERVAL') && define('SEWN_WS_STATS_UPDATE_INTERVAL', 10000);
!defined('SEWN_WS_STATS_MAX_POINTS') && define('SEWN_WS_STATS_MAX_POINTS', 20);

// Development mode detection
define('SEWN_WS_IS_LOCAL', (
    strpos($_SERVER['HTTP_HOST'], '.local') !== false || 
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false
));

// Update ENV_OVERRIDABLE constants
!defined('SEWN_WS_ENV_OVERRIDABLE') && define('SEWN_WS_ENV_OVERRIDABLE', [
    'SEWN_WS_ENV_LOCAL_MODE' => false,
    'SEWN_WS_ENV_CONTAINER_MODE' => false,
    'SEWN_WS_ENV_DEBUG_ENABLED' => false,
    'SEWN_WS_ENV_SSL_CERT_PATH' => '',
    'SEWN_WS_ENV_SSL_KEY_PATH' => ''
]);

// Add new constant for environment info storage
!defined('SEWN_WS_ENV_INFO_OPTION') && define('SEWN_WS_ENV_INFO_OPTION', 'sewn_ws_env_info');

// Define environment mode constants only once
!defined('SEWN_WS_ENV_LOCAL_MODE') && define('SEWN_WS_ENV_LOCAL_MODE', false);
!defined('SEWN_WS_ENV_CONTAINER_MODE') && define('SEWN_WS_ENV_CONTAINER_MODE', false);

// History and Stats Constants
!defined('SEWN_WS_HISTORY_MAX_POINTS') && define('SEWN_WS_HISTORY_MAX_POINTS', 100);
!defined('SEWN_WS_HISTORY_RETENTION_DAYS') && define('SEWN_WS_HISTORY_RETENTION_DAYS', 7);
!defined('SEWN_WS_SERVER_HISTORY_OPTION') && define('SEWN_WS_SERVER_HISTORY_OPTION', 'sewn_ws_server_history');
!defined('SEWN_WS_STATS_HISTORY_OPTION') && define('SEWN_WS_STATS_HISTORY_OPTION', 'sewn_ws_stats_history');
!defined('SEWN_WS_LAST_STATS_OPTION') && define('SEWN_WS_LAST_STATS_OPTION', 'sewn_ws_last_stats');

// Configuration System Constants
!defined('SEWN_WS_CONFIG_VERSION') && define('SEWN_WS_CONFIG_VERSION', '1.0.0');
!defined('SEWN_WS_CONFIG_INITIALIZED') && define('SEWN_WS_CONFIG_INITIALIZED', false);

// Development and Environment Settings
!defined('SEWN_WS_OPTION_DEV_MODE') && define('SEWN_WS_OPTION_DEV_MODE', 'sewn_ws_dev_mode');
!defined('SEWN_WS_OPTION_RATE_LIMIT') && define('SEWN_WS_OPTION_RATE_LIMIT', 'sewn_ws_rate_limit');
!defined('SEWN_WS_OPTION_ENVIRONMENT') && define('SEWN_WS_OPTION_ENVIRONMENT', 'sewn_ws_environment');
!defined('SEWN_WS_OPTION_LOCAL_SITE_URL') && define('SEWN_WS_OPTION_LOCAL_SITE_URL', 'sewn_ws_local_site_url');

// SSL Configuration Options
!defined('SEWN_WS_OPTION_SSL_CERT') && define('SEWN_WS_OPTION_SSL_CERT', 'sewn_ws_ssl_cert');
!defined('SEWN_WS_OPTION_SSL_KEY') && define('SEWN_WS_OPTION_SSL_KEY', 'sewn_ws_ssl_key');

// End of file 