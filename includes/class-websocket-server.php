<?php

/**
 * Location: includes/class-websocket-server.php
 * Dependencies: Ratchet library, WebSocket_Handler
 * Classes: WebSocket_Server
 * 
 * Core implementation of WebSocket server using Ratchet framework. Manages port allocation, connection lifecycle, and integration with custom protocol handlers.
 */

namespace SEWN\WebSockets;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use SEWN\WebSockets\WebSocket_Handler;

class WebSocket_Server {
    private $server;
    private $port;
    private $connections = [];
    private $is_running = false;
    
    public function __construct($port = 8080) {
        if (!$this->is_port_available($port)) {
            throw new \Exception(sprintf(__('Port %d is not available', 'sewn-ws'), $port));
        }
        
        $this->port = $port;
        try {
            $this->server = new \Ratchet\Server\IoServer(
                new \Ratchet\Http\HttpServer(
                    new \Ratchet\WebSocket\WsServer(
                        new WebSocket_Handler()
                    )
                ),
                $port
            );
        } catch (\Exception $e) {
            error_log(sprintf('[SEWN WebSocket] Server initialization failed: %s', $e->getMessage()));
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
            $this->server->run();
        } catch (\Exception $e) {
            $this->is_running = false;
            error_log(sprintf('[SEWN WebSocket] Server runtime error: %s', $e->getMessage()));
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
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if (is_resource($connection)) {
            fclose($connection);
            return false;
        }
        return true;
    }
} 