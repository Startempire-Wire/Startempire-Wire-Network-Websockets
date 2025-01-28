<?php
namespace SEWN\WebSockets;

class REST_API {
    public function register_routes() {
        register_rest_route('sewn-ws/v1', '/config', [
            'methods' => 'GET',
            'callback' => [$this, 'get_config'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
    }

    private function check_permissions() {
        return current_user_can('manage_options');
    }
}
