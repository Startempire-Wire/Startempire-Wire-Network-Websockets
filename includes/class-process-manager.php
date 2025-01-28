<?php
namespace SEWN\WebSockets;

class Process_Manager {
    const NAMESPACE = 'sewn-ws';
    
    public static function start_server() {
        $command = 'cd ' . escapeshellarg(SEWN_WS_NODE_SERVER) . 
                 ' && npm start -- --port=' . SEWN_WS_PORT;
        exec('pm2 start ' . escapeshellarg($command) . ' --name ' . self::NAMESPACE);
    }
    
    public static function get_connections() {
        return apply_filters('sewn_ws_active_connections', []);
    }
}
