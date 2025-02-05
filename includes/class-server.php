<?php

/**
 * Location: includes/class-server.php
 * Dependencies: Node.js server, WordPress process management
 * Classes: Server_Manager
 * 
 * Manages lifecycle operations for WebSocket server including start/stop commands. Bridges WordPress admin controls with Node.js backend through system process management and PID tracking.
 */

namespace SEWN\WebSockets;

class Server_Manager {
    public function start() {
        // Connect with Node.js server
        $cmd = "nohup node ".plugin_dir_path(__FILE__)."node-server/index.js > ".WP_CONTENT_DIR."/sewn-ws.log 2>&1 & echo $!";
        $pid = shell_exec($cmd);
        update_option('sewn_ws_pid', $pid);
    }
    
    public function stop() {
        $pid = get_option('sewn_ws_pid');
        if($pid) {
            shell_exec("kill -9 $pid");
            delete_option('sewn_ws_pid');
        }
    }
} 