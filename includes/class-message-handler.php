<?php

namespace SEWN\WebSockets;

class Message_Handler {
    private $protocols = [];
    private $bridge;
    
    public function __construct() {
        $this->bridge = new Bridge();
        $this->register_default_protocols();
    }

    public function register_protocol($name, $protocol) {
        if (!($protocol instanceof Protocol_Base)) {
            throw new \InvalidArgumentException('Protocol must extend Protocol_Base');
        }
        $this->protocols[$name] = $protocol;
    }

    public function handle_message($client_id, $message) {
        // Extract protocol from message
        $protocol_name = $message['protocol'] ?? 'default';
        
        // Get protocol handler
        $protocol = $this->get_protocol($protocol_name);
        
        return $protocol->handle_message($message, [
            'client_id' => $client_id,
            'bridge' => $this->bridge
        ]);
    }

    private function get_protocol($name) {
        if (!isset($this->protocols[$name])) {
            return $this->protocols['default'];
        }
        return $this->protocols[$name];
    }

    private function register_default_protocols() {
        // Register basic protocol handler
        $this->register_protocol('default', new Default_Protocol());
        
        // Allow other plugins to register protocols
        do_action('sewn_ws_register_protocols', $this);
    }
}

// Base protocol class that others will extend
abstract class Protocol_Base {
    abstract public function handle_message($message, $context);
    
    protected function distribute_message($bridge, $message, $options) {
        return $bridge->broadcast('message', [
            'payload' => $message,
            'options' => $options
        ]);
    }
}

// Default basic protocol
class Default_Protocol extends Protocol_Base {
    public function handle_message($message, $context) {
        return $this->distribute_message(
            $context['bridge'],
            $message,
            ['priority' => 'normal']
        );
    }
} 