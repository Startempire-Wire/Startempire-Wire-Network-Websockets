<?php
/**
 * Location: includes/channels/
 * Dependencies: Channel interface
 * Variables: $subscribers, $stats, $bridge
 * Classes: Base_Channel implements Channel
 * 
 * Abstract base class defining common channel functionality for WebSocket communication. Implements core subscription management and statistics tracking for all channel implementations.
 */
namespace SEWN\WebSockets\Channels;

use SEWN\WebSockets\Interfaces\Channel;

abstract class Base_Channel implements Channel {
    protected $subscribers = [];
    protected $stats = [];
    protected $bridge;

    public function __construct($bridge) {
        $this->bridge = $bridge;
        $this->init_stats();
    }

    protected function init_stats() {
        $this->stats = [
            'messages_processed' => 0,
            'subscribers' => 0,
            'errors' => 0
        ];
    }

    public function subscribe($client_id) {
        if (!in_array($client_id, $this->subscribers)) {
            $this->subscribers[] = $client_id;
            $this->stats['subscribers']++;
        }
    }

    public function unsubscribe($client_id) {
        $key = array_search($client_id, $this->subscribers);
        if ($key !== false) {
            unset($this->subscribers[$key]);
            $this->stats['subscribers']--;
        }
    }

    public function getStats() {
        return $this->stats;
    }

    abstract public function process($event);
    abstract public function broadcast($data);
} 