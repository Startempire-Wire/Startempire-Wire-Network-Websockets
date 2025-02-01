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