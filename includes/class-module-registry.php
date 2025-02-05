<?php
namespace SEWN\WebSockets;

/**
 * LOCATION: includes/class-module-registry.php
 * DEPENDENCIES: Module_Base, WordPress Transients API
 * VARIABLES: $registered_modules (array)
 * CLASSES: Module_Registry (module manager)
 * 
 * Central registry for managing WebSocket protocol modules and their lifecycle. Handles activation/deactivation
 * sequencing required for network synchronization. Maintains compatibility with Ring Leader plugin's service discovery.
 */

class Module_Registry {
    private static $instance;
    private $modules = [];
    
    private function __construct() {}

    public static function get_instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function discover_modules() {
        $this->modules = [];

        $module_dirs = glob(__DIR__ . '/../modules/*', GLOB_ONLYDIR);
        
        foreach ($module_dirs as $dir) {
            $module_slug = basename($dir);
            $main_file = "$dir/class-{$module_slug}-module.php";
            
            if (!file_exists($main_file)) continue;

            require_once $main_file;
            $class_name = 'SEWN\\WebSockets\\Modules\\' . ucfirst($module_slug) . '\\' . ucfirst($module_slug) . '_Module';
            
            if (!class_exists($class_name)) continue;

            $module = new $class_name();
            
            // Validate module interface
            if (!method_exists($module, 'metadata') || !is_array($module->metadata())) {
                error_log("Invalid module: $class_name - Missing valid metadata()");
                continue;
            }

            $this->modules[$module_slug] = $module;
        }
    }
    
    public function get_module($module_slug) {
        return $this->modules[$module_slug] ?? null;
    }
    
    public function get_modules() {
        return $this->modules;
    }
    
    public function init_modules() {
        foreach ($this->modules as $module) {
            if (method_exists($module, 'init')) {
                $module->init();
            }
        }
    }
} 