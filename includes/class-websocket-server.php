<?php

/**
 * Location: includes/class-websocket-server.php
 * Dependencies: Ratchet library, WebSocket_Handler
 * Classes: WebSocketServer
 * 
 * Core implementation of WebSocket server using Ratchet framework. Manages port allocation, connection lifecycle, and integration with custom protocol handlers.
 */

namespace SEWN\WebSockets;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as ReactLoop;
use React\Socket\Server as ReactServer;
use SEWN\WebSockets\WebSocket_Handler;

class WebSocketServer implements MessageComponentInterface {
    private $server;
    private $loop;
    private $socket;
    private $handler;
    private $port;
    private $connections = [];
    private $is_running = false;
    private $clients;
    private $connectionCount = 0;
    
    /**
     * Initialize a new server process instance
     * 
     * @param int|null $port Port number for WebSocket server
     * @throws \Exception If port is invalid or unavailable
     */
    public function __construct($port = null) {
        try {
            // Get port configuration with deprecation support
            if ($port === null) {
                $port = defined('\SEWN_WS_ENV_DEFAULT_PORT') 
                    ? \SEWN_WS_ENV_DEFAULT_PORT 
                    : \SEWN_WS_DEFAULT_PORT;

                if (defined('\SEWN_WS_ENV_DEFAULT_PORT')) {
                    error_log('[SEWN WebSocket] Warning: Using deprecated SEWN_WS_ENV_DEFAULT_PORT constant');
                }
            }
            
            error_log(sprintf('[SEWN WebSocket] Initializing WebSocket server with port: %d', $port));
            
            // Validate port number
            if (!is_numeric($port) || $port < 1024 || $port > 65535) {
                $error_message = sprintf(
                    __('Invalid port number: %d. Port must be between 1024 and 65535.', 'sewn-ws'),
                    $port
                );
                error_log('[SEWN WebSocket] ' . $error_message);
                throw new \Exception($error_message);
            }
            
            if (!$this->is_port_available($port)) {
                $error_message = sprintf(
                    __('Port %d is already in use. Please choose a different port from range 49152-65535 to avoid conflicts.', 'sewn-ws'), 
                    $port
                );
                error_log('[SEWN WebSocket] ' . $error_message);
                throw new \Exception($error_message);
            }
            
            $this->port = $port;
            error_log('[SEWN WebSocket] Port validated successfully');

            // Initialize server components
            $this->init_server();

        } catch (\Exception $e) {
            error_log('[SEWN WebSocket] Server initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Initialize server components
     */
    private function init_server() {
        try {
            error_log('[SEWN WebSocket] Creating event loop');
            $this->loop = ReactLoop::create();
            
            error_log(sprintf('[SEWN WebSocket] Creating socket server on port %d', $this->port));
            $this->socket = new ReactServer("0.0.0.0:{$this->port}", $this->loop);
            
            // Initialize WebSocket handler
            $this->handler = new WebSocket_Handler();
            
            // Create server stack
            $ws_server = new WsServer($this->handler);
            $http_server = new HttpServer($ws_server);
            
            // Create IoServer with proper socket
            $this->server = new IoServer($http_server, $this->socket, $this->loop);
            
            $this->clients = new \SplObjectStorage;
            
            error_log('[SEWN WebSocket] Server components initialized successfully');
        } catch (\Exception $e) {
            error_log('[SEWN WebSocket] Server component initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function run() {
        if ($this->is_running) {
            return false;
        }
        
        try {
            $this->is_running = true;
            do_action('sewn_ws_server_before_start', $this->port);
            $this->loop->run();
        } catch (\Exception $e) {
            $this->is_running = false;
            error_log('[SEWN WebSocket] Server runtime error: ' . $e->getMessage());
            do_action('sewn_ws_server_error', $e);
            throw $e;
        }
    }

    public function stop() {
        if (!$this->is_running) {
            return false;
        }
        
        try {
            $this->server->socket->close();
            $this->is_running = false;
            do_action('sewn_ws_server_stopped');
            return true;
        } catch (\Exception $e) {
            error_log(sprintf('[SEWN WebSocket] Server stop error: %s', $e->getMessage()));
            return false;
        }
    }

    private function is_port_available($port) {
        // Validate port number first
        if (!is_numeric($port) || $port < 1024 || $port > 65535) {
            throw new \Exception(sprintf(__('Invalid port number: %d. Port must be between 1024 and 65535.', 'sewn-ws'), $port));
        }

        try {
            // Create a socket
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket === false) {
                throw new \Exception("Failed to create socket: " . socket_strerror(socket_last_error()));
            }

            // Set socket options
            socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

            // Attempt to bind to the port
            $result = @socket_bind($socket, '127.0.0.1', $port);
            
            // Clean up
            socket_close($socket);

            if ($result === false) {
                return false; // Port is in use
            }

            return true; // Port is available

        } catch (\Exception $e) {
            error_log(sprintf('[SEWN WebSocket] Port check error: %s', $e->getMessage()));
            throw new \Exception(
                sprintf(__('Failed to check port availability: %s', 'sewn-ws'), $e->getMessage())
            );
        }
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $this->connectionCount++;
        $conn->send(json_encode(['type' => 'connection', 'count' => $this->connectionCount]));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        foreach ($this->clients as $client) {
            if ($client !== $from) {
                $client->send($msg);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        $this->connectionCount--;
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        error_log("WebSocket Error: {$e->getMessage()}");
        $conn->close();
    }

    public function getConnectionCount() {
        return $this->connectionCount;
    }
} 