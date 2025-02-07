<?php

/**
 * Location: includes/class-websocket-handler.php
 * Dependencies: Ratchet library
 * Classes: WebSocket_Handler
 * 
 * Handles WebSocket connections and message routing. Implements Ratchet's MessageComponentInterface
 * to provide core WebSocket functionality.
 */

namespace SEWN\WebSockets;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class WebSocket_Handler implements MessageComponentInterface {
    protected $clients;
    protected $subscriptions = [];
    protected $logger;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->logger = new Log_Handler();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $this->logger->log("New connection! ({$conn->resourceId})", 'info');
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $numRecv = count($this->clients) - 1;
        $this->logger->log(sprintf('Connection %d sending message "%s" to %d other connection%s',
            $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's'), 'debug');

        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $client->send($msg);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        $this->logger->log("Connection {$conn->resourceId} has disconnected", 'info');
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->logger->log("An error has occurred: {$e->getMessage()}", 'error');
        $conn->close();
    }
} 