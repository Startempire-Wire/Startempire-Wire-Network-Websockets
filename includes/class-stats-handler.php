<?php

/**
 * Location: includes/class-stats-handler.php
 * Dependencies: REST API, AJAX handlers
 * Classes: Stats_Handler
 * 
 * Processes statistics requests and formats data for admin dashboard consumption. Implements caching layer to optimize performance of frequent metrics queries.
 */

namespace SEWN\WebSockets;

/**
 * Handles statistics collection, storage, and retrieval for the WebSocket server.
 */
class Stats_Handler {
    /**
     * Instance of this class.
     *
     * @var Stats_Handler
     */
    private static $instance = null;

    /**
     * Stats buffer for temporary storage.
     *
     * @var array
     */
    private $stats_buffer = [];

    /**
     * Cache duration in seconds.
     *
     * @var int
     */
    private $cache_duration = 5;

    /**
     * Stats file location.
     *
     * @var string
     */
    private $stats_file;

    private $cache_key = 'sewn_ws_stats';
    private $cache_expiration = 30; // seconds

    /**
     * Get instance of this class.
     *
     * @return Stats_Handler
     */
    public static function get_instance(): Stats_Handler {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Initialize stats file path
        $this->stats_file = SEWN_WS_PATH . 'logs/stats.json';

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Initialize hooks and actions.
     */
    private function init_hooks(): void {
        // REST API endpoints
        add_action('rest_api_init', [$this, 'register_endpoints']);

        // AJAX handlers
        add_action('wp_ajax_sewn_ws_get_stats', [$this, 'handle_stats_request']);
        add_action('wp_ajax_sewn_ws_get_channel_stats', [$this, 'handle_channel_stats']);

        // WebSocket event tracking
        add_action('sewn_ws_server_started', [$this, 'initialize_stats']);
        add_action('sewn_ws_client_connect', [$this, 'track_connection']);
        add_action('sewn_ws_client_disconnect', [$this, 'track_disconnection']);
        add_action('sewn_ws_message_received', [$this, 'track_message']);
        add_action('sewn_ws_message_sent', [$this, 'track_message_sent']);
        add_action('sewn_ws_error', [$this, 'track_error']);

        // Stats persistence
        add_action('sewn_ws_persist_stats', [$this, 'persist_stats']);
        if (!wp_next_scheduled('sewn_ws_persist_stats')) {
            wp_schedule_event(time(), 'every_minute', 'sewn_ws_persist_stats');
        }
    }

    /**
     * Register REST API endpoints.
     */
    public function register_endpoints(): void {
        register_rest_route(SEWN_WS_REST_NAMESPACE, '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    /**
     * Initialize stats storage.
     */
    public function initialize_stats(): void {
        $this->stats_buffer = [
            'connections' => [
                'active' => 0,
                'total' => 0,
                'peak' => 0,
                'dropped' => 0
            ],
            'messages' => [
                'sent' => 0,
                'received' => 0,
                'errors' => 0
            ],
            'bandwidth' => [
                'incoming' => 0,
                'outgoing' => 0
            ],
            'latency' => [
                'average' => 0,
                'peak' => 0
            ],
            'timestamp' => time()
        ];

        $this->persist_stats();
    }

    /**
     * Track new connection.
     *
     * @param string $client_id Client identifier.
     */
    public function track_connection(string $client_id): void {
        $this->stats_buffer['connections']['active']++;
        $this->stats_buffer['connections']['total']++;
        
        if ($this->stats_buffer['connections']['active'] > $this->stats_buffer['connections']['peak']) {
            $this->stats_buffer['connections']['peak'] = $this->stats_buffer['connections']['active'];
        }

        $this->maybe_persist_stats();
    }

    /**
     * Track client disconnection.
     *
     * @param string $client_id Client identifier.
     */
    public function track_disconnection(string $client_id): void {
        $this->stats_buffer['connections']['active']--;
        $this->maybe_persist_stats();
    }

    /**
     * Track received message.
     *
     * @param array $message Message data.
     */
    public function track_message(array $message): void {
        $this->stats_buffer['messages']['received']++;
        $this->stats_buffer['bandwidth']['incoming'] += strlen(json_encode($message));
        $this->maybe_persist_stats();
    }

    /**
     * Track sent message.
     *
     * @param array $message Message data.
     */
    public function track_message_sent(array $message): void {
        $this->stats_buffer['messages']['sent']++;
        $this->stats_buffer['bandwidth']['outgoing'] += strlen(json_encode($message));
        $this->maybe_persist_stats();
    }

    /**
     * Track error occurrence.
     *
     * @param \Throwable $error Error object.
     */
    public function track_error(\Throwable $error): void {
        $this->stats_buffer['messages']['errors']++;
        $this->maybe_persist_stats();
    }

    /**
     * Handle stats request via AJAX.
     */
    public function handle_stats_request(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        try {
            $stats = $this->collect_server_stats();
            wp_send_json_success($stats);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle channel stats request via AJAX.
     */
    public function handle_channel_stats(): void {
        check_ajax_referer(\SEWN_WS_NONCE_ACTION, '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $stats = $this->collect_channel_stats();
            wp_send_json_success($stats);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Collect server statistics.
     *
     * @return array Server statistics.
     * @throws \Exception If collection fails.
     */
    private function collect_server_stats(): array {
        $cached_stats = $this->get_cached_stats();
        if ($cached_stats !== false) {
            return $cached_stats;
        }

        try {
            $stats = $this->fetch_live_stats();
            $this->cache_stats($stats);
            return $stats;
        } catch (\Exception $e) {
            throw new \Exception('Failed to collect server statistics: ' . $e->getMessage());
        }
    }

    /**
     * Collect channel statistics.
     *
     * @return array Channel statistics.
     * @throws \Exception If collection fails.
     */
    private function collect_channel_stats(): array {
        try {
            $response = $this->make_server_request('stats/channels');
            return $this->process_channel_stats($response);
        } catch (\Exception $e) {
            throw new \Exception('Failed to collect channel statistics: ' . $e->getMessage());
        }
    }

    /**
     * Fetch live statistics from server.
     *
     * @return array Live statistics.
     * @throws \Exception If fetch fails.
     */
    private function fetch_live_stats(): array {
        $response = $this->make_server_request('stats');
        
        if (empty($response)) {
            throw new \Exception('Empty response from WebSocket server');
        }

        return [
            'connections' => [
                'current' => $response['currentConnections'] ?? 0,
                'peak' => $response['peakConnections'] ?? 0,
                'total' => $response['totalConnections'] ?? 0
            ],
            'memory' => [
                'used' => $response['memoryUsage'] ?? 0,
                'peak' => $response['peakMemoryUsage'] ?? 0
            ],
            'uptime' => $response['uptime'] ?? 0,
            'bandwidth' => [
                'in' => $response['bytesReceived'] ?? 0,
                'out' => $response['bytesSent'] ?? 0
            ],
            'messages' => [
                'received' => $response['messagesReceived'] ?? 0,
                'sent' => $response['messagesSent'] ?? 0
            ],
            'errors' => [
                'count' => $response['errorCount'] ?? 0,
                'rate' => $response['errorRate'] ?? 0
            ],
            'timestamp' => time()
        ];
    }

    /**
     * Process channel statistics.
     *
     * @param array $response Server response.
     * @return array Processed channel statistics.
     */
    private function process_channel_stats(array $response): array {
        $channels = [];
        
        foreach ($response['channels'] ?? [] as $channel => $data) {
            $channels[$channel] = [
                'subscribers' => $data['subscribers'] ?? 0,
                'messages' => $data['messageCount'] ?? 0,
                'lastActivity' => $data['lastActivity'] ?? 0
            ];
        }

        return [
            'channels' => $channels,
            'total' => [
                'channels' => count($channels),
                'subscribers' => array_sum(array_column($channels, 'subscribers')),
                'messages' => array_sum(array_column($channels, 'messages'))
            ]
        ];
    }

    /**
     * Make request to WebSocket server.
     *
     * @param string $endpoint API endpoint.
     * @return array Server response.
     * @throws \Exception If request fails.
     */
    private function make_server_request(string $endpoint): array {
        $port = get_option(SEWN_WS_OPTION_PORT, SEWN_WS_DEFAULT_PORT);
        $host = '127.0.0.1';
        $url = "http://{$host}:{$port}/api/{$endpoint}";

        $response = wp_remote_get($url, [
            'timeout' => 5,
            'headers' => [
                'X-API-Key' => get_option('sewn_ws_api_key')
            ]
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from server');
        }

        return $data;
    }

    /**
     * Get cached statistics.
     *
     * @return array|false Cached stats or false if not available.
     */
    private function get_cached_stats() {
        if (!file_exists($this->stats_file)) {
            return false;
        }

        $stats = json_decode(file_get_contents($this->stats_file), true);
        if (!$stats || !isset($stats['timestamp'])) {
            return false;
        }

        if (time() - $stats['timestamp'] > $this->cache_duration) {
            return false;
        }

        return $stats;
    }

    /**
     * Cache statistics.
     *
     * @param array $stats Statistics to cache.
     */
    private function cache_stats(array $stats): void {
        $stats['timestamp'] = time();
        file_put_contents($this->stats_file, json_encode($stats));
    }

    /**
     * Check if stats should be persisted.
     */
    private function maybe_persist_stats(): void {
        if (count($this->stats_buffer) >= SEWN_WS_STATS_MAX_POINTS) {
            $this->persist_stats();
        }
    }

    /**
     * Persist stats to database.
     */
    public function persist_stats(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sewn_ws_stats';
        
        // Ensure we have a timestamp
        $this->stats_buffer['timestamp'] = $this->stats_buffer['timestamp'] ?? time();

        $wpdb->insert(
            $table_name,
            [
                'stats_data' => json_encode($this->stats_buffer),
                'created_at' => date('Y-m-d H:i:s', $this->stats_buffer['timestamp'])
            ],
            [
                '%s',
                '%s'
            ]
        );

        // Reset buffer after persistence
        $this->initialize_stats();
    }

    /**
     * Get current statistics.
     *
     * @return array Current statistics.
     */
    public function get_current_stats(): array {
        return $this->stats_buffer;
    }

    /**
     * Get historical statistics.
     *
     * @param int $hours Number of hours of history to retrieve.
     * @return array Historical statistics.
     */
    public function get_historical_stats(int $hours = 24): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sewn_ws_stats';
        $time_limit = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT stats_data, created_at FROM {$table_name} WHERE created_at >= %s ORDER BY created_at DESC",
                $time_limit
            )
        );

        return array_map(function($row) {
            return [
                'data' => json_decode($row->stats_data, true),
                'timestamp' => strtotime($row->created_at)
            ];
        }, $results);
    }

    public function collect_real_time_stats() {
        $server_status = $this->get_server_status();
        
        // If server is not running, return minimal stats
        if ($server_status !== 'running') {
            return [
                'server_status' => $server_status,
                'connections' => [
                    'active' => 0,
                    'total' => $this->stats_buffer['connections']['total'] ?? 0,
                    'peak' => $this->stats_buffer['connections']['peak'] ?? 0
                ],
                'memory_usage' => 0,
                'message_rate' => 0,
                'uptime' => 0
            ];
        }

        // Server is running, get live stats
        $memory_usage = $this->get_memory_usage();
        $uptime = $this->get_uptime();
        
        // Update peak connections if necessary
        $active_connections = $this->count_active_connections();
        if ($active_connections > ($this->stats_buffer['connections']['peak'] ?? 0)) {
            $this->stats_buffer['connections']['peak'] = $active_connections;
        }

        return [
            'server_status' => $server_status,
            'connections' => [
                'active' => $active_connections,
                'total' => $this->stats_buffer['connections']['total'] ?? 0,
                'peak' => $this->stats_buffer['connections']['peak'] ?? 0
            ],
            'memory_usage' => $memory_usage,
            'message_rate' => $this->get_message_rate(),
            'uptime' => $uptime
        ];
    }
    
    private function count_active_connections() {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sewn_ws_connections 
             WHERE disconnected_at IS NULL"
        );
    }
    
    private function get_total_connections() {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sewn_ws_connections"
        );
    }
    
    private function get_peak_connections() {
        return get_option('sewn_ws_peak_connections', 0);
    }
    
    private function get_message_rate() {
        $current_rate = get_transient('sewn_ws_message_rate');
        return $current_rate ? $current_rate : 0;
    }

    private function get_server_status() {
        $pid_file = SEWN_WS_PATH . 'tmp/server.pid';
        
        if (!file_exists($pid_file)) {
            return 'stopped';
        }

        $pid = (int) file_get_contents($pid_file);
        if (!$pid || !posix_kill($pid, 0)) {
            return 'stopped';
        }

        // Check if process is actually our Node.js server
        $command = "ps -p $pid -o command=";
        $output = [];
        exec($command, $output);
        
        if (empty($output) || strpos($output[0], 'server.js') === false) {
            return 'stopped';
        }

        return 'running';
    }

    private function get_memory_usage() {
        $pid_file = SEWN_WS_PATH . 'tmp/server.pid';
        
        if (!file_exists($pid_file)) {
            return 0;
        }

        $pid = (int) file_get_contents($pid_file);
        if (!$pid) {
            return 0;
        }

        // Get memory usage in bytes
        $command = "ps -o rss= -p $pid";
        $output = [];
        exec($command, $output);
        
        if (empty($output)) {
            return 0;
        }

        // Convert KB to bytes
        return (int) $output[0] * 1024;
    }

    private function get_uptime() {
        $pid_file = SEWN_WS_PATH . 'tmp/server.pid';
        
        if (!file_exists($pid_file)) {
            return 0;
        }

        $pid = (int) file_get_contents($pid_file);
        if (!$pid) {
            return 0;
        }

        // Get process start time
        $stat = @file_get_contents("/proc/$pid/stat");
        if (!$stat) {
            return 0;
        }

        $stat_array = explode(' ', $stat);
        $start_time = isset($stat_array[21]) ? (int) $stat_array[21] : 0;
        
        if (!$start_time) {
            return 0;
        }

        // Calculate uptime in seconds
        $system_start = (int) file_get_contents('/proc/uptime');
        return $system_start - ($start_time / 100);
    }
} 