<?php
/**
 * Plugin Name:     Startempire Wire Network Websockets - This plugin provides websocket support for the Startempire Wire Network. WordPress-based, server-agnostic, able integrate with any server. Handles real-time features like chat, notifications, and data syncing.
 * Plugin URI:      https://startempirewire.network/
 * Description:     Plugin to provide websocket support for the Startempire Wire Network. WordPress-based, server-agnostic, able integrate with any server. Handles real-time features like chat, notifications, and data syncing.
 * Author:          Startempire Wire
 * Author URI:      https://startempirewire.com/
 * Text Domain:     sewn-ws
 * Domain Path:     /languages
 * Version:         0.1.0
 * Namespace:       SEWN
 *
 * @package         Startempire_Wire_Network_Websockets
 */

defined('ABSPATH') || exit;

// Define constants
define('SEWN_WS_PATH', plugin_dir_path(__FILE__));
define('SEWN_WS_NODE_SERVER', SEWN_WS_PATH . 'node-server/');

// Load core components
require_once SEWN_WS_PATH . 'includes/class-process-manager.php';
require_once SEWN_WS_PATH . 'includes/class-dashboard.php';
require_once SEWN_WS_PATH . 'includes/class-rest-api.php';

// Activation hooks
register_activation_hook(__FILE__, ['SEWN_Process_Manager', 'activate']);
register_deactivation_hook(__FILE__, ['SEWN_Process_Manager', 'deactivate']);

// Your code starts here.
