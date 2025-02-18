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
!defined('SEWN_WS_DEFAULT_PORT') && define('SEWN_WS_DEFAULT_PORT', 8080);
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
    'SEWN_WS_ENV_DEFAULT_PORT' => 8080,
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

// End of file 