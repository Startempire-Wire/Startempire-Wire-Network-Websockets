<?php

/**
 * Location: includes/class-server-status.php
 * Dependencies: Process checking via pgrep, WordPress options API
 * Classes: SEWN_WS_Server_Status
 * 
 * Provides real-time server health monitoring for WebSocket infrastructure. Integrates with system processes to track running status, active connections, and resource utilization for network diagnostics.
 */

namespace SEWN\WebSockets;
class SEWN_WS_Server_Status {
    public function get_server_health() {
        return [
            'status' => $this->check_process(),
            'connections' => $this->get_connection_count(),
            'uptime' => $this->get_uptime(),
            'memory' => $this->get_memory_usage()
        ];
    }

    private function check_process() {
        exec('pgrep -f "node server.js"', $output, $result);
        return $result === 0 ? 'running' : 'stopped';
    }
} 