<?php
/**
 * Location: includes/class-stats-collector.php
 * Dependencies: Redis, Process_Manager
 * Classes: Stats_Collector
 * 
 * Aggregates real-time performance metrics from WebSocket server and infrastructure. Provides fallback mechanisms when Redis unavailable to ensure continuous monitoring.
 */

namespace SEWN\WebSockets;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class Stats_Collector {
    /**
     * @var \Redis Redis connection instance
     */
    private $redis;

    /**
     * @var string Redis prefix for stats keys
     */
    private $prefix = 'sewn_ws_stats:';

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_redis();
    }

    /**
     * Initialize Redis connection
     */
    private function init_redis() {
        try {
            $this->redis = new \Redis();
            $redis_url = defined('SEWN_WS_REDIS_URL') ? SEWN_WS_REDIS_URL : 'redis://localhost:6379';
            $parsed = parse_url($redis_url);
            
            $this->redis->connect(
                $parsed['host'] ?? 'localhost',
                $parsed['port'] ?? 6379
            );

            if (isset($parsed['pass'])) {
                $this->redis->auth($parsed['pass']);
            }
        } catch (\Exception $e) {
            error_log('Redis connection failed: ' . $e->getMessage());
            $this->redis = null;
        }
    }

    /**
     * Collect current metrics
     *
     * @return array Server metrics
     */
    public function collect_metrics() {
        if (!$this->redis) {
            return $this->get_fallback_metrics();
        }

        $metrics = [
            'connections' => (int) $this->redis->get($this->prefix . 'connections') ?? 0,
            'memory_usage' => $this->get_memory_usage(),
            'cpu_usage' => $this->get_cpu_usage(),
            'bandwidth' => [
                'in' => (int) $this->redis->get($this->prefix . 'bandwidth_in') ?? 0,
                'out' => (int) $this->redis->get($this->prefix . 'bandwidth_out') ?? 0
            ],
            'events' => [
                'total' => (int) $this->redis->get($this->prefix . 'events_total') ?? 0,
                'rate' => $this->calculate_event_rate()
            ],
            'timestamp' => time()
        ];

        // Store historical data
        $this->store_historical_metrics($metrics);

        return $metrics;
    }

    /**
     * Store metrics for historical tracking
     *
     * @param array $metrics Current metrics
     */
    private function store_historical_metrics($metrics) {
        if (!$this->redis) {
            return;
        }

        $history_key = $this->prefix . 'history:' . date('Y-m-d');
        $this->redis->rPush($history_key, json_encode($metrics));
        $this->redis->expire($history_key, 86400 * 7); // Keep 7 days of history
    }

    /**
     * Get memory usage of Node.js process
     *
     * @return array Memory usage statistics
     */
    private function get_memory_usage() {
        $process_manager = new Process_Manager();
        if (!$process_manager->is_running()) {
            return ['used' => 0, 'total' => 0];
        }

        $pid = (int) file_get_contents(SEWN_WEBSOCKETS_PATH . 'tmp/server.pid');
        $status = file_get_contents("/proc/$pid/status");
        
        preg_match('/VmRSS:\s+(\d+)\s+kB/', $status, $matches);
        $used = isset($matches[1]) ? (int) $matches[1] * 1024 : 0;

        return [
            'used' => $used,
            'total' => $this->get_system_memory()
        ];
    }

    /**
     * Get total system memory
     *
     * @return int Total system memory in bytes
     */
    private function get_system_memory() {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches);
        return isset($matches[1]) ? (int) $matches[1] * 1024 : 0;
    }

    /**
     * Get CPU usage of Node.js process
     *
     * @return float CPU usage percentage
     */
    private function get_cpu_usage() {
        $process_manager = new Process_Manager();
        if (!$process_manager->is_running()) {
            return 0.0;
        }

        $pid = (int) file_get_contents(SEWN_WEBSOCKETS_PATH . 'tmp/server.pid');
        $cmd = "ps -p $pid -o %cpu | tail -n 1";
        return (float) trim(shell_exec($cmd));
    }

    /**
     * Calculate events per second
     *
     * @return float Events per second
     */
    private function calculate_event_rate() {
        if (!$this->redis) {
            return 0.0;
        }

        $current = (int) $this->redis->get($this->prefix . 'events_total');
        $previous = (int) $this->redis->get($this->prefix . 'events_total_previous');
        $time_diff = time() - ((int) $this->redis->get($this->prefix . 'events_last_check') ?: time());

        if ($time_diff > 0) {
            $rate = ($current - $previous) / $time_diff;
            
            // Update previous values
            $this->redis->set($this->prefix . 'events_total_previous', $current);
            $this->redis->set($this->prefix . 'events_last_check', time());
            
            return round($rate, 2);
        }

        return 0.0;
    }

    /**
     * Get fallback metrics when Redis is unavailable
     *
     * @return array Basic metrics
     */
    private function get_fallback_metrics() {
        return [
            'connections' => 0,
            'memory_usage' => $this->get_memory_usage(),
            'cpu_usage' => $this->get_cpu_usage(),
            'bandwidth' => ['in' => 0, 'out' => 0],
            'events' => ['total' => 0, 'rate' => 0],
            'timestamp' => time()
        ];
    }
} 