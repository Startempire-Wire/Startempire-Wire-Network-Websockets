<?php

/**
 * Location: includes/class-server-process.php
 * Dependencies: Process_Manager
 * Classes: Server_Process
 * 
 * Manages individual WebSocket server processes, including lifecycle management,
 * status monitoring, and error handling.
 */

namespace SEWN\WebSockets;

class Server_Process {
    private $pid = null;
    private $port;
    private $last_error = '';
    private $status = 'stopped';

    public function __construct($port = 8080) {
        $this->port = $port;
    }

    public function start() {
        if ($this->is_running()) {
            $this->last_error = 'Server already running';
            return false;
        }

        $node_script = plugin_dir_path(dirname(__FILE__)) . 'node-server/server.js';
        $command = sprintf(
            'node %s --port=%d > /dev/null 2>&1 & echo $!',
            escapeshellarg($node_script),
            $this->port
        );

        $output = [];
        exec($command, $output);

        if (!empty($output)) {
            $this->pid = (int)$output[0];
            $this->status = 'running';
            return true;
        }

        $this->last_error = 'Failed to start server process';
        return false;
    }

    public function stop() {
        if (!$this->is_running()) {
            return true;
        }

        if (posix_kill($this->pid, SIGTERM)) {
            $this->pid = null;
            $this->status = 'stopped';
            return true;
        }

        $this->last_error = 'Failed to stop server process';
        return false;
    }

    public function is_running() {
        if (!$this->pid) {
            return false;
        }

        return posix_kill($this->pid, 0);
    }

    public function get_pid() {
        return $this->pid;
    }

    public function get_status() {
        return $this->status;
    }

    public function get_last_error() {
        return $this->last_error;
    }
} 