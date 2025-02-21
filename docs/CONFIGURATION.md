# WebSocket Dashboard Configuration Guide

## Core Settings

### Server Configuration

#### Port Settings
```php
// Default port configuration
define('SEWN_WS_DEFAULT_PORT', 49200);  // IANA Dynamic Port range
define('SEWN_WS_PORT_MIN', 1024);       // Minimum allowed port
define('SEWN_WS_PORT_MAX', 65535);      // Maximum allowed port
```

#### SSL/TLS Settings
```php
// SSL configuration
define('SEWN_WS_SSL_ENABLED', false);    // Enable/disable SSL
define('SEWN_WS_SSL_CERT', '/path/to/cert.pem');
define('SEWN_WS_SSL_KEY', '/path/to/key.pem');
```

### Environment Configuration

#### Development Mode
```php
// Development environment settings
define('SEWN_WS_ENV_DEV_MODE', true);    // Enable development mode
define('SEWN_WS_ENV_DEBUG', true);       // Enable debug logging
```

#### Production Mode
```php
// Production environment settings
define('SEWN_WS_ENV_DEV_MODE', false);   // Disable development mode
define('SEWN_WS_ENV_DEBUG', false);      // Disable debug logging
```

## Dashboard Settings

### Display Configuration
```php
// Dashboard display settings
define('SEWN_WS_STATS_UPDATE_INTERVAL', 10000);  // Update interval in ms
define('SEWN_WS_HISTORY_MAX_POINTS', 100);       // Maximum data points
```

### Metric Configuration
```php
// Metric retention settings
define('SEWN_WS_METRIC_RETENTION', 3600);        // 1 hour retention
define('SEWN_WS_METRIC_RESOLUTION', 10);         // 10 second intervals
```

## Module Settings

### Discord Integration
```php
// Discord module configuration
define('SEWN_WS_DISCORD_ENABLED', true);
define('SEWN_WS_DISCORD_BOT_TOKEN', 'your-token');
define('SEWN_WS_DISCORD_GUILD_ID', 'your-guild-id');
```

### Network Integration
```php
// Network module configuration
define('SEWN_WS_MEMBER_AUTH', true);             // Enable member auth
define('SEWN_WS_DATA_DISTRIBUTION', true);       // Enable data sync
define('SEWN_WS_SYNC_INTERVAL', 300);           // 5 minute sync
```

## Security Settings

### Authentication
```php
// Authentication configuration
define('SEWN_WS_AUTH_TIMEOUT', 3600);           // Token lifetime
define('SEWN_WS_AUTH_REFRESH', 604800);         // Refresh token lifetime
```

### Rate Limiting
```php
// Rate limiting configuration
define('SEWN_WS_RATE_LIMIT_WINDOW', 60);        // Window in seconds
define('SEWN_WS_RATE_LIMIT_MAX', 100);          // Max requests per window
```

## Performance Settings

### Connection Pooling
```php
// Connection pool configuration
define('SEWN_WS_MAX_CONNECTIONS', 1000);        // Maximum connections
define('SEWN_WS_CONNECTION_TIMEOUT', 30000);    // Timeout in ms
```

### Memory Management
```php
// Memory management settings
define('SEWN_WS_MAX_MEMORY', '1G');            // Maximum memory usage
define('SEWN_WS_CLEANUP_INTERVAL', 300);       // Cleanup interval
```

## WordPress Integration

### Admin Interface
```php
// Admin interface settings
define('SEWN_WS_ADMIN_MENU_SLUG', 'sewn-ws-dashboard');
define('SEWN_WS_TEXT_DOMAIN', 'sewn-ws');
```

### Plugin Dependencies
```php
// Required WordPress version
define('SEWN_WS_WP_MIN_VERSION', '5.8');
define('SEWN_WS_PHP_MIN_VERSION', '7.4');
```

## Environment Variables

The following environment variables can be used to override default settings:

```bash
# Server Configuration
WP_PORT=49200                  # WebSocket server port
WP_HOST=localhost              # WebSocket server host
WP_DEBUG=false                 # Debug mode
WP_SSL_ENABLED=false          # SSL/TLS mode

# Authentication
WP_JWT_SECRET=your-secret     # JWT signing secret
WP_AUTH_TIMEOUT=3600         # Authentication timeout

# Performance
WP_MAX_CONNECTIONS=1000      # Maximum connections
WP_MEMORY_LIMIT=1G          # Memory limit
```

## Configuration Files

### Server Configuration
Location: `config/server.json`
```json
{
  "port": 49200,
  "host": "0.0.0.0",
  "ssl": {
    "enabled": false,
    "cert": null,
    "key": null
  },
  "auth": {
    "timeout": 3600,
    "refresh": 604800
  }
}
```

### Module Configuration
Location: `config/modules.json`
```json
{
  "discord": {
    "enabled": true,
    "botToken": "your-token",
    "guildId": "your-guild-id"
  },
  "network": {
    "memberAuth": true,
    "dataSync": true,
    "syncInterval": 300
  }
}
```

## Best Practices

### Security
1. Always use SSL in production
2. Rotate JWT secrets regularly
3. Implement rate limiting
4. Use secure defaults

### Performance
1. Configure appropriate connection limits
2. Set reasonable timeouts
3. Enable garbage collection
4. Monitor memory usage

### Development
1. Enable debug mode locally
2. Use development SSL certificates
3. Configure lower rate limits
4. Enable detailed logging

## Support

For configuration issues:
- Documentation: https://docs.startempirewire.com/config
- Support Email: support@startempirewire.com
- GitHub Issues: https://github.com/startempirewire/sewn-websockets/issues 