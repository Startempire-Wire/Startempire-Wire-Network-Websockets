# WebSocket Dashboard Troubleshooting Guide

## Common Issues and Solutions

### 1. Connection Issues

#### SSL/TLS Configuration Problems
- **Symptom**: Mixed content errors or connection failures
- **Cause**: Mismatch between page protocol (HTTP/HTTPS) and WebSocket protocol (WS/WSS)
- **Solution**:
  1. Check if page is served over HTTPS
  2. Verify SSL configuration in WebSocket server
  3. Ensure SSL certificates are properly installed
  4. Match protocols between page and WebSocket server

#### Port Availability
- **Symptom**: Server fails to start or bind to port
- **Cause**: Port already in use or restricted
- **Solution**:
  1. Verify port 49200 is available
  2. Check for other services using the port
  3. Try alternative port in range 49152-65535
  4. Ensure port is not blocked by firewall

#### Authentication Failures
- **Symptom**: "Unauthorized" or "Invalid token" errors
- **Cause**: JWT token validation issues
- **Solution**:
  1. Check system clock synchronization
  2. Verify JWT_SECRET matches between WP and WebSocket server
  3. Ensure token hasn't expired
  4. Check user permissions in WordPress

### 2. Server Management Issues

#### Server Start/Stop Problems
- **Symptom**: Server fails to start or stop cleanly
- **Cause**: Process management issues
- **Solution**:
  1. Check Node.js installation and version
  2. Verify file permissions
  3. Check server logs for specific errors
  4. Ensure no zombie processes remain

#### Environment Configuration
- **Symptom**: Server starts but behaves incorrectly
- **Cause**: Missing or incorrect environment variables
- **Solution**:
  1. Verify WP_PORT setting
  2. Check WP_HOST configuration
  3. Validate SSL settings if enabled
  4. Review debug logs for environment issues

#### Process Management
- **Symptom**: Server becomes unresponsive
- **Cause**: Resource exhaustion or process conflicts
- **Solution**:
  1. Monitor server resources
  2. Check for memory leaks
  3. Verify process cleanup on shutdown
  4. Review process manager logs

### 3. Performance Issues

#### Memory Usage
- **Symptom**: High memory consumption or leaks
- **Cause**: Connection accumulation or resource leaks
- **Solution**:
  1. Monitor memory usage trends
  2. Implement connection limits
  3. Enable garbage collection
  4. Clean up disconnected clients

#### Connection Management
- **Symptom**: Too many connections or slow response
- **Cause**: Connection pool exhaustion
- **Solution**:
  1. Set appropriate connection limits
  2. Implement connection timeouts
  3. Monitor active connections
  4. Clean up stale connections

#### Message Throughput
- **Symptom**: Message delays or drops
- **Cause**: Queue buildup or processing bottlenecks
- **Solution**:
  1. Monitor message queue depth
  2. Implement rate limiting
  3. Optimize message processing
  4. Scale server resources if needed

### 4. Debug Tools

#### Server Logs
- Location: `/var/log/websocket.log`
- Contains detailed error messages and stack traces
- Use for investigating startup and runtime issues

#### Status Monitoring
- Dashboard provides real-time metrics
- Monitor connection counts, memory usage, and errors
- Use for proactive issue detection

#### Network Testing
```bash
# Test port availability
telnet yoursite.com 49200

# Check SSL configuration
openssl s_client -connect yoursite.com:49200
```

### 5. Emergency Recovery

If the server becomes unresponsive:

1. Stop the server:
```bash
wp sewn-ws server stop
```

2. Clean up processes:
```bash
pkill -f "node.*sewn-ws"
```

3. Remove PID file:
```bash
rm /tmp/websocket.pid
```

4. Restart the server:
```bash
wp sewn-ws server start
```

## Support Resources

- Documentation: `/wp-content/plugins/startempire-wire-network-websockets/docs/`
- Issue Tracker: GitHub repository
- Support Email: support@startempirewire.com 