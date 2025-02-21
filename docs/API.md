# WebSocket Dashboard API Reference

## REST API Endpoints

### Server Management

#### Get Server Status
```http
GET /wp-json/sewn-ws/v1/status
```

Response:
```json
{
  "status": "running",
  "uptime": 3600,
  "connections": 42,
  "memory_usage": "128MB",
  "cpu_usage": "2%"
}
```

#### Start Server
```http
POST /wp-json/sewn-ws/v1/server/start
```

Response:
```json
{
  "success": true,
  "message": "Server started successfully",
  "pid": 1234
}
```

#### Stop Server
```http
POST /wp-json/sewn-ws/v1/server/stop
```

Response:
```json
{
  "success": true,
  "message": "Server stopped successfully"
}
```

### Connection Management

#### List Active Connections
```http
GET /wp-json/sewn-ws/v1/connections
```

Response:
```json
{
  "total": 42,
  "connections": [
    {
      "id": "conn_123",
      "user_id": 456,
      "ip": "192.168.1.1",
      "connected_at": "2024-03-20T10:00:00Z",
      "type": "member"
    }
  ]
}
```

#### Get Connection Details
```http
GET /wp-json/sewn-ws/v1/connections/{connection_id}
```

Response:
```json
{
  "id": "conn_123",
  "user_id": 456,
  "ip": "192.168.1.1",
  "connected_at": "2024-03-20T10:00:00Z",
  "type": "member",
  "messages_sent": 100,
  "messages_received": 80,
  "last_activity": "2024-03-20T10:05:00Z"
}
```

#### Disconnect Client
```http
DELETE /wp-json/sewn-ws/v1/connections/{connection_id}
```

Response:
```json
{
  "success": true,
  "message": "Connection terminated"
}
```

### Statistics

#### Get Real-time Stats
```http
GET /wp-json/sewn-ws/v1/stats/realtime
```

Response:
```json
{
  "timestamp": "2024-03-20T10:00:00Z",
  "connections": {
    "total": 42,
    "authenticated": 40,
    "anonymous": 2
  },
  "messages": {
    "sent": 1000,
    "received": 800,
    "rate": "50/s"
  },
  "memory": {
    "used": "128MB",
    "peak": "256MB",
    "limit": "1GB"
  }
}
```

#### Get Historical Stats
```http
GET /wp-json/sewn-ws/v1/stats/history
```

Parameters:
- `start`: Start timestamp (ISO 8601)
- `end`: End timestamp (ISO 8601)
- `resolution`: Data point interval in seconds

Response:
```json
{
  "start": "2024-03-20T09:00:00Z",
  "end": "2024-03-20T10:00:00Z",
  "resolution": 300,
  "datapoints": [
    {
      "timestamp": "2024-03-20T09:00:00Z",
      "connections": 40,
      "messages_rate": "45/s",
      "memory_usage": "120MB"
    }
  ]
}
```

## WebSocket API

### Connection

#### Connect to Server
```javascript
const ws = new WebSocket('wss://your-domain.com:49200');
```

#### Authentication
```javascript
// After connection established
ws.send(JSON.stringify({
  type: 'auth',
  token: 'your-jwt-token'
}));
```

Response:
```json
{
  "type": "auth_response",
  "success": true,
  "user": {
    "id": 456,
    "role": "member"
  }
}
```

### Message Types

#### Client to Server

##### Subscribe to Channel
```json
{
  "type": "subscribe",
  "channel": "network_updates"
}
```

##### Unsubscribe from Channel
```json
{
  "type": "unsubscribe",
  "channel": "network_updates"
}
```

##### Send Message
```json
{
  "type": "message",
  "channel": "network_updates",
  "content": {
    "type": "status_update",
    "message": "Site updated"
  }
}
```

##### Ping
```json
{
  "type": "ping",
  "timestamp": 1710921600000
}
```

#### Server to Client

##### Channel Message
```json
{
  "type": "channel_message",
  "channel": "network_updates",
  "sender": {
    "id": 456,
    "name": "Member Site"
  },
  "content": {
    "type": "status_update",
    "message": "Site updated"
  },
  "timestamp": 1710921600000
}
```

##### System Message
```json
{
  "type": "system",
  "message": "Server maintenance in 5 minutes",
  "level": "warning"
}
```

##### Pong
```json
{
  "type": "pong",
  "timestamp": 1710921600000
}
```

### Error Handling

#### Error Response Format
```json
{
  "type": "error",
  "code": "AUTH_FAILED",
  "message": "Authentication failed",
  "details": {
    "reason": "Invalid token"
  }
}
```

#### Common Error Codes
- `AUTH_FAILED`: Authentication failure
- `INVALID_MESSAGE`: Message format invalid
- `RATE_LIMITED`: Too many messages
- `CHANNEL_ERROR`: Channel operation failed
- `SERVER_ERROR`: Internal server error

## Events

### Server Events

#### Connection Events
```javascript
ws.onopen = () => {
  console.log('Connected to server');
};

ws.onclose = (event) => {
  console.log('Disconnected:', event.code, event.reason);
};

ws.onerror = (error) => {
  console.error('WebSocket error:', error);
};
```

#### Message Events
```javascript
ws.onmessage = (event) => {
  const message = JSON.parse(event.data);
  switch(message.type) {
    case 'auth_response':
      handleAuth(message);
      break;
    case 'channel_message':
      handleChannelMessage(message);
      break;
    case 'system':
      handleSystemMessage(message);
      break;
  }
};
```

## Rate Limits

### REST API
- Anonymous: 30 requests per minute
- Authenticated: 100 requests per minute
- Admin: 300 requests per minute

### WebSocket
- Message Rate: 100 messages per minute
- Connection Rate: 10 connections per minute per IP
- Subscription Limit: 50 channels per connection

## Security

### Authentication
- JWT tokens required for authenticated connections
- Token format: `Bearer <token>`
- Token lifetime: 1 hour
- Refresh token lifetime: 7 days

### SSL/TLS
- WSS (WebSocket Secure) required in production
- Minimum TLS version: 1.2
- Strong cipher suites enforced

## Support

For API issues:
- API Documentation: https://docs.startempirewire.com/api
- Support Email: api-support@startempirewire.com
- GitHub Issues: https://github.com/startempirewire/sewn-websockets/issues 