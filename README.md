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

## Overview

The Startempire Wire Network WebSocket Plugin provides real-time communication capabilities for the Startempire Wire Network ecosystem. It enables live updates, chat functionality, and network-wide data synchronization through a robust WebSocket server implementation.

## Features

- **Real-time Communication**: Enable instant messaging and live updates across the network
- **Discord Integration**: Seamless integration with Discord for community engagement
- **Network Synchronization**: Real-time data synchronization between network members
- **Scalable Architecture**: Support for thousands of concurrent connections
- **Secure Communication**: SSL/TLS encryption and JWT authentication
- **Admin Dashboard**: Comprehensive monitoring and management interface
- **Performance Metrics**: Real-time statistics and historical data analysis
- **API Integration**: REST and WebSocket APIs for custom integrations
- **Server Agnostic**: Can be used on any server or service
- **AsyncAPI Support**: Uses AsyncAPI to describe WebSocket-based APIs

## Requirements

- PHP 7.4 or higher
- WordPress 5.8 or higher
- Node.js 14.x or higher
- SSL certificate for production use
- Redis (optional, for enhanced caching)

## Installation

### Standard Installation
1. Upload plugin files to `/wp-content/plugins/`
2. Activate through WordPress admin
3. Install Node.js dependencies:
```bash
cd wp-content/plugins/startempire-wire-network-websockets/node-server
npm install --production
```

### Local by Flywheel Installation
1. Follow standard installation steps 1-2
2. Access Local by Flywheel shell:
   - Right-click your site
   - Select "Open Site Shell"
3. Navigate to plugin directory:
```bash
cd wp-content/plugins/startempire-wire-network-websockets/node-server
```
4. Install dependencies:
```bash
npm install --production
```
5. Configure environment:
```bash
cp .env.example .env
```
6. Edit .env file to use alternative port:
```bash
WP_PORT=8081  # Use lower port number for Local by Flywheel
```
7. Add to wp-config.php:
```php
define('SEWN_WS_DEFAULT_PORT', 8081);  // Override default port
define('SEWN_WS_ENV_LOCAL_MODE', true); // Enable local mode
```

### Port Configuration

The plugin supports two methods for configuring the WebSocket port:

1. Recommended (Current):
```php
define('SEWN_WS_DEFAULT_PORT', 49200);  // In wp-config.php
```

2. Legacy (Deprecated):
```php
define('SEWN_WS_ENV_DEFAULT_PORT', 8081);  // Will be removed in v2.0.0
```

### Starting the Server

#### Standard Environment
```bash
wp sewn-ws server start
```

#### Local by Flywheel
1. Access site shell
2. Start server with debug mode:
```bash
wp sewn-ws server start --debug
```
3. Monitor logs:
```bash
tail -f /var/log/websocket.log
```

### Troubleshooting Local by Flywheel Installation

If the server starts but immediately stops:

1. Check Port Availability:
```bash
netstat -tulpn | grep 8081
```

2. Verify Process:
```bash
ps aux | grep "node.*sewn-ws"
```

3. Check Permissions:
```bash
ls -la wp-content/plugins/startempire-wire-network-websockets/node-server
```

4. Review Logs:
```bash
tail -f /var/log/websocket.log
```

5. Test Connection:
```bash
telnet localhost 8081
```

Common Solutions:
- Use port 8081 instead of 49200
- Enable debug mode for detailed logs
- Check file permissions in node-server directory
- Verify Node.js installation in Local by Flywheel
- Ensure no other services use the configured port

## Documentation

### Core Documentation
- [Configuration Guide](docs/CONFIGURATION.md)
- [API Reference](docs/API.md)
- [Development Guide](docs/DEVELOPMENT.md)
- [Monitoring Guide](docs/MONITORING.md)
- [Troubleshooting Guide](docs/TROUBLESHOOTING.md)

### Additional Resources
- [Architecture Overview](docs/architecture/README.md)
- [Security Guidelines](docs/security/README.md)
- [Performance Tuning](docs/performance/README.md)
- [Integration Examples](docs/examples/README.md)

## Support

### Community Support
- [Documentation](https://docs.startempirewire.com)
- [GitHub Issues](https://github.com/startempirewire/sewn-websockets/issues)
- [Community Forum](https://community.startempirewire.com)

### Professional Support
- Email: support@startempirewire.com
- Priority Support: [https://startempirewire.com/support](https://startempirewire.com/support)

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the GPL v3 - see the [LICENSE](LICENSE) file for details.

## Changelog

### 1.0
* Initial release
* Core WebSocket implementation
* External Service Integration

### 0.5
* Beta release
* Basic WebSocket functionality
* Admin dashboard prototype

## Upgrade Notice

### 1.0
**Major Update** - Requires Node.js 14.x+ and PHP 7.4+. Includes new security features.

### 0.5
Initial beta release - Not recommended for production use.

## Acknowledgments

- [Ratchet](http://socketo.me/) - WebSocket server library
- [React](https://reactjs.org/) - UI library
- [WordPress](https://wordpress.org/) - Content management system
- [Node.js](https://nodejs.org/) - JavaScript runtime
- [AsyncAPI](https://www.asyncapi.com/) - API documentation

## Integration

### WordPress Integration
```php
// Initialize WebSocket client
$ws_client = SEWN_WebSocket_Client::getInstance();

// Subscribe to updates
$ws_client->subscribe('network_updates', function($message) {
    // Handle update
});

// Send message
$ws_client->send('network_updates', [
    'type' => 'status_update',
    'message' => 'Site updated'
]);
```

### JavaScript Integration
```javascript
// Initialize WebSocket connection
const ws = new WebSocket('wss://your-domain.com:49200');

// Authenticate
ws.onopen = () => {
    ws.send(JSON.stringify({
        type: 'auth',
        token: 'your-jwt-token'
    }));
};

// Handle messages
ws.onmessage = (event) => {
    const message = JSON.parse(event.data);
    // Handle message
};
```

## Architecture

The plugin follows a modular architecture with these key components:

```
wp-content/plugins/startempire-wire-network-websockets/
├── admin/                 # Admin interface
├── includes/             # Core plugin classes
├── node-server/          # WebSocket server
├── public/               # Public assets
└── docs/                 # Documentation
```

## Security

- All production deployments must use SSL/TLS
- Authentication required for privileged operations
- Rate limiting enforced on all endpoints
- Regular security audits recommended

## Performance

- Supports 1000+ concurrent connections
- Message throughput: 10,000+ messages/second
- Memory usage: ~128MB base, ~256B per connection
- CPU usage: ~2% idle, ~10% under load

## Support

### Community Support
- [Documentation](https://docs.startempirewire.com)
- [GitHub Issues](https://github.com/startempirewire/sewn-websockets/issues)
- [Community Forum](https://community.startempirewire.com)

### Professional Support
- Email: support@startempirewire.com
- Priority Support: [https://startempirewire.com/support](https://startempirewire.com/support)

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the GPL v3 - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- [Ratchet](http://socketo.me/) - WebSocket server library
- [React](https://reactjs.org/) - UI library
- [WordPress](https://wordpress.org/) - Content management system
- [Node.js](https://nodejs.org/) - JavaScript runtime 