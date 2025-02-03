<?php

namespace SEWN\WebSockets;


// Text domains and internationalization
defined('SEWN_WS_TEXT_DOMAIN') || define('SEWN_WS_TEXT_DOMAIN', 'sewn-ws');
defined('SEWN_WS_ADMIN_MENU_SLUG') || define('SEWN_WS_ADMIN_MENU_SLUG', 'sewn-ws');

// Options and transients
defined('SEWN_WS_OPTION_SERVER_STATUS') || define('SEWN_WS_OPTION_SERVER_STATUS', 'sewn_ws_server_status');
defined('SEWN_WS_OPTION_PORT') || define('SEWN_WS_OPTION_PORT', 'sewn_ws_port');
defined('SEWN_WS_TRANSIENT_CONNECTIONS') || define('SEWN_WS_TRANSIENT_CONNECTIONS', 'sewn_ws_connections');

// Nonce and security
defined('SEWN_WS_NONCE_ACTION') || define('SEWN_WS_NONCE_ACTION', 'sewn_ws_nonce');

// Script handles
defined('SEWN_WS_SCRIPT_HANDLE_ADMIN') || define('SEWN_WS_SCRIPT_HANDLE_ADMIN', 'sewn-ws-admin');

// REST API
defined('SEWN_WS_REST_NAMESPACE') || define('SEWN_WS_REST_NAMESPACE', 'sewn-ws/v1');

// Server defaults
defined('SEWN_WS_DEFAULT_PORT') || define('SEWN_WS_DEFAULT_PORT', 8080);
defined('SEWN_WS_SETTINGS_GROUP') || define('SEWN_WS_SETTINGS_GROUP', 'sewn_ws_settings');

// Module system
defined('SEWN_WS_MODULE_SETTINGS_PREFIX') || define('SEWN_WS_MODULE_SETTINGS_PREFIX', 'sewn_ws_module_%s_settings'); 