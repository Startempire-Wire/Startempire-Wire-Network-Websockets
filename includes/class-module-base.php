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
    /**
     * Module ID
     *
     * @var string
     */
    protected $id;

    /**
     * Module version
     *
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * Module status
     *
     * @var string
     */
    protected $status = 'inactive';

    /**
     * Minimum PHP version required
     *
     * @var string
     */
    protected $min_php_version = '7.4';

    /**
     * Minimum WordPress version required
     *
     * @var string
     */
    protected $min_wp_version = '5.8';

    /**
     * Module dependencies
     *
     * @var array
     */
    protected $dependencies = array();

    /**
     * Module slug
     *
     * @var string
     */
    protected $slug;

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

    /**
     * Get module ID
     *
     * @return string
     */
    public function get_id() {
        return $this->get_module_slug();
    }

    /**
     * Get module version
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get module status
     *
     * @return string
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * Check if module is active
     *
     * @return bool
     */
    public function is_active() {
        // Check internal status first
        if ($this->status !== 'active') {
            return false;
        }

        // Check persistent activation status
        $meta = $this->metadata();
        $slug = $this->get_module_slug();
        $is_active = (bool) get_option("sewn_module_{$slug}_active", false);

        // Update internal status if there's a mismatch
        if ($is_active && $this->status !== 'active') {
            $this->set_status('active');
        } elseif (!$is_active && $this->status === 'active') {
            $this->set_status('inactive');
        }

        return $is_active;
    }

    /**
     * Get minimum PHP version required
     *
     * @return string
     */
    public function get_min_php_version() {
        return $this->min_php_version;
    }

    /**
     * Get minimum WordPress version required
     *
     * @return string
     */
    public function get_min_wp_version() {
        return $this->min_wp_version;
    }

    /**
     * Get module dependencies
     *
     * @return array
     */
    public function get_dependencies() {
        return $this->dependencies;
    }

    /**
     * Set module status
     *
     * @param string $status New status
     * @return void
     */
    protected function set_status($status) {
        $this->status = $status;
    }

    /**
     * Get module slug
     *
     * @return string
     */
    public function get_module_slug() {
        if (empty($this->slug)) {
            // Get slug from metadata if available
            $meta = $this->metadata();
            if (!empty($meta['module_slug'])) {
                $this->slug = $meta['module_slug'];
            } else {
                // Fallback to class name based slug
                $class_name = get_class($this);
                $parts = explode('\\', $class_name);
                $base_name = end($parts);
                $this->slug = strtolower(str_replace('_Module', '', $base_name));
            }
        }
        return $this->slug;
    }

    /**
     * Get module setting
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    protected function get_setting($key, $default = null) {
        return Config::get_module_setting($this->slug, $key, $default);
    }

    /**
     * Save module setting
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Whether the setting was saved
     */
    protected function save_setting($key, $value) {
        return Config::set_module_setting($this->slug, $key, $value);
    }

    /**
     * Get all module settings
     *
     * @return array Module settings
     */
    protected function get_all_settings() {
        $schema = Config::get_module_schema($this->slug);
        $settings = [];

        foreach (array_keys($schema) as $key) {
            $settings[$key] = $this->get_setting($key);
        }

        return $settings;
    }

    /**
     * Validate module settings
     *
     * @param array $settings Settings to validate
     * @return array Validation results
     */
    protected function validate_settings($settings) {
        return Config::validate_module_config($this->slug, $settings);
    }
} 