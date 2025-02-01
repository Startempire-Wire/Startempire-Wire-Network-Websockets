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