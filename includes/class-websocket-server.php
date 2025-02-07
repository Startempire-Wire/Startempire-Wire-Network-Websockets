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
use SEWN\WebSockets\WebSocket_Handler;

class WebSocketServer implements MessageComponentInterface {
    private $server;
    private $port;
    private $connections = [];
    private $is_running = false;
    private $clients;
    private $connectionCount = 0;
    
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
        $this->clients = new \SplObjectStorage;
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