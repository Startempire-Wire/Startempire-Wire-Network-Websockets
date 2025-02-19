    private $clients;
    private $connectionCount = 0;
    
    /**
     * Constructor
     * 
     * @param int $port Port number for WebSocket server. Defaults to 49200 (IANA Dynamic Port range)
     */
    public function __construct($port = 49200) {
        try {
            if (!$this->is_port_available($port)) {
                throw new \Exception(sprintf(
                    __('Port %d is already in use. Please choose a different port from range 49152-65535 to avoid conflicts.', 'sewn-ws'), 
                    $port
                ));
            }
        } catch (\Exception $e) {
            // Handle the exception
        }
    }

    // ... rest of the original file content ... 