<?php

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