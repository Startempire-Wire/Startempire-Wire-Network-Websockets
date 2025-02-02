<?php
namespace SEWN\WebSockets;

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
            
            if (file_exists($main_file)) {
                require_once $main_file;
                $class_name = 'SEWN\\WebSockets\\Modules\\' . ucfirst($module_slug) . '\\' . ucfirst($module_slug) . '_Module';
                
                if (class_exists($class_name)) {
                    $this->modules[$module_slug] = new $class_name();
                    if (method_exists($this->modules[$module_slug], 'set_registry')) {
                        $this->modules[$module_slug]->set_registry($this);
                    }
                }
            }
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