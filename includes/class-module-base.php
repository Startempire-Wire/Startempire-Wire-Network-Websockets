<?php

/**
 * LOCATION: includes/class-module-base.php
 * DEPENDENCIES: Module_Registry, WordPress Hooks API
 * VARIABLES: $module_config (array)
 * CLASSES: Module_Base (abstract module template)
 * 
 * Provides base functionality for WebSocket protocol modules. Ensures consistent implementation
 * of network authentication and membership tier handling across all extensions. Required foundation
 * for WebRing ad network features and content distribution channels.
 */

namespace SEWN\WebSockets;

/**
 * Base class for all WebSocket modules
 */
abstract class Module_Base {
    public function __construct() {
        // Empty constructor - activation hook moved to separate method
    }

    /**
     * Register module activation hook
     * 
     * @param string $plugin_file Main plugin file path
     * @return void
     */
    public function register_activation($plugin_file) {
        if (!empty($plugin_file)) {
            register_activation_hook($plugin_file, [$this, 'activate_plugin']);
        }
    }

    /**
     * Plugin activation handler
     */
    public function activate_plugin() {
        global $wpdb;
        
        // Add index for module options if it doesn't exist
        $index_name = 'sewn_module_options';
        $table_name = $wpdb->options;
        
        // Check if index exists
        $index_exists = $wpdb->get_results("SHOW INDEX FROM {$table_name} WHERE Key_name = '{$index_name}'");
        
        if (empty($index_exists)) {
            // Add index for module options
            $wpdb->query("ALTER TABLE {$table_name} ADD INDEX {$index_name} (option_name(64))
                         WHERE option_name LIKE 'sewn_module_%'");
        }
        
        // Set autoload=no for existing module options
        $wpdb->query("UPDATE {$table_name} 
                     SET autoload = 'no' 
                     WHERE option_name LIKE 'sewn_module_%'");
                     
        // Clear any existing caches
        wp_cache_delete('sewn_ws_active_modules', 'sewn_ws');
    }

    /**
     * Get the module's slug
     *
     * @return string
     */
    abstract public function get_module_slug();

    /**
     * Get the module's name
     *
     * @return string
     */
    public function get_name() {
        return $this->get_module_slug();
    }

    /**
     * Get the module's description
     *
     * @return string
     */
    public function get_description() {
        return '';
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    abstract public function init();

    /**
     * Check module dependencies
     *
     * @return array List of dependency errors
     */
    public function check_dependencies() {
        return [];
    }

    /**
     * Deactivate the module
     *
     * @return void
     */
    public function deactivate() {
        // Base implementation - can be overridden by modules
        delete_option("sewn_module_{$this->get_module_slug()}_settings");
    }

    /**
     * Get module settings
     *
     * @return array
     */
    public function get_settings() {
        return get_option("sewn_module_{$this->get_module_slug()}_settings", []);
    }

    /**
     * Get module admin UI configuration
     *
     * @return array
     */
    public function admin_ui() {
        return [
            'sections' => [],
            'settings' => []
        ];
    }

    /**
     * Get module requirements
     *
     * @return array List of required dependencies
     */
    public function requires(): array {
        return [];
    }

    /**
     * Get module metadata
     *
     * @return array Module metadata
     */
    abstract public function metadata(): array;

    public function is_active() {
        $meta = $this->metadata();
        $slug = $this->get_module_slug();
        return (bool) get_option("sewn_module_{$slug}_active", false);
    }
} 