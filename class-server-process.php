    /**
     * Initialize a new server process instance
     * 
     * @param int $port Port number for WebSocket server. Defaults to 49200 (IANA Dynamic Port range)
     *                  to avoid conflicts with common development ports.
     */
    public function __construct($port = 49200) {
        $this->port = $port;
        $this->node_script = plugin_dir_path(dirname(__FILE__)) . 'node-server/server.js';
        $this->log_file = plugin_dir_path(dirname(__FILE__)) . 'node-server/server.log';
        $this->pid_file = plugin_dir_path(dirname(__FILE__)) . 'node-server/server.pid';
        $this->stats_file = plugin_dir_path(dirname(__FILE__)) . 'node-server/stats.json';
    } 