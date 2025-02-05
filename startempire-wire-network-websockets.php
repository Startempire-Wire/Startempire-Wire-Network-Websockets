<?php
/**
 * Plugin Name:       Startempire Wire Network Websockets
 * Plugin URI:        https://startempirewire.network/
 * Description:       Provides real-time websocket support for the Startempire Wire Network. Handles chat, notifications, and data synchronization across distributed WordPress sites.
 * Version:           0.1.0
 * Author:            Startempire Wire
 * Author URI:        https://startempirewire.com/
 * Text Domain:       sewn-ws
 * Domain Path:       /languages/
 * Namespace:         SEWN\WebSockets
 *
 * @package           Startempire_Wire_Network_Websockets
 */
 
// Add this at the VERY TOP of the file
namespace SEWN\WebSockets;
use SEWN\WebSockets\Admin\Module_Admin;
use SEWN\WebSockets\Admin\Websockets_Admin;
use SEWN\WebSockets\Dashboard;
use SEWN\WebSockets\Module_Registry;
use SEWN\WebSockets\Server_Controller;
use SEWN\WebSockets\Socket_Manager;
use SEWN\WebSockets\Unified_Roles;

// Define Global Constants
require_once __DIR__ . '/includes/constants.php';

// Add activation hook immediately after namespace declaration
register_activation_hook(__FILE__, function() {
    // Clear any existing menu cache
    delete_option('menu_cache_key');
});

// Before any other code
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add at the very top of the plugin file
try {
    // All existing plugin code
} catch(\Throwable $e) {
    add_action('admin_notices', function() use ($e) {
        echo '<div class="notice notice-error"><p>';
        echo 'WebSocket Error: ' . esc_html($e->getMessage());
        echo '</p></div>';
    });
    error_log('[SEWN] Error: ' . $e->getMessage());
    return;
}

// Check for Local's unique network interface
$ifconfig = shell_exec('ifconfig') ?? '';
if (strpos($ifconfig, 'utun0') !== false) {
    define('SEWN_WS_IS_LOCAL', true);
}

// Load core components
require_once SEWN_WS_PATH . DIRECTORY_SEPARATOR . 'class-process-manager.php';
require_once SEWN_WS_PATH . DIRECTORY_SEPARATOR . 'class-sewn-ws-dashboard.php';
require_once SEWN_WS_PATH . DIRECTORY_SEPARATOR . 'class-rest-api.php';

// Add at the top of your main plugin file
require_once SEWN_WS_PATH . DIRECTORY_SEPARATOR . 'class-process-manager.php';

// Add after namespace declarations
require_once SEWN_WS_PATH . DIRECTORY_SEPARATOR . 'class-node-check.php';
require_once SEWN_WS_PATH . DIRECTORY_SEPARATOR . 'class-install-handler.php';
require_once SEWN_WS_PATH . '../admin' . DIRECTORY_SEPARATOR . 'class-admin-notices.php';

// Add debug mode
if(!defined('SEWN_WS_DEBUG')) {
    define('SEWN_WS_DEBUG', true);
}

if(SEWN_WS_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// CORRECTED BOOTSTRAP
add_action('init', function() {
    require_once SEWN_WS_PATH . DIRECTORY_SEPARATOR . 'class-socket-manager.php';
    require_once SEWN_WS_PATH . DIRECTORY_SEPARATOR . 'class-unified-roles.php';
    
    Socket_Manager::init();
    Unified_Roles::sync_tiers();
});

// Initialize installation handler
add_action('plugins_loaded', function() {
    new Install_Handler();
});

// Before any other code
add_action('plugins_loaded', function() {
    if (!function_exists('shell_exec')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>WebSocket plugin requires shell_exec() function to be enabled</p></div>';
        });
        return;
    }
    
    if (!defined('SEWN_WS_IS_LOCAL')) {
        define('SEWN_WS_IS_LOCAL', false);
    }
});

