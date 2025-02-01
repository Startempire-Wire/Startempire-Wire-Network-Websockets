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

    public function render_dashboard() {
        ?>
        <div class="wrap sewn-ws-dashboard-container">
            <h1>WebSocket Server Management</h1>
            
            <div class="card">
                <h2>Server Controls</h2>
                <button id="start-server" class="button button-primary">Start</button>
                <button id="stop-server" class="button button-secondary">Stop</button>
                <span id="server-status"></span>
            </div>

            <div class="card">
                <h2>Real-time Statistics</h2>
                <div id="network-stats">
                    <p>Connections: <span class="connection-count">0</span></p>
                    <p>Bandwidth: <span class="bandwidth">0KB/s</span></p>
                </div>
            </div>
        </div>
        <?php
    }
}
