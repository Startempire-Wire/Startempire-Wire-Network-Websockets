# WebSocket Dashboard Development Guide

## Architecture Overview

### 1. Component Structure

#### Core Components
```
startempire-wire-network-websockets/
├── admin/                 # Admin interface
├── includes/             # Core functionality
├── node-server/          # WebSocket server
└── public/               # Public assets
```

#### Key Classes
- `WebSocketServer`: Core server implementation
- `Admin_UI`: Dashboard interface
- `Server_Controller`: Process management
- `Module_Registry`: Plugin extensibility

### 2. Development Environment

#### Requirements
- PHP 7.4+
- Node.js 14+
- WordPress 5.8+
- SSL certificates for HTTPS

#### Local Setup
```bash
# Clone repository
git clone https://github.com/startempirewire/sewn-websockets.git

# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
```

### 3. Coding Standards

#### PHP Standards
- Follow WordPress Coding Standards
- PSR-4 autoloading
- PHPDoc documentation
- Type hinting where possible

#### JavaScript Standards
- ES6+ syntax
- Module pattern
- JSDoc documentation
- Error handling

### 4. Dashboard Development

#### UI Components
```javascript
// Example component structure
class DashboardComponent {
    constructor() {
        this.initializeState();
        this.bindEvents();
        this.setupWebSocket();
    }

    initializeState() {
        this.state = {
            connections: 0,
            errors: 0,
            memory: 0
        };
    }

    bindEvents() {
        // Event binding implementation
    }

    setupWebSocket() {
        // WebSocket connection setup
    }
}
```

#### State Management
```javascript
// Example state updates
class StateManager {
    updateMetrics(data) {
        this.state = {
            ...this.state,
            ...data
        };
        this.notifySubscribers();
    }

    notifySubscribers() {
        this.subscribers.forEach(callback => callback(this.state));
    }
}
```

### 5. WebSocket Implementation

#### Server Configuration
```php
// Example server configuration
class WebSocketConfig {
    public static function getConfig() {
        return [
            'port' => defined('SEWN_WS_PORT') ? SEWN_WS_PORT : 49200,
            'host' => defined('SEWN_WS_HOST') ? SEWN_WS_HOST : 'localhost',
            'ssl' => [
                'enabled' => defined('SEWN_WS_SSL') ? SEWN_WS_SSL : false,
                'cert' => SEWN_WS_SSL_CERT ?? null,
                'key' => SEWN_WS_SSL_KEY ?? null
            ]
        ];
    }
}
```

#### Protocol Handlers
```php
// Example protocol implementation
class CustomProtocol extends Protocol_Base {
    public function handleMessage($client, $message) {
        try {
            // Message handling logic
            return $this->success(['status' => 'processed']);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
```

### 6. Testing

#### Unit Tests
```php
// Example test case
class WebSocketTest extends WP_UnitTestCase {
    public function test_server_initialization() {
        $server = new WebSocketServer();
        $this->assertTrue($server->isInitialized());
        $this->assertEquals(49200, $server->getPort());
    }
}
```

#### Integration Tests
```php
// Example integration test
class DashboardIntegrationTest extends WP_UnitTestCase {
    public function test_dashboard_metrics() {
        $admin = new Admin_UI();
        $metrics = $admin->getMetrics();
        $this->assertArrayHasKey('connections', $metrics);
        $this->assertArrayHasKey('memory', $metrics);
    }
}
```

### 7. Error Handling

#### Client-side Errors
```javascript
// Example error handling
class ErrorHandler {
    static handle(error) {
        console.error('WebSocket Error:', error);
        
        // Log to server
        this.logError(error);
        
        // Update UI
        this.updateErrorDisplay(error);
        
        // Attempt recovery
        this.attemptRecovery();
    }
}
```

#### Server-side Errors
```php
// Example error logging
class ServerErrorHandler {
    public static function log($error, $context = []) {
        error_log(sprintf(
            '[SEWN WebSocket] %s | Context: %s',
            $error,
            json_encode($context)
        ));
    }
}
```

### 8. Performance Optimization

#### Connection Pooling
```php
// Example connection pool
class ConnectionPool {
    private $pool = [];
    private $maxSize = 1000;

    public function acquire() {
        if (count($this->pool) >= $this->maxSize) {
            throw new Exception('Connection pool exhausted');
        }
        // Connection acquisition logic
    }

    public function release($connection) {
        // Connection release logic
    }
}
```

#### Memory Management
```php
// Example memory monitoring
class MemoryMonitor {
    public static function check() {
        $usage = memory_get_usage(true);
        $limit = ini_get('memory_limit');
        
        if ($usage > self::bytesFromPhpIni($limit) * 0.9) {
            self::triggerCleanup();
        }
    }
}
```

### 9. Security

#### Authentication
```php
// Example authentication middleware
class WebSocketAuth {
    public static function validateToken($token) {
        try {
            $decoded = JWT::decode($token, SEWN_WS_JWT_KEY, ['HS256']);
            return self::validateClaims($decoded);
        } catch (Exception $e) {
            return false;
        }
    }
}
```

#### SSL/TLS
```php
// Example SSL configuration
class SSLConfig {
    public static function getContext() {
        return [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
    }
}
```

## Best Practices

### 1. Code Organization
- Use meaningful namespaces
- Follow SOLID principles
- Implement design patterns
- Document public APIs

### 2. Performance
- Implement caching
- Use connection pooling
- Optimize memory usage
- Profile code regularly

### 3. Security
- Validate all input
- Implement rate limiting
- Use secure defaults
- Regular security audits

### 4. Maintenance
- Write maintainable code
- Document changes
- Version control
- Automated testing

## Contributing

1. Fork the repository
2. Create feature branch
3. Write tests
4. Submit pull request

## Support

- Developer Docs: https://docs.startempirewire.com/dev
- API Reference: https://api.startempirewire.com
- GitHub Issues: https://github.com/startempirewire/sewn-websockets/issues 