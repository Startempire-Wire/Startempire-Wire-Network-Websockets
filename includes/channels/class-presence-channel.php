<?php

namespace SEWN\WebSockets\Channels;

class Presence_Channel extends Base_Channel {
    private $active_users = [];

    public function process($event) {
        try {
            switch ($event->action) {
                case 'join':
                    $this->handle_join($event->user);
                    break;
                case 'leave':
                    $this->handle_leave($event->user);
                    break;
            }
            $this->stats['messages_processed']++;
            
            return $this->broadcast($this->active_users);
        } catch (\Exception $e) {
            $this->stats['errors']++;
            throw $e;
        }
    }

    public function broadcast($data) {
        return $this->bridge->emit('presence', [
            'type' => 'presence_update',
            'users' => $data,
            'timestamp' => time()
        ]);
    }

    private function handle_join($user) {
        $this->active_users[$user->id] = [
            'id' => $user->id,
            'name' => $user->name,
            'joined_at' => time()
        ];
    }

    private function handle_leave($user) {
        unset($this->active_users[$user->id]);
    }
} 