// Error handling FIRST
add_action('plugins_loaded', function() {
    try {
        // Only define constants here
        if (!defined('SEWN_WS_FILE')) {
            define('SEWN_WS_FILE', __FILE__);
        }
        
        // Load admin UI unconditionally
        require_once SEWN_WS_PATH . '../admin' . DIRECTORY_SEPARATOR . 'class-websockets-admin.php';
        
        // Defer all other checks to admin_init
        add_action('admin_init', function() {
            if (!function_exists('shell_exec')) {
                throw new Exception('shell_exec() disabled');
            }
            
            require_once SEWN_WS_PATH . DIRECTORY_SEPARATOR . 'class-node-check.php';
            
            if (!Node_Check::check_version()) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>Node.js 16.x+ required</p></div>';
                });
                return;
            }
            
            // Only load core if requirements met
            require_once SEWN_WS_PATH . DIRECTORY_SEPARATOR . 'class-core.php';
            $core = Core::get_instance();
        });
        
    } catch(\Throwable $e) {
        error_log('WebSocket Error: ' . $e->getMessage());
    }
}, 1);

// Your code starts here.

add_action('admin_init', function() {
    try {
        $required_files = [
            DIRECTORY_SEPARATOR . 'class-node-check.php',
            DIRECTORY_SEPARATOR . 'class-core.php',
            '../admin' . DIRECTORY_SEPARATOR . 'class-websockets-admin.php'
        ];
        
        foreach ($required_files as $file) {
            $path = SEWN_WS_PATH . $file;
            if (!file_exists($path)) {
                throw new Exception("Missing required file: $file");
            }
            require_once $path;
        }
        
        // Rest of initialization
        
    } catch(\Throwable $e) {
        error_log('Plugin Error: ' . $e->getMessage());
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>';
            echo 'Configuration Error: ' . esc_html($e->getMessage());
            echo '</p></div>';
        });
    }
});

// AUTOLOADER FIX
spl_autoload_register(function ($class) {
    $prefix = 'SEWN\\WebSockets\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $parts = explode('\\', $relative_class);

    // Explicit handling for Admin namespace classes
    if ($parts[0] === 'Admin') {
        array_shift($parts); // Remove 'Admin' namespace prefix
        $class_name = str_replace('_', '-', strtolower(implode('-', $parts)));
        $file = plugin_dir_path(__FILE__) . 'admin/class-' . $class_name . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // Add this section for includes directory classes
    $class_name = str_replace('_', '-', strtolower(implode('-', $parts)));
    $file = plugin_dir_path(__FILE__) . 'includes/class-' . $class_name . '.php';
    
    if (file_exists($file)) {
        require_once $file;
        return;
    }

    // ... rest of existing autoloader code ...
});

// INIT HOOK
add_action('init', function() {
    $core = Core::get_instance();
    $core::get_instance();
});

// Initialize plugin
add_action('plugins_loaded', function() {
    error_log('[SEWN] Plugin loaded - initializing');
    
    require_once SEWN_WS_PATH . DIRECTORY_SEPARATOR . 'class-core.php';
    $core = Core::get_instance();
    $core::get_instance();
    
    error_log('[SEWN] Core initialized');
});

// Initialize admin UI
add_action('plugins_loaded', function() {
    if (is_admin()) {
        Websockets_Admin::get_instance();
        error_log('[SEWN] Admin system initialized');
    }
});

// initialize the dashboard
add_action('plugins_loaded', function() {
    if (is_admin()) {
        Dashboard::get_instance();
    }
});


// Initialize handlers
add_action('init', function() {
    Server_Controller::get_instance();
    new Stats_Handler();
    new Log_Handler();
});

require_once __DIR__ . '/includes/class-module-registry.php';

// Initialize modules after core
add_action('sewn_ws_after_core_init', function() {
    $registry = new Module_Registry();
    
    // Register core modules
    $registry->register(new Modules\Discord\Discord_Module());
    $registry->register(new Modules\Startempire\Startempire_Module());
    $registry->register(new Modules\Wirebot\Wirebot_Module());
    
    // Initialize module admin
    new Module_Admin($registry);
    
    // Late initialization
    $registry->init_modules();
});