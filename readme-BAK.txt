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

   - class-{module}-protocol.php (Protocol Handler)
     * Extends Protocol_Base
     * Implements WebSocket protocol handling
     * Required methods:
       - register(): void
       - handle_message($message, $context): array
       - register_protocol($handler): void
       - init_config(): void
       - add_protocol_config($config): array

   - class-{module}-handler.php (Business Logic)
     * Optional but recommended for complex modules
     * Handles message processing and business logic
     * Recommended methods:
       - process_message(): array
       - validate_message(): bool
       - handle_error(): array

2. Configuration Management:
   Each module uses the centralized Config class for settings:

   ```php
   // Getting module settings
   $value = Config::get_module_setting('module_slug', 'setting_name', $default);

   // Setting module settings
   Config::set_module_setting('module_slug', 'setting_name', $value);

   // Checking for legacy settings
   if (Config::has_legacy_settings('module_slug')) {
       // Handle migration
   }
   ```

   Legacy Settings Support:
   - Automatic detection of old format settings
   - Transparent migration to new format
   - Cleanup of legacy options after migration
   - Visual indicators in admin UI

3. Settings System:
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
   - number: Numeric input
   - url: URL input field
   - textarea: Multi-line text input
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
      - Each setting can define a sanitization callback
      - Built-in validation for common field types
      - Errors are displayed inline with fields
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

   4. Integration with Module_Settings_Base:
      - Extends core WordPress Settings API
      - Handles automatic section registration
      - Manages settings page rendering
      - Provides consistent styling and layout
      - Supports custom field types
      - Handles AJAX updates
      - Manages form submissions
      - Provides validation hooks

   Each module automatically receives these base settings and standardized UI components:
   Each module automatically receives these base settings:

   1. Core Settings Tab:
      - Module Information (status indicator, version, author)
      - Access Control (access level selection)
      - Debug Settings (debug mode toggle, log level selection)

   2. Performance Tab:
      - Cache Settings (enable/disable, cache duration)
      - Rate Limits (enable/disable, max requests, time window)

   3. Module Settings Tab:
      - Custom module-specific settings
      - Legacy settings migration interface (if applicable)

4. Migration Support:
   Modules with legacy settings get automatic migration support:
   ```php
   // In Config class
   private static $legacy_map = [
       'module_slug' => [
           'new_key' => 'old_option_name',
           // ... more mappings
       ]
   ];
   ```

   Migration Process:
   1. Automatic detection of legacy settings
   2. Transparent value retrieval from both formats
   3. Migration on first save or manual trigger
   4. Scheduled cleanup of old options

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


