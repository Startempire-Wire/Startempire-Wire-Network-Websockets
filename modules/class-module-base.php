<?php

namespace SEWN\WebSockets\Modules;
    
abstract class Module_Base {
    const VERSION = '2.1.0';
    
    // Required module metadata
    abstract public function metadata(): array;
    
    // Module dependencies
    public function requires(): array {
        return [];
    }
    
    // Admin UI configuration
    public function admin_ui(): array {
        return [
            'menu_title' => $this->metadata()['name'],
            'capability' => 'manage_options',
            'settings' => [],
            'sections' => [
                [
                    'id' => 'default',
                    'title' => 'General Settings',
                    'callback' => null
                ]
            ]
        ];
    }
    
    // Settings renderer
    public function render_settings() {
        settings_fields('sewn_ws_module_'.$this->metadata()['slug']);
        do_settings_sections('sewn_ws_module_'.$this->metadata()['slug']);
        do_action('sewn_ws_module_settings_after', $this->metadata()['slug']);
    }
    
    // Module lifecycle hooks
    public function activate() {}
    public function deactivate() {}
    public function init() {}
    
    // Dependency checker
    final public function check_dependencies(): bool {
        foreach ($this->requires() as $dependency) {
            if (!class_exists($dependency['class'])) {
                throw new \Exception("Missing required dependency: {$dependency['name']}");
            }
            if (isset($dependency['version']) && 
                !version_compare($dependency['version'], $dependency['class']::VERSION, '<=')) {
                throw new \Exception("Dependency version mismatch for {$dependency['name']}");
            }
        }
        return true;
    }

    protected function add_settings_error($message, $type = 'error') {
        add_settings_error(
            $this->metadata()['slug'],
            'module_settings_error',
            $message,
            $type
        );
    }
}
