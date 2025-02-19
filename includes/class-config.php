<?php
/**
 * Configuration Management Class
 *
 * Handles all configuration management for the WebSocket server including
 * environment settings, server configuration, and runtime options.
 *
 * @package           Startempire_Wire_Network_Websockets
 * @subpackage        Includes
 * @since             1.0.0
 */

namespace SEWN\WebSockets;

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Configuration management class
 */
class Config {
    /**
     * Default configuration values
     *
     * @var array
     */
    private static $defaults = [
        'port' => SEWN_WS_DEFAULT_PORT,
        'ssl_enabled' => false,
        'debug' => false,
        'server_status' => SEWN_WS_SERVER_STATUS_UNINITIALIZED,
        'connection_timeout' => 45000,
        'reconnection_attempts' => 3,
        'stats_update_interval' => SEWN_WS_STATS_UPDATE_INTERVAL,
        'history_max_points' => SEWN_WS_HISTORY_MAX_POINTS
    ];

    /**
     * Get a configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed Configuration value
     */
    public static function get($key, $default = null) {
        $option_name = 'sewn_ws_' . $key;
        $value = get_option($option_name, null);
        
        if ($value === null) {
            return $default ?? self::$defaults[$key] ?? null;
        }
        
        return $value;
    }

    /**
     * Set a configuration value
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return bool Whether the option was updated
     */
    public static function set($key, $value) {
        $option_name = 'sewn_ws_' . $key;
        return update_option($option_name, $value);
    }

    /**
     * Get all configuration values
     *
     * @return array All configuration values
     */
    public static function get_all() {
        $config = [];
        foreach (self::$defaults as $key => $default) {
            $config[$key] = self::get($key);
        }
        return $config;
    }

    /**
     * Get environment-specific configuration
     *
     * @return array Environment configuration
     */
    public static function get_environment_config() {
        $is_local = defined('SEWN_WS_IS_LOCAL') && SEWN_WS_IS_LOCAL;
        $is_container = defined('SEWN_WS_ENV_CONTAINER_MODE') && SEWN_WS_ENV_CONTAINER_MODE;
        
        return [
            'is_local' => $is_local,
            'is_container' => $is_container,
            'debug_enabled' => self::get('debug', false),
            'ssl_enabled' => self::get('ssl_enabled', false),
            'ssl_cert_path' => self::get('ssl_cert_path', ''),
            'ssl_key_path' => self::get('ssl_key_path', '')
        ];
    }

    /**
     * Get server configuration
     *
     * @return array Server configuration
     */
    public static function get_server_config() {
        return [
            'port' => self::get('port', SEWN_WS_DEFAULT_PORT),
            'status' => self::get('server_status', SEWN_WS_SERVER_STATUS_UNINITIALIZED),
            'connection_timeout' => self::get('connection_timeout', 45000),
            'reconnection_attempts' => self::get('reconnection_attempts', 3),
            'stats_update_interval' => self::get('stats_update_interval', SEWN_WS_STATS_UPDATE_INTERVAL),
            'history_max_points' => self::get('history_max_points', SEWN_WS_HISTORY_MAX_POINTS)
        ];
    }

    /**
     * Initialize default configuration
     *
     * @return void
     */
    public static function init() {
        foreach (self::$defaults as $key => $value) {
            if (get_option('sewn_ws_' . $key) === false) {
                self::set($key, $value);
            }
        }
    }
}

// Initialize configuration on plugin load
add_action('plugins_loaded', ['SEWN\\WebSockets\\Config', 'init']); 