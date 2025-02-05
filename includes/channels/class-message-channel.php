<?php
/**
 * Location: Message handling subsystem
 * Dependencies: Base_Channel class, WebSocket bridge interface
 * Variables: Message_Channel class, message validation methods
 * 
 * Processes real-time message broadcasting through WebSocket connections. Implements message
 * validation and rate limiting. Tracks delivery metrics for monitoring purposes.
 */

 namespace SEWN\WebSockets\Channels;

 class Message_Channel extends Base_Channel {
    public function process($event) {
        try {
            $this->validate_message($event);
            $this->stats['messages_processed']++;
            
            return $this->broadcast([
                'type' => 'message',
                'data' => $event->data,
                'user' => $event->user,
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            $this->stats['errors']++;
            throw $e;
        }
    }

    public function broadcast($data) {
        return $this->bridge->emit('message', $data);
    }

    private function validate_message($event) {
        if (empty($event->data)) {
            throw new \Exception('Empty message not allowed');
        }
    }
} 