<?php
/**
 * Location: includes/
 * Dependencies: Bridge class, Channel implementations
 * Variables: $bridge, $channels
 * Classes: Events
 * 
 * Orchestrates real-time event distribution through WebSocket channels. Manages channel registration, statistics collection, and admin interface updates for monitoring network activity.
 */
namespace SEWN\WebSockets;

use SEWN\WebSockets\Channels\Message_Channel;
use SEWN\WebSockets\Channels\Presence_Channel;
use SEWN\WebSockets\Channels\Status_Channel;

class Events {
    private $bridge;
    private $channels = [];
    
    public function __construct(Bridge $bridge) {
        $this->bridge = $bridge;
        $this->init_channels();
        $this->register_admin_hooks();
    }

    public function init_channels() {
        $this->channels = [
            'message' => new Message_Channel($this->bridge),
            'presence' => new Presence_Channel($this->bridge),
            'status' => new Status_Channel($this->bridge)
        ];
    }

    public function handle_event($event_type, $data) {
        if (!isset($this->channels[$event_type])) {
            throw new \Exception("Unknown event type: $event_type");
        }
        
        $result = $this->channels[$event_type]->process($data);
        $this->update_admin_stats();
        
        return $result;
    }

    private function register_admin_hooks() {
        add_action('wp_ajax_sewn_ws_get_channel_stats', [$this, 'get_channel_stats']);
    }

    public function get_channel_stats() {
        check_ajax_referer('sewn_ws_admin');
        
        $stats = [];
        foreach ($this->channels as $name => $channel) {
            $stats[$name] = $channel->getStats();
        }
        
        wp_send_json_success($stats);
    }

    private function update_admin_stats() {
        $stats = [];
        foreach ($this->channels as $name => $channel) {
            $stats[$name] = $channel->getStats();
        }
        
        set_transient('sewn_ws_channel_stats', $stats, 30);
    }
} 