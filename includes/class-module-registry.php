<?php
/**
 * LOCATION: includes/class-module-registry.php
 * DEPENDENCIES: Module_Base, WordPress Transients API
 * VARIABLES: $registered_modules (array)
 * CLASSES: Module_Registry (module manager)
 * 
 * Central registry for managing WebSocket protocol modules and their lifecycle. Handles activation/deactivation
 * sequencing required for network synchronization. Maintains compatibility with Ring Leader plugin's service discovery.
 */

namespace SEWN\WebSockets;

use SEWN\WebSockets\Admin\Error_Logger;
use SEWN\WebSockets\Admin\Environment_Monitor;

/**
 * Module Registry class
 * Handles registration and management of WebSocket modules with integrated error handling
 */
class Module_Registry {
    /**
     * Class instance
     *
     * @var Module_Registry
     */
    private static $instance = null;

    /**
     * Registered modules
     *
     * @var array
     */
    private $modules = array();

    /**
     * Error logger instance
     *
     * @var Error_Logger
     */
    private $logger;

    /**
     * Environment monitor instance
     *
     * @var Environment_Monitor
     */
    private $monitor;

    /**
     * Get class instance
     *
     * @return Module_Registry
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->logger = Error_Logger::get_instance();
        $this->monitor = Environment_Monitor::get_instance();
    }

    /**
     * Prevent cloning of singleton instance
     */
    private function __clone() {}

    /**
     * Prevent unserializing of singleton instance
     */
    private function __wakeup() {}

    /**
     * Register a new module
     *
     * @param Module_Base $module Module instance to register
     * @return bool Success status
     */
    public function register(Module_Base $module) {
        try {
            // Validate module
            if (!$module instanceof Module_Base) {
                throw new \Exception('Invalid module type. Must extend Module_Base.');
            }

            $module_id = $module->get_id();

            // Check for duplicate modules
            if (isset($this->modules[$module_id])) {
                throw new \Exception(sprintf('Module %s is already registered.', $module_id));
            }

            // Validate module requirements
            $this->validate_module_requirements($module);

            // Register the module
            $this->modules[$module_id] = $module;

            // Log successful registration
            $this->logger->log(
                sprintf('Module %s registered successfully', $module_id),
                array(
                    'module_id' => $module_id,
                    'module_type' => get_class($module)
                ),
                'info'
            );

            // Update environment status
            $this->update_environment_status();

            return true;

        } catch (\Exception $e) {
            // Log registration failure
            $this->logger->log(
                sprintf('Failed to register module %s', $module->get_id()),
                array(
                    'error' => $e->getMessage(),
                    'module_type' => get_class($module)
                ),
                'error'
            );

            return false;
        }
    }

    /**
     * Validate module requirements
     *
     * @param Module_Base $module Module to validate
     * @throws \Exception If requirements not met
     */
    private function validate_module_requirements(Module_Base $module) {
        // Check PHP version requirements
        if (version_compare(PHP_VERSION, $module->get_min_php_version(), '<')) {
            throw new \Exception(
                sprintf(
                    'Module %s requires PHP version %s or higher.',
                    $module->get_id(),
                    $module->get_min_php_version()
                )
            );
        }

        // Check WordPress version requirements
        global $wp_version;
        if (version_compare($wp_version, $module->get_min_wp_version(), '<')) {
            throw new \Exception(
                sprintf(
                    'Module %s requires WordPress version %s or higher.',
                    $module->get_id(),
                    $module->get_min_wp_version()
                )
            );
        }

        // Check dependencies
        foreach ($module->get_dependencies() as $dependency) {
            if (!isset($this->modules[$dependency])) {
                throw new \Exception(
                    sprintf(
                        'Module %s requires module %s which is not registered.',
                        $module->get_id(),
                        $dependency
                    )
                );
            }
        }
    }

    /**
     * Update environment status with current module information
     */
    private function update_environment_status() {
        $modules_status = array();
        foreach ($this->modules as $module_id => $module) {
            $modules_status[$module_id] = array(
                'id' => $module_id,
                'type' => get_class($module),
                'version' => $module->get_version(),
                'status' => $module->get_status(),
                'is_active' => $module->is_active()
            );
        }

        // Update environment info
        $environment_info = $this->monitor->get_environment_status();
        $environment_info['modules'] = $modules_status;
        update_option('sewn_ws_environment_info', $environment_info);
    }

    /**
     * Initialize all registered modules
     */
    public function init_modules() {
        foreach ($this->modules as $module_id => $module) {
            try {
                if (!$module->init()) {
                    throw new \Exception(sprintf('Failed to initialize module %s', $module_id));
                }

                $this->logger->log(
                    sprintf('Module %s initialized successfully', $module_id),
                    array('module_id' => $module_id),
                    'info'
                );

            } catch (\Exception $e) {
                $this->logger->log(
                    sprintf('Module initialization failed for %s', $module_id),
                    array(
                        'error' => $e->getMessage(),
                        'module_id' => $module_id
                    ),
                    'error'
                );
            }
        }

        // Update environment status after initialization
        $this->update_environment_status();
    }

    /**
     * Get all registered modules
     *
     * @return array Array of registered modules
     */
    public function get_modules() {
        return $this->modules;
    }

    /**
     * Get a specific module by ID
     *
     * @param string $module_id Module ID to retrieve
     * @return Module_Base|null Module instance or null if not found
     */
    public function get_module($module_id) {
        return isset($this->modules[$module_id]) ? $this->modules[$module_id] : null;
    }

    /**
     * Discover and register available modules
     *
     * @return array Array of discovered modules
     */
    public function discover_modules() {
        try {
            $this->modules = array();
            $module_dirs = glob(dirname(__DIR__) . '/modules/*', GLOB_ONLYDIR);

            if ($module_dirs === false) {
                throw new \Exception('Failed to scan modules directory');
            }

            foreach ($module_dirs as $dir) {
                try {
                    $module_slug = basename($dir);
                    $main_file = "$dir/class-{$module_slug}-module.php";

                    if (!file_exists($main_file)) {
                        $this->logger->log(
                            sprintf('Module main file not found for %s', $module_slug),
                            array('path' => $main_file),
                            'warning'
                        );
                        continue;
                    }

                    require_once $main_file;
                    $class_name = 'SEWN\\WebSockets\\Modules\\' . ucfirst($module_slug) . '\\' . ucfirst($module_slug) . '_Module';

                    if (!class_exists($class_name)) {
                        $this->logger->log(
                            sprintf('Module class %s not found', $class_name),
                            array('module_slug' => $module_slug),
                            'warning'
                        );
                        continue;
                    }

                    $module = new $class_name();

                    // Validate module interface
                    if (!$module instanceof Module_Base) {
                        $this->logger->log(
                            sprintf('Invalid module type for %s', $module_slug),
                            array('class' => $class_name),
                            'error'
                        );
                        continue;
                    }

                    // Register the module
                    if ($this->register($module)) {
                        $this->logger->log(
                            sprintf('Successfully discovered and registered module %s', $module_slug),
                            array('class' => $class_name),
                            'info'
                        );
                    }

                } catch (\Exception $e) {
                    $this->logger->log(
                        sprintf('Failed to load module in %s', $dir),
                        array(
                            'error' => $e->getMessage(),
                            'module_slug' => $module_slug ?? 'unknown'
                        ),
                        'error'
                    );
                }
            }

            // Update environment status after discovery
            $this->update_environment_status();

            return $this->modules;

        } catch (\Exception $e) {
            $this->logger->log(
                'Module discovery failed',
                array('error' => $e->getMessage()),
                'error'
            );
            return array();
        }
    }
} 