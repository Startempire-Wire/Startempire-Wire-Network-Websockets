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
 * @since             0.1.0
 * @author            Startempire Wire
 * @link              https://startempirewire.com/
 * @license           GPL-2.0+
 * @copyright         2024 Startempire Wire
 */
 

// Add this at the VERY TOP of the file
namespace SEWN\WebSockets;
use SEWN\WebSockets\Admin\Dashboard;
use SEWN\WebSockets\Admin\Module_Admin;
use SEWN\WebSockets\Admin\Websockets_Admin;
use SEWN\WebSockets\Module_Registry;
use SEWN\WebSockets\Server_Controller;
use SEWN\WebSockets\Socket_Manager;
use SEWN\WebSockets\Unified_Roles;

// Constants MUST load first
require_once __DIR__ . '/includes/constants.php';

// Define plugin file constant in global namespace
if (!defined('SEWN_WS_FILE')) {
    define('SEWN_WS_FILE', __FILE__);
}

// Register autoloader first
spl_autoload_register(function ($class) {
    // Base namespace for the plugin
    $prefix = 'SEWN\\WebSockets\\';
    $len = strlen($prefix);

    // Check if the class uses our namespace
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);
    $parts = explode('\\', $relative_class);

    // Handle Admin namespace classes
    if ($parts[0] === 'Admin') {
        array_shift($parts); // Remove 'Admin' from parts
        $file = plugin_dir_path(__FILE__) . 'admin/class-' . 
                strtolower(str_replace('_', '-', implode('-', $parts))) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // Handle regular classes
    $file = plugin_dir_path(__FILE__) . 'includes/class-' . 
            strtolower(str_replace('_', '-', implode('-', $parts))) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Load Composer's autoloader if it exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load core dependencies in correct order
require_once __DIR__ . '/includes/class-exception.php';
require_once __DIR__ . '/includes/class-error-handler.php';
require_once __DIR__ . '/includes/class-log-handler.php';
require_once __DIR__ . '/includes/class-config.php';

// Load critical admin dependencies first
require_once __DIR__ . '/admin/class-error-logger.php';
require_once __DIR__ . '/admin/class-environment-monitor.php';

// Then load core classes that depend on admin classes
require_once __DIR__ . '/includes/class-module-base.php';
require_once __DIR__ . '/includes/class-module-registry.php';

// Load remaining core classes
require_once __DIR__ . '/includes/class-protocol-base.php';
require_once __DIR__ . '/includes/class-process-manager.php';
require_once __DIR__ . '/includes/class-websocket-handler.php';
require_once __DIR__ . '/includes/class-server-process.php';
require_once __DIR__ . '/includes/class-server-controller.php';
require_once __DIR__ . '/includes/class-websocket-server.php';

// Load remaining admin classes
require_once __DIR__ . '/admin/class-settings-page.php';
require_once __DIR__ . '/admin/class-module-admin.php';
require_once __DIR__ . '/admin/class-admin-ui.php';
require_once __DIR__ . '/admin/class-websockets-admin.php';

// Load module protocols
require_once __DIR__ . '/modules/wirebot/class-wirebot-protocol.php';
require_once __DIR__ . '/modules/discord/class-discord-protocol.php';
require_once __DIR__ . '/modules/startempire/class-startempire-protocol.php';

// Load module classes
require_once __DIR__ . '/modules/wirebot/class-wirebot-module.php';
require_once __DIR__ . '/modules/discord/class-discord-module.php';
require_once __DIR__ . '/modules/startempire/class-startempire-module.php';

// Initialize Module Registry early
$registry = Module_Registry::get_instance();

// Load module protocols
require_once __DIR__ . '/modules/wirebot/class-wirebot-protocol.php';
require_once __DIR__ . '/modules/discord/class-discord-protocol.php';
require_once __DIR__ . '/modules/startempire/class-startempire-protocol.php';

// Load module classes
require_once __DIR__ . '/modules/wirebot/class-wirebot-module.php';
require_once __DIR__ . '/modules/discord/class-discord-module.php';
require_once __DIR__ . '/modules/startempire/class-startempire-module.php';

// Register modules immediately
$discord = new \SEWN\WebSockets\Modules\Discord\Discord_Module();
$startempire = new \SEWN\WebSockets\Modules\Startempire\Startempire_Module();
$wirebot = new \SEWN\WebSockets\Modules\Wirebot\Wirebot_Module();

$registry->register($discord);
$registry->register($startempire);
$registry->register($wirebot);

error_log('[SEWN] Registered modules: ' . print_r($registry->get_modules(), true));

// Initialize modules after core
add_action('sewn_ws_after_core_init', function() use ($registry) {
    error_log('[SEWN] Starting module initialization');
    
    // Initialize module admin with existing registry
    new Module_Admin($registry);
    
    // Initialize modules
    $registry->init_modules();
    
    error_log('[SEWN] Completed module initialization');
});

// Add activation hook immediately after namespace declaration
register_activation_hook(__FILE__, function() {
    // Clear any existing menu cache
    delete_option('menu_cache_key');
});

// Before any other code
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize core components
require_once SEWN_WS_PATH . DIRECTORY_SEPARATOR . 'class-process-manager.php';
require_once SEWN_WS_PATH . DIRECTORY_SEPARATOR . 'class-sewn-ws-dashboard.php';
require_once SEWN_WS_PATH . DIRECTORY_SEPARATOR . 'class-rest-api.php';

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

// Initialize Process Manager early
add_action('init', function() {
    $process_manager = Process_Manager::get_instance();
    $process_manager->init();
}, 5); // Priority 5 to run before default priority

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
        Dashboard::init();
    }
});


// Initialize handlers
add_action('init', function() {
    Server_Controller::get_instance();
    Stats_Handler::get_instance();
    new Log_Handler();
});

require_once __DIR__ . '/includes/class-module-registry.php';

add_action('admin_init', function() {
    if (is_admin()) {
        \SEWN\WebSockets\Admin\Environment_Monitor::get_instance()->check_environment();
    }
});