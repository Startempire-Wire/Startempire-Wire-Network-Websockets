<?php
namespace SEWN\WebSockets;

class Server_Manager {
    private $recovery_attempts = 0;
    private const MAX_RECOVERY_ATTEMPTS = 3;

    public function ensure_server_health() {
        try {
            $status = $this->check_server_status();
            
            if (!$status->healthy) {
                $this->handle_unhealthy_server($status);
            }
            
            $this->recovery_attempts = 0;
            return true;
            
        } catch (Exception $e) {
            $this->log_error($e);
            return $this->attempt_recovery();
        }
    }

    private function handle_unhealthy_server($status) {
        if ($status->memory_usage > 80) {
            $this->restart_server();
        }
        
        if ($status->connection_errors > 50) {
            $this->clear_connection_pool();
        }
        
        if ($status->uptime > 86400) { // 24 hours
            $this->schedule_restart();
        }
    }

    private function attempt_recovery() {
        if ($this->recovery_attempts >= self::MAX_RECOVERY_ATTEMPTS) {
            $this->notify_admin('Server recovery failed after 3 attempts');
            return false;
        }
        
        $this->recovery_attempts++;
        $this->restart_server();
        return $this->check_server_status()->healthy;
    }

    public function start() {
        try {
            $output = [];
            $return_var = 0;
            $command = 'npm run start --prefix ' . escapeshellarg(SEWN_WS_NODE_DIR);
            error_log("Attempting to start server with command: " . $command);
            
            exec($command, $output, $return_var);
            error_log("Node.js start output: " . print_r($output, true));
            error_log("Exit code: " . $return_var);

            if ($return_var !== 0) {
                $errorMessage = "Node.js Start Failed (Code $return_var): " . implode("\n", $output);
                error_log($errorMessage);
                throw new Exception($errorMessage);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Server Start Exception: " . $e->getMessage());
            error_log("PHP Version: " . phpversion());
            error_log("WP Version: " . get_bloginfo('version'));
            error_log("Node Path: " . SEWN_WS_NODE_DIR);
            error_log("Current User: " . get_current_user_id());
            error_log("Server OS: " . PHP_OS);
            throw $e;
        }
    }
} 