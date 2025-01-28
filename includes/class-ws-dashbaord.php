<?php
namespace SEWN\WebSockets;

class Dashboard {
    public function add_menu() {
        add_menu_page(
            __('WebSocket Server', 'sewn-ws'),
            __('WS Server', 'sewn-ws'),
            'manage_options',
            'sewn-ws-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-networking'
        );
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'sewn-ws-admin',
            SEWN_WS_URL . 'assets/css/admin.css',
            [],
            SEWN_WS_VERSION
        );
    }
}
