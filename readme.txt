=== Startempire Wire Network WebSockets ===
Contributors: startempirewire
Donate link: https://startempirewire.com/donate
Tags: websocket, realtime, communication, api
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Enterprise-grade WebSocket solution for WordPress with real-time communication features.

== Description ==
**Startempire Wire Network WebSockets** is an server agnostic high-performance WebSocket implementation built on top of Socket.io for WordPress that enables:

* Real-time bidirectional communication
* Integration points for external services
* Hybrid WebSocket/WebRTC & Socket.io architecture
* Enterprise-grade security with JWT authentication
* Comprehensive documentation & Admin dashboard
* Agnostic architecture that can be used on any server or service
* Built with performance, scalability, and ease of use in mind
* Uses AsyncAPI to describe our WebSocket-based APIs (https://www.asyncapi.com/)

== Installation ==
1. Upload plugin files to `/wp-content/plugins/`
2. Activate through WordPress admin
3. Install Node.js dependencies:

    cd wp-content/plugins/startempire-wire-network-websockets/node-server
    npm install --production

4. Configure environment variables in `.env`
5. Start service via WP-CLI:

    wp sewn-ws server start

6. Configure firewall rules to allow WebSocket traffic on port 49200 (default)
   - Port 49200 is chosen from IANA Dynamic Port range (49152-65535)
   - This avoids conflicts with common development ports
   - Can be changed in plugin settings if needed

7. Enable TLS encryption in plugin settings

== Port Configuration ==

The plugin supports two methods for configuring the WebSocket port:

1. Recommended (Current):
   define('SEWN_WS_DEFAULT_PORT', 49200);  // In wp-config.php

2. Legacy (Deprecated):
   define('SEWN_WS_ENV_DEFAULT_PORT', 8081);  // Will be removed in v2.0.0

The default port 49200 is chosen from the IANA Dynamic Port range (49152-65535) to avoid conflicts with common development ports. See MIGRATION.md for detailed upgrade instructions.

== REST API Endpoints ==
| Endpoint                | Method | Description                     |
|-------------------------|--------|---------------------------------|
| /sewn-ws/v1/config      | GET    | Server configuration           |
| /sewn-ws/v1/broadcast   | POST   | Global message broadcast       |
| /sewn-ws/v1/status      | GET    | Connection statistics          |

== Frequently Asked Questions ==

= What WebSocket features are included? =
- Real-time messaging
- Presence tracking
- File transfer (WebRTC)
- Cross-domain communication

= How does External Service Integration work? =
Premium users can enable:
- Priority message routing
- Collaborative editing sessions
- Enterprise-grade broadcasting

= What are the system requirements? =
- WordPress 5.8+
- PHP 7.4+
- Node.js 16.x
- 1GB+ RAM recommended

== Changelog ==

= 1.0 =
* Initial release
* Core WebSocket implementation
* External Service Integration

= 0.5 =
* Beta release
* Basic WebSocket functionality
* Admin dashboard prototype

== Upgrade Notice ==

= 1.0 =
**Major Update** - Requires Node.js 16.x+ and PHP 7.4+. Includes new security features.

= 0.5 =
Initial beta release - Not recommended for production use.

== Support ==
Contact support@startempirewire.com for technical assistance.

STARTEMPIRE WIRE NETWORK WEBSOCKETS DOCUMENTATION
==================================================

1. ARCHITECTURE OVERVIEW
-----------------------

1.1 System Diagram
[WordPress] ↔ [REST API] ↔ [WebSocket Server] ↔ [WebRTC Service]
                ↑               ↑               ↑
            [Auth Service]  [Message Broker] [File Transfer]

1.2 Core Components
- SEWN_WebSocket_Server: Main WebSocket handler
- SEWN_Auth_Manager: JWT authentication
- SEWN_Protocol_Adapter: WebSocket/WebRTC bridge
- SEWN_Monitoring: Real-time metrics collector

2. INSTALLATION & SETUP
-----------------------

2.1 Requirements
- WordPress 5.8+
- PHP 7.4+ (with sodium extension)
- Node.js 16.x
- Redis 6.2+ (recommended)

2.2 Installation Steps
1. Upload plugin via WP Admin
2. Install dependencies:
   cd wp-content/plugins/startempire-wire-network-websockets/node-server
   npm install --production
3. Configure .env:
   WP_PORT=49200  # Using IANA Dynamic Port range (49152-65535)
   JWT_SECRET=your-secret-key
   REDIS_URL=redis://localhost:6379
4. Start service via WP-CLI:

    wp sewn-ws server start

5. Configure firewall rules to allow WebSocket traffic on port 49200 (default)
   - Port 49200 is chosen from IANA Dynamic Port range (49152-65535)
   - This avoids conflicts with common development ports
   - Can be changed in plugin settings if needed

6. Enable TLS encryption in plugin settings

3. CONFIGURATION
----------------

3.1 wp-config.php Settings
define('SEWN_WS_ENCRYPTION_KEY', 'your-encryption-key');
define('SEWN_WS_MAX_CONNECTIONS', 1000);
define('SEWN_WS_ENV_DEFAULT_PORT', 8081); // Overrides base port setting
define('SEWN_WS_ENV_LOCAL_MODE', true); // Force local mode

3.2 Admin Settings (WP Admin → WebSocket → Settings)
- Port Configuration
- TLS Certificate Path
- Rate Limiting Rules
- WebRTC ICE Servers

4. API DOCUMENTATION
-------------------

4.1 REST Endpoints
POST /sewn-ws/v1/auth → Generate JWT
  Params: username, password
  Response: { 
    token: "jwt-token", 
    expires: 3600,
    isAdmin: true,
    tier: "admin"
  }

GET /sewn-ws/v1/stats → Connection Statistics
  Response: 
  {
    connections: 142,
    bandwidth: "1.2GB",
    uptime: "36h"
  }

4.2 WebSocket Protocol
- WordPress administrators get full system access
- Users inherit capabilities from their tier
- Tiers temporarily based on WordPress roles

Handshake Headers:
Authorization: Bearer <JWT>
X-SEWN-Protocol: v1.2

Message Format:
{
  "event": "message|file|presence",
  "data": {},
  "timestamp": 1625097600
}

5. HOOKS & FILTERS
-------------------

5.1 Authentication Hooks
add_filter('sewn_ws_validate_token', function($user_id, $token) {
  // Custom token validation
  return $user_id;
});

5.2 Connection Lifecycle
add_action('sewn_ws_client_connect', function($client_id) {
  error_log("Client connected: " . $client_id);
});

add_action('sewn_ws_client_disconnect', function($client_id) {
  // Cleanup resources
});

6. SECURITY PROTOCOLS
---------------------

6.1 Authentication Flow
1. Client → WP: Authenticate via REST
2. WP → Client: JWT (valid 1h)
3. Client → WS: Connect with JWT
4. WS → WP: Validate JWT
5. WS ↔ Client: Secured connection

6.2 Encryption Standards
- TLS 1.3 mandatory
- JWT HS256 signing
- Message payload encryption (XChaCha20)

7. TROUBLESHOOTING
------------------

7.1 Common Issues

Issue: Connection Timeout
Solution:
1. Verify firewall rules
2. Check WS_PORT availability
3. Test with: telnet yoursite.com 49200

Issue: JWT Validation Failures
1. Ensure system clocks are synchronized
2. Verify JWT_SECRET matches between WP and WS
3. Check token expiration time

8. DEVELOPER GUIDE
------------------

8.1 Extending Protocols

class CustomProtocol extends SEWN_Protocol_Base {
  public function handleMessage($message) {
    // Custom message processing
  }
}

add_filter('sewn_ws_protocols', function($protocols) {
  $protocols['custom'] = CustomProtocol::class;
  return $protocols;
});

9. MONITORING & ANALYTICS
-------------------------

9.1 Key Metrics
- ws_connections_active
- ws_messages_per_second
- ws_bandwidth_usage
- ws_error_rate

9.2 Prometheus Configuration
- Endpoint: /metrics
- Scrape interval: 15s
- Alert rules:
  - High error rate
  - Connection saturation

9.3 Module Development Patterns

Each module in the WebSocket system follows a standardized structure with these key components:

1. Required Files:
   - class-{module}-module.php (Core Module Class)
     * Extends Module_Base
     * Handles module lifecycle and integration
     * Required methods:
       - get_module_slug(): string
       - metadata(): array
       - init(): void
       - check_dependencies(): array|bool
     * Optional methods:
       - requires(): array
       - admin_ui(): array
       - activate(): void
       - deactivate(): void

2. Settings System:
   Each module defines its settings interface by implementing the `admin_ui()` method:

   ```php
   public function admin_ui(): array {
       return [
           'menu_title' => 'Module Settings',  // Optional: Custom menu title
           'capability' => 'manage_options',    // Optional: Required capability
           'settings' => [                      // Array of setting fields
               [
                   'name' => 'setting_name',    // Setting name (without module prefix)
                   'label' => 'Setting Label',  // Display label
                   'type' => 'text',           // Field type (see Field Types below)
                   'description' => 'Help text', // Field description
                   'section' => 'section_id',   // Section ID where field appears
                   'options' => [],            // Required for select fields
                   'sanitize' => 'callback',   // Optional sanitization callback
                   'default' => 'value',       // Default value
                   'depends_on' => 'field_name' // Optional dependency on another field
               ]
           ],
           'sections' => [                     // Array of setting sections
               [
                   'id' => 'section_id',       // Unique section identifier
                   'title' => 'Section Title', // Display title
                   'callback' => null          // Optional render callback
               ]
           ]
       ];
   }
   ```

   Field Types Supported:
   - text: Standard text input
   - password: Password field with masked input
   - checkbox: Boolean toggle
   - select: Dropdown with options
   - textarea: Multi-line text input
   - url: URL input field
   - number: Numeric input with validation
   - color: Color picker input
   - file: File upload field
   - radio: Radio button group
   - hidden: Hidden input field

   Dependencies:
   - Use 'depends_on' to show/hide fields based on another field's value
   - Typically used with checkbox fields to toggle related settings
   - Supports complex dependency chains
   - Can depend on multiple fields using array syntax
   - Handles nested dependencies automatically

   Technical Implementation:
   1. Settings Storage:
      - All settings automatically prefixed with 'sewn_ws_module_{module_slug}_'
      - Settings grouped by section in the database
      - Example: 'bot_token' becomes 'sewn_ws_module_discord_bot_token'
      - Uses WordPress options API with autoload optimization
      - Supports serialized data for complex settings

   2. Visual Organization:
      - Settings can be organized into two columns using section positioning
      - Base settings are visually separated from custom module settings
      - Sections can include custom rendering callbacks for advanced layouts
      ```php
      'sections' => [
          [
              'id' => 'left_column',
              'title' => __('Primary Settings', 'sewn-ws'),
              'position' => 'left'
          ],
          [
              'id' => 'right_column',
              'title' => __('Secondary Settings', 'sewn-ws'),
              'position' => 'right'
          ]
      ]
      ```

   3. Validation & Error Handling:
      - Built-in sanitization for common field types
      - Custom sanitization callback support
      - Inline error display
      - Field dependency management
      - Example validation:
      ```php
      'settings' => [
          [
              'name' => 'port_number',
              'type' => 'number',
              'sanitize' => function($value) {
                  $port = absint($value);
                  if ($port < 1024 || $port > 65535) {
                      add_settings_error(
                          'port_number',
                          'invalid_port',
                          __('Port must be between 1024 and 65535', 'sewn-ws')
                      );
                      return 49200; // Default fallback
                  }
                  return $port;
              }
          ]
      ]
      ```

3. Standardized UI Components:
   Each module automatically receives these base settings and UI components:

   1. Core Settings Tab:
      Module Information Section:
      - Status indicator (active/inactive)
      - Version information
      - Author details
      - Module description
      - Dependency status
      
      Access Control Section:
      - Access level selection
        * Public access
        * Registered users
        * Premium members
      - Role-based permissions
      - Custom capability settings
      
      Debug Settings Section:
      - Debug mode toggle
      - Log level selection
        * Error
        * Warning
        * Info
        * Debug
      - Error reporting options
      - Debug log viewer

   2. Performance Tab:
      Cache Settings Section:
      - Cache enable/disable toggle
      - Cache duration selection
        * 5 minutes
        * 15 minutes
        * 1 hour
        * 24 hours
      - Cache clearing options
      - Storage location settings
      
      Rate Limiting Section:
      - Rate limit enable/disable
      - Request limit configuration
        * Requests per window
        * Window duration
      - Burst allowance settings
      - Override options

   3. Module Settings Tab:
      Custom Settings Section:
      - Module-specific settings
      - Custom field types
      - Dependency handling
      - Validation rules
      
      Migration Interface (if applicable):
      - Legacy settings detection
      - Migration controls
      - Status indicators
      - Rollback options

4. Layout Standards:
   ```css
   .sewn-ws-tabs {
       margin-top: 20px;
   }

   .sewn-ws-tab-content {
       background: #fff;
       padding: 20px;
       border: 1px solid #ccd0d4;
       border-top: none;
   }

   .sewn-ws-settings-grid {
       display: grid;
       grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
       gap: 20px;
   }

   .sewn-ws-field {
       margin-bottom: 15px;
   }

   .sewn-ws-field label {
       display: block;
       margin-bottom: 5px;
   }

   .sewn-ws-field .description {
       color: #666;
       font-style: italic;
       margin-top: 5px;
   }
   ```

5. JavaScript Integration:
   ```javascript
   jQuery(document).ready(function($) {
       // Tab switching
       $('.sewn-ws-tabs .nav-tab').on('click', function(e) {
           e.preventDefault();
           var target = $(this).attr('href');
           
           $('.sewn-ws-tabs .nav-tab').removeClass('nav-tab-active');
           $(this).addClass('nav-tab-active');
           
           $('.sewn-ws-tab-content').removeClass('active');
           $(target).addClass('active');
       });

       // Dependency handling
       $('[data-depends-on]').each(function() {
           var $field = $(this);
           var dependency = $field.data('depends-on');
           
           $('#' + dependency).on('change', function() {
               $field.toggle(this.checked);
           }).trigger('change');
       });
   });
   ```

6. Required Hooks:
   ```php
   // Register settings
   add_action('admin_init', [$this, 'register_settings']);

   // Handle settings validation
   add_filter("pre_update_option_{$setting_name}", [$this, 'validate_setting']);

   // Process form submission
   add_action('admin_post_save_module_settings', [$this, 'handle_save']);
   ```

7. Security Implementation:
   - Capability checks for all operations
   - Data sanitization on input/output
   - Nonce verification for forms
   - XSS prevention
   - CSRF protection

8. Error Handling:
   - Standardized error messages
   - Validation feedback
   - User-friendly notices
   - Debug logging support
   - Recovery options

10. SUPPORT & MAINTENANCE
-------------------------

Support Channels:
- Email: support@startempirewire.com
- SLA Response Times:
  - Critical: 1h
  - High: 4h
  - Normal: 24h

Maintenance Schedule:
- Security patches: Weekly
- Major updates: Quarterly
- Protocol upgrades: Bi-annually

APPENDICES
----------

A. Glossary
- ICE: Interactive Connectivity Establishment
- JWT: JSON Web Token
- STUN: Session Traversal Utilities for NAT

B. CLI Reference
wp sewn-ws server restart
wp sewn-ws stats --format=json
wp sewn-ws client list

C. Legal
- License: GPLv3
- Data Policy: https://startempirewire.com/privacy
- SLA Terms: https://startempirewire.com/sla

5. Protocol Requirements:
   - Must implement message validation layer
   - Required to handle 3 core message types:
     1. Connection lifecycle events:
        - connect: Initial connection establishment
        - disconnect: Connection termination
        - reconnect: Connection recovery
        - error: Connection errors
     2. Protocol-specific operations:
        - Custom message types
        - Protocol-specific commands
        - State synchronization
        - Batch operations
     3. Error handling responses:
        - Validation errors
        - Protocol errors
        - System errors
        - Rate limit errors

   Standard Message Format:
   ```php
   [
       'type' => string,      // Message type identifier
       'data' => array,       // Message payload
       'user_data' => array,  // User context data
       'timestamp' => int,    // Message timestamp
       'version' => string,   // Protocol version
       'trace_id' => string,  // Optional tracing ID
       'metadata' => array    // Optional metadata
   ]
   ```

   Standard Response Format:
   ```php
   [
       'status' => string,    // 'success' or 'error'
       'data' => array,       // Response payload
       'timestamp' => int,    // Response timestamp
       'code' => int,        // Response code
       'message' => string,  // Human-readable message
       'context' => array    // Additional context
   ]
   ```

9.4 Basic Module Requirements
----------------------------

1. Module File Structure:
```
modules/example/
├── class-example-module.php         # Core module class
├── class-example-protocol.php       # WebSocket protocol handler
├── includes/                        # Module includes
│   ├── class-example-handler.php    # Business logic
│   └── class-example-api.php        # API implementation
├── admin/                           # Admin interface
│   ├── class-admin.php             # Admin functionality
│   ├── js/                         # Admin JavaScript
│   │   └── admin.js               # Admin scripts
│   ├── css/                        # Admin styles
│   │   └── admin.css             # Admin stylesheets
│   └── views/                      # Admin templates
│       └── settings.php           # Settings page
└── public/                         # Public assets
    ├── js/                         # Public JavaScript
    │   └── client.js              # Client-side code
    └── css/                        # Public styles
        └── style.css              # Public stylesheets
```

2. Basic Module Implementation Example:

```php
<?php
/**
 * Example Module Implementation
 *
 * @package SEWN\WebSockets\Modules\Example
 */

namespace SEWN\WebSockets\Modules\Example;

use SEWN\WebSockets\Module_Base;
use SEWN\WebSockets\Exception;

class Example_Module extends Module_Base {
    /**
     * Get module slug
     *
     * @return string
     */
    public function get_module_slug(): string {
        return 'example';
    }

    /**
     * Module metadata
     *
     * @return array
     */
    public function metadata(): array {
        return [
            'name'        => 'Example Module',
            'version'     => '1.0.0',
            'description' => 'Example module implementation',
            'author'      => 'Your Name',
            'author_uri'  => 'https://example.com',
            'requires'    => [
                'php' => '7.4',
                'wp'  => '5.8'
            ]
        ];
    }

    /**
     * Initialize module
     *
     * @return void
     * @throws Exception
     */
    public function init(): void {
        try {
            // Register hooks
            add_action('sewn_ws_init', [$this, 'setup_module']);
            add_action('sewn_ws_client_connected', [$this, 'handle_connection']);
            add_action('sewn_ws_client_disconnected', [$this, 'handle_disconnection']);

            // Initialize components
            $this->init_protocol();
            $this->init_admin();

        } catch (\Throwable $e) {
            throw new Exception(
                sprintf('Failed to initialize %s module: %s', 
                    $this->get_module_slug(), 
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Check dependencies
     *
     * @return array|bool
     */
    public function check_dependencies() {
        $errors = [];

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = 'PHP 7.4 or higher required';
        }

        // Check WordPress version
        if (version_compare($GLOBALS['wp_version'], '5.8', '<')) {
            $errors[] = 'WordPress 5.8 or higher required';
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Module requirements
     *
     * @return array
     */
    public function requires(): array {
        return [
            'modules' => [],  // Required modules
            'plugins' => [],  // Required plugins
            'php_extensions' => ['json']  // Required PHP extensions
        ];
    }

    /**
     * Admin UI configuration
     *
     * @return array
     */
    public function admin_ui(): array {
        return [
            'menu_title' => 'Example Settings',
            'capability' => 'manage_options',
            'settings' => [
                [
                    'name' => 'example_setting',
                    'label' => 'Example Setting',
                    'type' => 'text',
                    'description' => 'Example setting description',
                    'section' => 'general',
                    'default' => ''
                ]
            ],
            'sections' => [
                [
                    'id' => 'general',
                    'title' => 'General Settings'
                ]
            ]
        ];
    }

    /**
     * Handle module activation
     *
     * @return void
     */
    public function activate(): void {
        // Activation tasks
        update_option('example_module_activated', time());
    }

    /**
     * Handle module deactivation
     *
     * @return void
     */
    public function deactivate(): void {
        // Cleanup tasks
        delete_option('example_module_activated');
    }
}
```

3. Required Components:

a. Core Requirements:
   - PHP 7.4+
   - WordPress 5.8+
   - Proper namespace (SEWN\WebSockets\Modules\{ModuleName})
   - Must extend Module_Base class

b. Required Methods:
   - get_module_slug(): string
   - metadata(): array
   - init(): void
   - check_dependencies(): array|bool

c. Optional Methods:
   - requires(): array
   - admin_ui(): array
   - activate(): void
   - deactivate(): void

d. Required Hooks:
   - sewn_ws_init
   - sewn_ws_client_connected
   - sewn_ws_client_disconnected
   - sewn_ws_register_protocols (if implementing protocol)

4. Error Handling:

```php
try {
    // Potentially dangerous operation
    $result = $this->perform_operation();
    
    if (!$result) {
        throw new Exception('Operation failed');
    }
    
} catch (\Throwable $e) {
    // Log error
    error_log(sprintf(
        '[%s] Module Error: %s', 
        $this->get_module_slug(), 
        $e->getMessage()
    ));
    
    // Notify admin if critical
    add_action('admin_notices', function() use ($e) {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html($e->getMessage())
        );
    });
    
    // Handle gracefully
    return false;
}
```

5. Best Practices:

a. File Organization:
   - One class per file
   - Clear file naming
   - Logical directory structure
   - Separate admin and public assets

b. Code Standards:
   - Follow WordPress Coding Standards
   - Use proper documentation blocks
   - Implement proper error handling
   - Follow security best practices

c. Security:
   - Validate all input
   - Sanitize all output
   - Use nonces for forms
   - Check capabilities
   - Implement rate limiting

d. Performance:
   - Cache expensive operations
   - Optimize database queries
   - Minimize asset loading
   - Use WordPress transients

6. Security Requirements:
   Admin Settings Security:
   - Type-specific sanitization:
     * text: sanitize_text_field()
     * url: esc_url_raw()
     * email: sanitize_email()
     * number: absint() or floatval()
     * html: wp_kses_post()
   - Capability checks:
     * manage_options for admin settings
     * custom capabilities for specific features
     * role-based access control
   - Nonce verification:
     * Unique nonce per form
     * Nonce lifetime management
     * CSRF protection

   Protocol Security:
   - Message Origin Validation:
     * Verify sender authenticity
     * Validate origin headers
     * Check referrer policies
   - Network Access Control:
     * IP allowlisting
     * Geographic restrictions
     * Rate limiting per IP
   - Input Sanitization:
     * Validate all incoming data
     * Sanitize based on context
     * Escape output appropriately
   - WordPress Security:
     * Use WordPress security functions
     * Follow WordPress coding standards
     * Implement capability checks
   - Rate Limiting:
     * Per-user limits
     * Global limits
     * Burst protection
     * Cooldown periods

7. Example Structure:
   ```
   modules/{module}/
   ├── class-{module}-module.php      # Core module implementation
   ├── class-{module}-protocol.php    # WebSocket protocol handler
   ├── class-{module}-handler.php     # Optional business logic handler
   ├── admin/                         # Admin interface files
   │   ├── class-admin.php           # Admin functionality
   │   ├── views/                    # Admin templates
   │   └── js/                       # Admin JavaScript
   ├── includes/                      # Core functionality
   │   ├── class-api.php            # API implementation
   │   └── class-helpers.php        # Helper functions
   ├── public/                        # Public assets
   │   ├── css/                     # Stylesheets
   │   └── js/                      # JavaScript files
   └── config/
       ├── default-settings.json     # Configuration presets
       └── schema.json              # Settings schema
   ```

8. Required Hooks:
   Protocol Registration:
   ```php
   add_action('sewn_ws_register_protocols', function($handler) {
       $handler->register_protocol('custom', new Custom_Protocol());
   });
   ```

   Connection Lifecycle:
   ```php
   add_action('sewn_ws_client_connected', function($client_id) {
       // Handle new connection
   });

   add_action('sewn_ws_client_disconnected', function($client_id) {
       // Cleanup on disconnect
   });
   ```

   Authentication:
   ```php
   add_filter('sewn_ws_auth_validation', function($token) {
       // Validate authentication token
       return $validated_token;
   });
   ```

   Initialization:
   ```php
   add_action('sewn_ws_init', function() {
       // Initialize module components
   });
   ```

   Client Configuration:
   ```php
   add_filter('sewn_ws_client_config', function($config) {
       // Add module-specific client configuration
       return $config;
   });
   ```

9. Best Practices:
   1. Configuration Management:
      - Use Config class for all settings
      - Implement caching for frequently accessed values
      - Validate configuration on load
      - Provide defaults for all settings

   2. Protocol Implementation:
      - Implement message validation interface
      - Use protocol versioning
      - Handle protocol upgrades gracefully
      - Support protocol negotiation

   3. Code Organization:
      - Use separate handler classes for complex logic
      - Follow single responsibility principle
      - Implement proper error handling
      - Use dependency injection

   4. Version Management:
      - Include protocol version in messages
      - Support backward compatibility
      - Plan for future upgrades
      - Document version changes

   5. Debugging and Monitoring:
      - Add admin debug tools
      - Implement logging
      - Add performance metrics
      - Support troubleshooting

   6. Error Handling:
      - Implement circuit breakers
      - Handle edge cases
      - Provide meaningful error messages
      - Log errors appropriately

   7. Helper Methods:
      - Use base class helper methods:
        * handle_error() for error responses
        * format_response() for success responses
        * validate_message() for input validation
      - Create reusable utilities
      - Document helper functions
      - Test helper methods

10. Documentation Validation:
    ✓ Matches Discord implementation patterns
    ✓ Covers all security aspects from reference module
    ✓ Specifies exact hook requirements
    ✓ Requires proper class verification
    ✓ Enforces lifecycle management
    ✓ Documents backwards compatibility
    ✓ Includes migration guidance
    ✓ Provides clear examples
    ✓ Includes security best practices
    ✓ Details error handling
    ✓ Covers testing requirements
    ✓ Explains configuration management

11. Standardized Module UI
-------------------------

Each module automatically receives these base settings and standardized UI components:

1. Core Settings Tab:
   Module Information Section:
   - Status indicator (active/inactive)
   - Version information
   - Author details
   - Module description
   - Dependency status
   
   Access Control Section:
   - Access level selection
     * Public access
     * Registered users
     * Premium members
   - Role-based permissions
   - Custom capability settings
   
   Debug Settings Section:
   - Debug mode toggle
   - Log level selection
     * Error
     * Warning
     * Info
     * Debug
   - Error reporting options
   - Debug log viewer

2. Performance Tab:
   Cache Settings Section:
   - Cache enable/disable toggle
   - Cache duration selection
     * 5 minutes
     * 15 minutes
     * 1 hour
     * 24 hours
   - Cache clearing options
   - Storage location settings
   
   Rate Limiting Section:
   - Rate limit enable/disable
   - Request limit configuration
     * Requests per window
     * Window duration
   - Burst allowance settings
   - Override options

3. Module Settings Tab:
   Custom Settings Section:
   - Module-specific settings
   - Custom field types
   - Dependency handling
   - Validation rules
   
   Migration Interface (if applicable):
   - Legacy settings detection
   - Migration controls
   - Status indicators
   - Rollback options

Standard UI Components:
```php
// Standard settings section
add_settings_section(
    'module_info',
    __('Module Information', 'sewn-ws'),
    [$this, 'render_info_section'],
    'sewn_ws_module_' . $module_slug
);

// Standard setting field
add_settings_field(
    'debug_mode',
    __('Debug Mode', 'sewn-ws'),
    [$this, 'render_setting_field'],
    'sewn_ws_module_' . $module_slug,
    'debug_settings',
    [
        'type' => 'checkbox',
        'name' => 'debug_enabled',
        'description' => __('Enable debug logging', 'sewn-ws')
    ]
);
```

Layout Standards:
- Two-column layout for settings
- Consistent spacing and padding
- Standard form element styling
- Responsive design support
- Accessibility compliance

Visual Components:
- Status indicators
- Error/warning notices
- Loading indicators
- Tooltips and help text
- Validation feedback

Integration Points:
- WordPress admin styles
- Custom module styles
- JavaScript dependencies
- AJAX handlers
- Form processors

Example Implementation:
```php
public function render_module_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html($this->get_title()); ?></h1>
        
        <div class="sewn-ws-tabs">
            <nav class="nav-tab-wrapper">
                <a href="#core" class="nav-tab nav-tab-active">
                    <?php _e('Core Settings', 'sewn-ws'); ?>
                </a>
                <a href="#performance" class="nav-tab">
                    <?php _e('Performance', 'sewn-ws'); ?>
                </a>
                <a href="#module" class="nav-tab">
                    <?php _e('Module Settings', 'sewn-ws'); ?>
                </a>
            </nav>

            <form method="post" action="options.php">
                <?php
                settings_fields($this->get_option_group());
                
                echo '<div id="core" class="sewn-ws-tab-content active">';
                do_settings_sections($this->get_core_page());
                echo '</div>';
                
                echo '<div id="performance" class="sewn-ws-tab-content">';
                do_settings_sections($this->get_performance_page());
                echo '</div>';
                
                echo '<div id="module" class="sewn-ws-tab-content">';
                do_settings_sections($this->get_module_page());
                echo '</div>';
                
                submit_button();
                ?>
            </form>
        </div>
    </div>
    <?php
}
```

CSS Standards:
```css
.sewn-ws-tabs {
    margin-top: 20px;
}

.sewn-ws-tab-content {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-top: none;
}

.sewn-ws-settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.sewn-ws-field {
    margin-bottom: 15px;
}

.sewn-ws-field label {
    display: block;
    margin-bottom: 5px;
}

.sewn-ws-field .description {
    color: #666;
    font-style: italic;
    margin-top: 5px;
}
```

JavaScript Integration:
```javascript
jQuery(document).ready(function($) {
    // Tab switching
    $('.sewn-ws-tabs .nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        
        $('.sewn-ws-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.sewn-ws-tab-content').removeClass('active');
        $(target).addClass('active');
    });

    // Dependency handling
    $('[data-depends-on]').each(function() {
        var $field = $(this);
        var dependency = $field.data('depends-on');
        
        $('#' + dependency).on('change', function() {
            $field.toggle(this.checked);
        }).trigger('change');
    });
});
```

9.4 Basic Module Requirements
----------------------------

1. Module File Structure:
```
modules/example/
├── class-example-module.php         # Core module class
├── class-example-protocol.php       # WebSocket protocol handler
├── includes/                        # Module includes
│   ├── class-example-handler.php    # Business logic
│   └── class-example-api.php        # API implementation
├── admin/                           # Admin interface
│   ├── class-admin.php             # Admin functionality
│   ├── js/                         # Admin JavaScript
│   │   └── admin.js               # Admin scripts
│   ├── css/                        # Admin styles
│   │   └── admin.css             # Admin stylesheets
│   └── views/                      # Admin templates
│       └── settings.php           # Settings page
└── public/                         # Public assets
    ├── js/                         # Public JavaScript
    │   └── client.js              # Client-side code
    └── css/                        # Public styles
        └── style.css              # Public stylesheets
```

2. Basic Module Implementation Example:

```php
<?php
/**
 * Example Module Implementation
 *
 * @package SEWN\WebSockets\Modules\Example
 */

namespace SEWN\WebSockets\Modules\Example;

use SEWN\WebSockets\Module_Base;
use SEWN\WebSockets\Exception;

class Example_Module extends Module_Base {
    /**
     * Get module slug
     *
     * @return string
     */
    public function get_module_slug(): string {
        return 'example';
    }

    /**
     * Module metadata
     *
     * @return array
     */
    public function metadata(): array {
        return [
            'name'        => 'Example Module',
            'version'     => '1.0.0',
            'description' => 'Example module implementation',
            'author'      => 'Your Name',
            'author_uri'  => 'https://example.com',
            'requires'    => [
                'php' => '7.4',
                'wp'  => '5.8'
            ]
        ];
    }

    /**
     * Initialize module
     *
     * @return void
     * @throws Exception
     */
    public function init(): void {
        try {
            // Register hooks
            add_action('sewn_ws_init', [$this, 'setup_module']);
            add_action('sewn_ws_client_connected', [$this, 'handle_connection']);
            add_action('sewn_ws_client_disconnected', [$this, 'handle_disconnection']);

            // Initialize components
            $this->init_protocol();
            $this->init_admin();

        } catch (\Throwable $e) {
            throw new Exception(
                sprintf('Failed to initialize %s module: %s', 
                    $this->get_module_slug(), 
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Check dependencies
     *
     * @return array|bool
     */
    public function check_dependencies() {
        $errors = [];

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = 'PHP 7.4 or higher required';
        }

        // Check WordPress version
        if (version_compare($GLOBALS['wp_version'], '5.8', '<')) {
            $errors[] = 'WordPress 5.8 or higher required';
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Module requirements
     *
     * @return array
     */
    public function requires(): array {
        return [
            'modules' => [],  // Required modules
            'plugins' => [],  // Required plugins
            'php_extensions' => ['json']  // Required PHP extensions
        ];
    }

    /**
     * Admin UI configuration
     *
     * @return array
     */
    public function admin_ui(): array {
        return [
            'menu_title' => 'Example Settings',
            'capability' => 'manage_options',
            'settings' => [
                [
                    'name' => 'example_setting',
                    'label' => 'Example Setting',
                    'type' => 'text',
                    'description' => 'Example setting description',
                    'section' => 'general',
                    'default' => ''
                ]
            ],
            'sections' => [
                [
                    'id' => 'general',
                    'title' => 'General Settings'
                ]
            ]
        ];
    }

    /**
     * Handle module activation
     *
     * @return void
     */
    public function activate(): void {
        // Activation tasks
        update_option('example_module_activated', time());
    }

    /**
     * Handle module deactivation
     *
     * @return void
     */
    public function deactivate(): void {
        // Cleanup tasks
        delete_option('example_module_activated');
    }
}
```

3. Required Components:

a. Core Requirements:
   - PHP 7.4+
   - WordPress 5.8+
   - Proper namespace (SEWN\WebSockets\Modules\{ModuleName})
   - Must extend Module_Base class

b. Required Methods:
   - get_module_slug(): string
   - metadata(): array
   - init(): void
   - check_dependencies(): array|bool

c. Optional Methods:
   - requires(): array
   - admin_ui(): array
   - activate(): void
   - deactivate(): void

d. Required Hooks:
   - sewn_ws_init
   - sewn_ws_client_connected
   - sewn_ws_client_disconnected
   - sewn_ws_register_protocols (if implementing protocol)

4. Error Handling:

```php
try {
    // Potentially dangerous operation
    $result = $this->perform_operation();
    
    if (!$result) {
        throw new Exception('Operation failed');
    }
    
} catch (\Throwable $e) {
    // Log error
    error_log(sprintf(
        '[%s] Module Error: %s', 
        $this->get_module_slug(), 
        $e->getMessage()
    ));
    
    // Notify admin if critical
    add_action('admin_notices', function() use ($e) {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html($e->getMessage())
        );
    });
    
    // Handle gracefully
    return false;
}
```

5. Best Practices:

a. File Organization:
   - One class per file
   - Clear file naming
   - Logical directory structure
   - Separate admin and public assets

b. Code Standards:
   - Follow WordPress Coding Standards
   - Use proper documentation blocks
   - Implement proper error handling
   - Follow security best practices

c. Security:
   - Validate all input
   - Sanitize all output
   - Use nonces for forms
   - Check capabilities
   - Implement rate limiting

d. Performance:
   - Cache expensive operations
   - Optimize database queries
   - Minimize asset loading
   - Use WordPress transients


