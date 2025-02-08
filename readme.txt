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

6. Configure firewall rules to allow WebSocket traffic on port 8080
7. Enable TLS encryption in plugin settings

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
   WS_PORT=8080
   JWT_SECRET=your-secret-key
   REDIS_URL=redis://localhost:6379
4. Start service:
   wp sewn-ws server start

3. CONFIGURATION
----------------

3.1 wp-config.php Settings
define('SEWN_WS_ENCRYPTION_KEY', 'your-encryption-key');
define('SEWN_WS_MAX_CONNECTIONS', 1000);

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
3. Test with: telnet yoursite.com 8080

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

2. Settings System:
   Each module can define its settings using admin_ui():
   ```php
   public function admin_ui(): array {
       return [
           'menu_title' => 'Module Settings',
           'capability' => 'manage_options',
           'settings' => [
               [
                   'name' => 'setting_name',
                   'label' => 'Setting Label',
                   'type' => 'text|password|select|textarea|checkbox',
                   'description' => 'Setting description',
                   'section' => 'section_id',
                   'options' => [] // For select type
               ]
           ],
           'sections' => [
               [
                   'id' => 'section_id',
                   'title' => 'Section Title',
                   'callback' => [$this, 'render_section']
               ]
           ]
       ];
   }
   ```

3. Standard Configuration Pattern:
   ```php
   public function init_config(): void {
       $this->config = [
           'version' => '1.0',
           'min_php' => '7.4',
           'settings' => get_option('sewn_ws_' . $this->get_module_slug() . '_settings', [])
       ];
   }
   ```

4. Message Handling Pattern:
   ```php
   public function handle_message($message, $context): array {
       if (!$this->validate_message($message)) {
           return $this->handle_error('Invalid message format');
       }
       
       $message_type = $message['type'] ?? 'unknown';
       $user_data = $context['user_data'] ?? [];
       
       switch ($message_type) {
           case 'your_message_type':
               return $this->handle_specific_message($message, $user_data);
           default:
               return $this->handle_error('Unsupported message type');
       }
   }
   ```

5. Critical Requirements:
   - Must implement activate()/deactivate() for lifecycle management
   - Sanitization callbacks required for all admin settings
   - Protocol clients must be dependency-injected
   - Class verification in requires() must use FQCN:
     ['class' => 'Full\Namespace\Class_Name']

6. Protocol Requirements:
   - Must implement message validation layer
   - Required to handle 3 core message types:
     1. Connection lifecycle events
     2. Protocol-specific operations
     3. Error handling responses
   - Standard message format:
     ```php
     [
         'type' => string,      // Message type identifier
         'data' => array,       // Message payload
         'user_data' => array,  // User context data
         'timestamp' => int     // Message timestamp
     ]
     ```
   - Standard response format:
     ```php
     [
         'status' => string,    // 'success' or 'error'
         'data' => array,       // Response payload
         'timestamp' => int     // Response timestamp
     ]
     ```

7. Security Mandates:
   - All admin settings must include:
     * type-specific sanitization
     * capability checks
     * nonce verification
   - Protocol handlers must:
     * Validate message origins
     * Verify network access
     * Sanitize all input data
     * Use WordPress security functions
     * Implement rate limiting

8. Example Structure:
   ```
   modules/{module}/
   ├── class-{module}-module.php      # Core module implementation
   ├── class-{module}-protocol.php    # WebSocket protocol handler
   ├── class-{module}-handler.php     # Optional business logic handler
   └── config/
       └── default-settings.json      # Configuration presets
   ```

9. Required Hooks:
   - sewn_ws_register_protocols → Protocol registration
   - sewn_ws_client_connected → Connection handling
   - sewn_ws_auth_validation → Authentication flows
   - sewn_ws_init → Protocol initialization
   - sewn_ws_client_config → Client configuration

10. Best Practices:
    1. Implement message validation interface
    2. Use separate handler classes for complex logic
    3. Include protocol version in all messages
    4. Add admin debug tools for connection inspection
    5. Implement circuit breakers for external services
    6. Use base class helper methods for consistency:
       - handle_error() for error responses
       - format_response() for success responses
       - validate_message() for input validation

Documentation Validation:
✓ Matches Discord implementation patterns
✓ Covers all security aspects from reference module
✓ Specifies exact hook requirements
✓ Requires proper class verification
✓ Enforces lifecycle management

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


