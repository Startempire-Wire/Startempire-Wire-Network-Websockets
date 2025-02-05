<?php
/**
 * Location: includes/class-statistics.php
 * Dependencies: WordPress cron, options API
 * Classes: Statistics
 * 
 * Tracks connection metrics and event patterns for network analytics. Maintains historical data through WordPress storage with automated hourly data pruning.
 */

namespace SEWN\WebSockets;

class Statistics {
    private const STATS_OPTION = 'sewn_ws_statistics';
    private const HOURLY_STATS = 'sewn_ws_hourly_stats';
    private const MAX_HOURLY_RECORDS = 168; // 7 days

    public function __construct() {
        add_action('sewn_ws_log_event', [$this, 'log_event'], 10, 2);
        add_action('sewn_ws_client_connect', [$this, 'track_connection']);
        add_action('sewn_ws_client_disconnect', [$this, 'track_disconnection']);
        
        // Hourly cleanup
        if (!wp_next_scheduled('sewn_ws_hourly_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'sewn_ws_hourly_cleanup');
        }
        add_action('sewn_ws_hourly_cleanup', [$this, 'cleanup_old_stats']);
    }

    public function get_current_stats() {
        $stats = get_option(self::STATS_OPTION, [
            'connections' => 0,
            'total_connections' => 0,
            'messages_sent' => 0,
            'errors' => 0,
            'uptime_start' => 0
        ]);

        return array_merge($stats, [
            'uptime' => $stats['uptime_start'] ? time() - $stats['uptime_start'] : 0
        ]);
    }

    public function log_event($type, $data) {
        $stats = $this->get_current_stats();
        
        switch ($type) {
            case 'connection':
                $stats['total_connections']++;
                $stats['connections']++;
                break;
                
            case 'disconnection':
                $stats['connections'] = max(0, $stats['connections'] - 1);
                break;
                
            case 'message':
                $stats['messages_sent']++;
                break;
                
            case 'error':
                $stats['errors']++;
                break;
        }

        update_option(self::STATS_OPTION, $stats);
        $this->update_hourly_stats($type);
    }

    public function track_connection() {
        do_action('sewn_ws_log_event', 'connection', [
            'timestamp' => current_time('mysql')
        ]);
    }

    public function track_disconnection() {
        do_action('sewn_ws_log_event', 'disconnection', [
            'timestamp' => current_time('mysql')
        ]);
    }

    private function update_hourly_stats($type) {
        $hour = date('Y-m-d H:00:00');
        $hourly_stats = get_option(self::HOURLY_STATS, []);
        
        if (!isset($hourly_stats[$hour])) {
            $hourly_stats[$hour] = [
                'connections' => 0,
                'messages' => 0,
                'errors' => 0
            ];
        }

        switch ($type) {
            case 'connection':
                $hourly_stats[$hour]['connections']++;
                break;
            case 'message':
                $hourly_stats[$hour]['messages']++;
                break;
            case 'error':
                $hourly_stats[$hour]['errors']++;
                break;
        }

        update_option(self::HOURLY_STATS, $hourly_stats);
    }

    public function cleanup_old_stats() {
        $hourly_stats = get_option(self::HOURLY_STATS, []);
        $cutoff = date('Y-m-d H:00:00', strtotime('-7 days'));
        
        foreach ($hourly_stats as $hour => $stats) {
            if ($hour < $cutoff) {
                unset($hourly_stats[$hour]);
            }
        }

        update_option(self::HOURLY_STATS, $hourly_stats);
    }

    public function get_hourly_stats($hours = 24) {
        $hourly_stats = get_option(self::HOURLY_STATS, []);
        $cutoff = date('Y-m-d H:00:00', strtotime("-{$hours} hours"));
        
        return array_filter($hourly_stats, function($hour) use ($cutoff) {
            return $hour >= $cutoff;
        }, ARRAY_FILTER_USE_KEY);
    }
} 