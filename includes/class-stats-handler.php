<?php
namespace SEWN\WebSockets;

class Stats_Handler {
    private $stats_file;
    private $cache_duration = 5; // seconds
    private $stats = [];

    public function __construct() {
        add_action('wp_ajax_sewn_ws_get_stats', [$this, 'handle_stats_request']);
        add_action('wp_ajax_sewn_ws_get_channel_stats', [$this, 'handle_channel_stats']);
        add_action('rest_api_init', [$this, 'register_endpoints']);
        $this->stats_file = SEWN_WS_PATH . 'logs/stats.json';
    }

    public function register_endpoints() {
        register_rest_route('sewn-ws/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    public function get_stats() {
        return [
            'connections' => $this->get_connection_count(),
            'memory_usage' => $this->get_memory_usage(),
            'uptime' => $this->get_uptime()
        ];
    }

    public function handle_stats_request() {
        $server_controller = Server_Controller::get_instance();
        
        if (!$server_controller->is_server_running()) {
            wp_send_json_error(['message' => 'Server not running'], 409);
        }
        
        check_ajax_referer('sewn_ws_admin');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        try {
            $stats = $this->collect_server_stats();
            wp_send_json_success($stats);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function handle_channel_stats() {
        check_ajax_referer('sewn_ws_admin', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        try {
            $stats = $this->collect_channel_stats();
            wp_send_json_success($stats);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    private function collect_server_stats() {
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

    private function collect_channel_stats() {
        try {
            $response = $this->make_server_request('stats/channels');
            return $this->process_channel_stats($response);
        } catch (\Exception $e) {
            throw new \Exception('Failed to collect channel statistics: ' . $e->getMessage());
        }
    }

    private function fetch_live_stats() {
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

    private function process_channel_stats($response) {
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

    private function make_server_request($endpoint) {
        $port = get_option('sewn_ws_port', 3000);
        $host = get_option('sewn_ws_host', '127.0.0.1');
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

    private function cache_stats($stats) {
        $stats['timestamp'] = time();
        file_put_contents($this->stats_file, json_encode($stats));
    }

    private function get_connection_count() {
        return $this->stats['connections']['current'] ?? 0;
    }

    private function get_memory_usage() {
        return $this->stats['memory']['used'] ?? 0;
    }

    private function get_uptime() {
        return $this->stats['uptime'] ?? 0;
    }
} 