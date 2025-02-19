<?php
/**
 * Bootstrap file for testing the websocket server
 * 
 * This file is used to bootstrap the websocket server for testing purposes.
 * It is used to create a new websocket client and connect to the server.
 * 
 */

namespace SEWN\WebSockets\Tests;

// Require composer autoloader for PHPUnit dependencies
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../../../../wp-load.php';
require_once __DIR__ . '/../includes/class-websocket-server.php';

use PHPUnit\Framework\TestCase;
use Ratchet\Client\Connection;
use Ratchet\Client\WebSocketClient;

use SEWN\WebSockets\WebSocketServer;

/**
 * WebSocket Server Test Class
 * 
 * @requires PHP 7.4
 * @requires extension sockets
 */
class WebSocketTest extends TestCase {
    protected static $server;
    
    /**
     * Test port using IANA Dynamic Port range (49152-65535)
     * Port 49200 chosen to avoid conflicts with common development ports
     */
    protected static $port = 49200;

    public static function setUpBeforeClass(): void {
        self::$server = new WebSocketServer(self::$port);
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