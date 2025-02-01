<?php
namespace SEWN\WebSockets;

class Server_Controller {
    private static $instance = null;
    private $node_binary = null;
    private $server_script;
    private $pid_file;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_ajax_sewn_ws_server_control', [$this, 'handle_server_control']);
        add_action('wp_ajax_sewn_ws_get_stats', [$this, 'handle_stats_request']);
        add_action('wp_ajax_sewn_ws_get_channel_stats', [$this, 'handle_channel_stats']);

        $this->server_script = SEWN_WS_PATH . 'server/server.js';
        $this->pid_file = SEWN_WS_PATH . 'server/server.pid';
    }

    public function handle_server_control() {
        check_ajax_referer('sewn_ws_control', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $command = sanitize_text_field($_POST['command'] ?? '');
        $valid_commands = ['start', 'stop', 'restart'];
        
        if (!in_array($command, $valid_commands)) {
            wp_send_json_error(['message' => 'Invalid command']);
        }

        try {
            // Only try to get node binary when actually executing a command
            if ($this->node_binary === null) {
                $this->node_binary = $this->get_node_binary();
            }
            
            $result = $this->execute_server_command($command);
            wp_send_json_success([
                'message' => "Server {$command} successful",
                'status' => $this->get_server_status()
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    private function execute_server_command($command) {
        switch ($command) {
            case 'start':
                return $this->start_server();
            case 'stop':
                return $this->stop_server();
            case 'restart':
                $this->stop_server();
                sleep(2); // Wait for server to fully stop
                return $this->start_server();
            default:
                throw new \Exception('Invalid command');
        }
    }

    private function start_server() {
        if ($this->is_server_running()) {
            throw new \Exception('Server is already running');
        }

        $cmd = sprintf(
            '%s %s > %s 2>&1 & echo $! > %s',
            escapeshellcmd($this->node_binary),
            escapeshellarg($this->server_script),
            escapeshellarg(SEWN_WS_PATH . 'logs/server.log'),
            escapeshellarg($this->pid_file)
        );

        exec($cmd, $output, $return_var);

        if ($return_var !== 0) {
            throw new \Exception('Failed to start server');
        }

        // Verify server started successfully
        sleep(2);
        if (!$this->is_server_running()) {
            throw new \Exception('Server failed to start');
        }

        return true;
    }

    private function stop_server() {
        if (!$this->is_server_running()) {
            return true;
        }

        $pid = (int) file_get_contents($this->pid_file);
        if ($pid) {
            if (PHP_OS_FAMILY === 'Windows') {
                exec("taskkill /F /PID $pid 2>&1", $output, $return_var);
            } else {
                exec("kill $pid 2>&1", $output, $return_var);
            }

            if ($return_var !== 0) {
                throw new \Exception('Failed to stop server: ' . implode("\n", $output));
            }

            @unlink($this->pid_file);
        }

        return true;
    }

    private function is_server_running() {
        if (!file_exists($this->pid_file)) {
            return false;
        }

        $pid = (int) file_get_contents($this->pid_file);
        if (!$pid) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            exec("tasklist /FI \"PID eq $pid\" 2>&1", $output);
            return count($output) > 1;
        } else {
            exec("ps -p $pid", $output, $return_var);
            return $return_var === 0;
        }
    }

    public function get_server_status() {
        $running = $this->is_server_running();
        $pid = $running ? (int) file_get_contents($this->pid_file) : null;
        
        return [
            'running' => $running,
            'pid' => $pid,
            'uptime' => $running ? $this->get_server_uptime($pid) : 0,
            'memory' => $running ? $this->get_server_memory($pid) : 0
        ];
    }

    private function get_server_uptime($pid) {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows doesn't provide easy access to process start time
            return 0;
        } else {
            exec("ps -o etimes= -p $pid 2>&1", $output, $return_var);
            return $return_var === 0 ? (int) trim($output[0]) : 0;
        }
    }

    private function get_server_memory($pid) {
        if (PHP_OS_FAMILY === 'Windows') {
            exec("wmic process where ProcessId=$pid get WorkingSetSize 2>&1", $output);
            return isset($output[1]) ? (int) $output[1] : 0;
        } else {
            exec("ps -o rss= -p $pid 2>&1", $output, $return_var);
            return $return_var === 0 ? ((int) trim($output[0]) * 1024) : 0;
        }
    }

    private function get_node_binary() {
        // First check if there's a custom path set in the options
        $custom_path = get_option('sewn_ws_node_path');
        if ($custom_path && file_exists($custom_path)) {
            return $custom_path;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            // Check common Windows paths
            $possible_paths = [
                'node.exe',
                'C:\\Program Files\\nodejs\\node.exe',
                'C:\\Program Files (x86)\\nodejs\\node.exe'
            ];
            
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
        } else {
            // Check common Unix paths
            $possible_paths = [
                '/usr/local/bin/node',
                '/usr/bin/node',
                '/opt/local/bin/node',
                '/usr/local/bin/nodejs',
                '/usr/bin/nodejs'
            ];
            
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
            
            // Try using which command as fallback
            exec('which node 2>&1', $output, $return_var);
            if ($return_var === 0) {
                return trim($output[0]);
            }
            
            exec('which nodejs 2>&1', $output, $return_var);
            if ($return_var === 0) {
                return trim($output[0]);
            }
        }
        
        throw new \Exception(
            'Node.js binary not found. Please make sure Node.js is installed and either: ' .
            '1) Available in your system PATH, or ' .
            '2) Set the correct path in the plugin settings.'
        );
    }
} 