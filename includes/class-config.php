<?php
/**
 * Configuration Management Class
 *
 * Provides centralized configuration management for the WebSocket server including
 * core settings, module settings, environment configuration, and server settings.
 *
 * @package           Startempire_Wire_Network_Websockets
 * @subpackage        Includes
 * @since             1.0.0
 */

namespace SEWN\WebSockets;

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Configuration management class with backwards compatibility
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
     * Legacy option name mapping
     *
     * @var array
     */
    private static $legacy_map = [
        'discord' => [
            'bot_token' => 'sewn_ws_discord_bot_token',
            'guild_id' => 'sewn_ws_discord_guild_id',
            'webhook_url' => 'sewn_ws_discord_webhook',
            'streaming_enabled' => 'sewn_ws_discord_streaming',
            'role_sync_enabled' => 'sewn_ws_discord_role_sync',
            'notification_channel' => 'sewn_ws_discord_notification_channel'
        ]
        // Add mappings for other modules as needed
    ];

    /**
     * Get a configuration value with backwards compatibility
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed Configuration value
     */
    public static function get($key, $default = null) {
        // Try new format first
        $option_name = 'sewn_ws_' . $key;
        $value = get_option($option_name, null);
        
        if ($value !== null) {
            return $value;
        }
        
        // Check if this is a legacy option
        $legacy_value = self::get_legacy_option($key);
        if ($legacy_value !== null) {
            // Migrate to new format if found in legacy
            self::set($key, $legacy_value);
            return $legacy_value;
        }
        
        return $default ?? self::$defaults[$key] ?? null;
    }

    /**
     * Get module-specific setting with backwards compatibility
     *
     * @param string $module Module slug
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public static function get_module_setting($module, $key, $default = null) {
        // Try new format first
        $value = get_option("sewn_ws_module_{$module}_{$key}", null);
        
        if ($value !== null) {
            return $value;
        }

        // Check legacy format
        if (isset(self::$legacy_map[$module][$key])) {
            $legacy_value = get_option(self::$legacy_map[$module][$key], null);
            if ($legacy_value !== null) {
                // Migrate to new format
                self::set_module_setting($module, $key, $legacy_value);
                return $legacy_value;
            }
        }

        return $default;
    }

    /**
     * Set module-specific setting with legacy cleanup
     *
     * @param string $module Module slug
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Whether the option was updated
     */
    public static function set_module_setting($module, $key, $value) {
        $success = update_option("sewn_ws_module_{$module}_{$key}", $value);
        
        // If successful and legacy option exists, schedule cleanup
        if ($success && isset(self::$legacy_map[$module][$key])) {
            self::schedule_legacy_cleanup($module, $key);
        }
        
        return $success;
    }

    /**
     * Get a legacy option value
     *
     * @param string $key Option key
     * @return mixed|null Option value or null if not found
     */
    private static function get_legacy_option($key) {
        foreach (self::$legacy_map as $module => $mappings) {
            if (isset($mappings[$key])) {
                return get_option($mappings[$key], null);
            }
        }
        return null;
    }

    /**
     * Schedule legacy option cleanup
     *
     * @param string $module Module slug
     * @param string $key Setting key
     */
    private static function schedule_legacy_cleanup($module, $key) {
        if (!wp_next_scheduled('sewn_ws_legacy_cleanup')) {
            wp_schedule_single_event(
                time() + (30 * DAY_IN_SECONDS),
                'sewn_ws_legacy_cleanup',
                [$module, $key]
            );
        }
    }

    /**
     * Migrate all legacy settings to new format
     *
     * @return array Migration results
     */
    public static function migrate_legacy_settings() {
        $results = [];
        
        foreach (self::$legacy_map as $module => $mappings) {
            $results[$module] = [];
            
            foreach ($mappings as $new_key => $legacy_key) {
                $legacy_value = get_option($legacy_key, null);
                
                if ($legacy_value !== null) {
                    $success = self::set_module_setting($module, $new_key, $legacy_value);
                    $results[$module][$new_key] = $success;
                }
            }
        }
        
        return $results;
    }

    /**
     * Check if module has legacy settings
     *
     * @param string $module Module slug
     * @return bool Whether module has legacy settings
     */
    public static function has_legacy_settings($module) {
        if (!isset(self::$legacy_map[$module])) {
            return false;
        }

        foreach (self::$legacy_map[$module] as $legacy_key) {
            if (get_option($legacy_key, null) !== null) {
                return true;
            }
        }

        return false;
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
     * Get client-side configuration
     *
     * @return array Client-side configuration
     */
    public static function get_client_config() {
        return [
            'server' => self::get_server_config(),
            'environment' => self::get_environment_config(),
            'adminToken' => wp_create_nonce('sewn_ws_admin'),
            'debug' => self::get('debug', false),
            'namespaces' => [
                'admin' => '/admin',
                'message' => '/message',
                'presence' => '/presence',
                'status' => '/status'
            ]
        ];
    }

    /**
     * Initialize with backwards compatibility support
     *
     * @return void
     */
    public static function init() {
        // Register legacy cleanup hook
        add_action('sewn_ws_legacy_cleanup', [__CLASS__, 'cleanup_legacy_option']);
        
        // Initialize defaults
        foreach (self::$defaults as $key => $value) {
            if (get_option('sewn_ws_' . $key) === false) {
                self::set($key, $value);
            }
        }

        // Check for legacy settings
        if (get_option('sewn_ws_legacy_check', false) === false) {
            self::migrate_legacy_settings();
            update_option('sewn_ws_legacy_check', true);
        }
    }

    /**
     * Cleanup legacy option
     *
     * @param string $module Module slug
     * @param string $key Setting key
     */
    public static function cleanup_legacy_option($module, $key) {
        if (isset(self::$legacy_map[$module][$key])) {
            delete_option(self::$legacy_map[$module][$key]);
        }
    }

    /**
     * Get module configuration schema
     *
     * @param string $module Module slug
     * @return array Module configuration schema
     */
    public static function get_module_schema($module) {
        $schema_file = SEWN_WS_PATH . "modules/{$module}/config-schema.json";
        if (file_exists($schema_file)) {
            return json_decode(file_get_contents($schema_file), true);
        }
        return [];
    }

    /**
     * Validate module configuration
     *
     * @param string $module Module slug
     * @param array $config Configuration to validate
     * @return array Validation results
     */
    public static function validate_module_config($module, $config) {
        $schema = self::get_module_schema($module);
        $errors = [];
        
        foreach ($schema as $key => $rules) {
            if (!isset($config[$key]) && isset($rules['required']) && $rules['required']) {
                $errors[] = "Missing required field: {$key}";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
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
     * Generate WordPress constants JSON for Node.js server
     *
     * @return bool True if file was written successfully
     */
    public static function generate_node_constants() {
        $constants = [
            'SEWN_WS_DEFAULT_PORT' => SEWN_WS_DEFAULT_PORT,
            'SEWN_WS_SERVER_CONTROL_PATH' => SEWN_WS_SERVER_CONTROL_PATH,
            'SEWN_WS_SERVER_PID_FILE' => SEWN_WS_SERVER_PID_FILE,
            'SEWN_WS_SERVER_LOG_FILE' => SEWN_WS_SERVER_LOG_FILE,
            'SEWN_WS_NODE_SERVER' => SEWN_WS_NODE_SERVER,
            'SEWN_WS_ENV_DEBUG_ENABLED' => defined('WP_DEBUG') && WP_DEBUG,
            'SEWN_WS_STATS_UPDATE_INTERVAL' => SEWN_WS_STATS_UPDATE_INTERVAL,
            'SEWN_WS_HISTORY_MAX_POINTS' => SEWN_WS_HISTORY_MAX_POINTS,
            'SEWN_WS_IS_LOCAL' => SEWN_WS_IS_LOCAL
        ];

        $json_file = SEWN_WS_NODE_SERVER . 'wp-constants.json';
        
        return file_put_contents(
            $json_file,
            json_encode($constants, JSON_PRETTY_PRINT)
        ) !== false;
    }
}

// Initialize configuration on plugin load
add_action('plugins_loaded', ['SEWN\\WebSockets\\Config', 'init']); 