<?php
/**
 * Bootstrap file for testing the websocket server
 * 
 * This file is used to bootstrap the websocket server for testing purposes.
 * It is used to create a new websocket client and connect to the server.
 * 
 */

namespace SEWN\WebSockets\Tests;

use PHPUnit\Framework\TestCase;
use Ratchet\Client\Connection;
use Ratchet\Client\WebSocketClient;

require_once __DIR__ . '/../../../../wp-load.php';
require_once __DIR__ . '/../includes/class-websocket-server.php';

use SEWN\WebSockets\WebSocketServer;

class WebSocketTest extends TestCase {
    protected static $server;
    protected static $port = 8080;

    public static function setUpBeforeClass(): void {
        self::$server = new WebSocketServer();
    }

    public function testConnectionHandling() {
        $clients = new \ReflectionProperty(self::$server, 'clients');
        $clients->setAccessible(true);
        
        $this->assertCount(0, $clients->getValue(self::$server));
        
        // Simulate connection
        $mockConn = $this->createMock(\Ratchet\ConnectionInterface::class);
        self::$server->onOpen($mockConn);
        
        $this->assertCount(1, $clients->getValue(self::$server));
        
        // Simulate disconnection
        self::$server->onClose($mockConn);
        $this->assertCount(0, $clients->getValue(self::$server));
    }

    public function testMessageBroadcasting() {
        $sender = $this->createMock(\Ratchet\ConnectionInterface::class);
        $receiver = $this->createMock(\Ratchet\ConnectionInterface::class);
        
        $receiver->expects($this->once())
                 ->method('send')
                 ->with($this->equalTo('test message'));

        $clients = new \ReflectionProperty(self::$server, 'clients');
        $clients->setAccessible(true);
        $clients->setValue(self::$server, new \SplObjectStorage());
        $clients->getValue(self::$server)->attach($sender);
        $clients->getValue(self::$server)->attach($receiver);

        self::$server->onMessage($sender, 'test message');
    }
} 