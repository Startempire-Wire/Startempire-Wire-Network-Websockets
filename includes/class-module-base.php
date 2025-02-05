<?php
namespace SEWN\WebSockets;

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

abstract class Module_Base {
    abstract public function metadata();
    abstract public function init();
    
    public function check_dependencies() {
        return true;
    }

    public function is_active() {
        $meta = $this->metadata();
        $slug = $this->get_module_slug();
        return (bool) get_option("sewn_module_{$slug}_active", false);
    }

    public function get_module_slug() {
        $meta = $this->metadata();
        if (!empty($meta['module_slug'])) {
            return sanitize_key($meta['module_slug']);
        }
        
        $class_name = get_class($this);
        $slug = strtolower(preg_replace(
            ['/([a-z])([A-Z])/', '/_/', '/\\\/', '/Module$/'],
            ['$1-$2', '-', '-', ''],
            $class_name
        ));
        return sanitize_title($slug);
    }
} 