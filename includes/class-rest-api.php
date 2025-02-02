<?php
namespace SEWN\WebSockets;

class REST_API {
    public function register_routes() {
        register_rest_route('sewn-ws/v1', '/config', [
            'methods' => 'GET',
            'callback' => [$this, 'get_config'],
            'permission_callback' => [$this, 'check_permissions']
        ]);

        register_rest_route('sewn-ws/v1', '/server', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'handle_server_action'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => [
                    'action' => [
                        'required' => true,
                        'enum' => ['start', 'stop', 'restart']
                    ]
                ]
            ],
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_server_status'],
                'permission_callback' => [$this, 'check_permissions']
            ]
        ]);

        register_rest_route('sewn-ws/v1', '/auth', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_jwt'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('sewn-ws/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_live_stats'],
            'permission_callback' => [$this, 'check_stats_permissions']
        ]);

        register_rest_route('sewn-ws/v1', '/server/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_server_status'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
    }

    private function check_permissions() {
        return current_user_can('manage_options');
    }

    public function get_config() {
        // Existing config method
        // ... existing code ...
    }

    public function handle_server_action($request) {
        $action = $request->get_param('action');
        $node = new Node_Check();
        
        try {
            switch ($action) {
                case 'start':
                    $result = $node->start_server();
                    break;
                case 'stop':
                    $result = $node->stop_server();
                    break;
                case 'restart':
                    $result = $node->restart_server();
                    break;
            }

            return rest_ensure_response([
                'success' => true,
                'action' => $action,
                'status' => $result
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'server_action_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_server_status() {
        $node = new Node_Check();
        $status = $node->check_server_status();
        
        return rest_ensure_response([
            'running' => $status['running'],
            'version' => $status['version'],
            'uptime' => $status['uptime'] ?? 0,
            'connections' => Node_Check::get_connection_count()
        ]);
    }

    public function generate_jwt(WP_REST_Request $request) {
        // JWT generation logic
        return rest_ensure_response([
            'token' => $jwt,
            'expires' => 3600
        ]);
    }

    public function get_live_stats() {
        $stats = [
            'connections' => Node_Check::get_connection_count(),
            'throughput' => Node_Check::get_message_throughput(),
            'rooms' => Node_Check::get_active_rooms(),
            'system' => Node_Check::get_system_stats()
        ];
        return rest_ensure_response($stats);
    }

    public function check_stats_permissions() {
        return current_user_can('manage_options');
    }
}
