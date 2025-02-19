    /**
     * Sanitize port number and validate against acceptable ranges
     * 
     * @param mixed $value Port number to sanitize
     * @return int Sanitized port number or default port
     */
    public function sanitize_port($value) {
        $port = absint($value);
        
        // Check if port is in valid range
        if ($port < 1024 || $port > 65535) {
            add_settings_error(
                'sewn_ws_port',
                'invalid_port',
                sprintf(
                    __('Invalid port number: %d. Using default port %d from IANA Dynamic Port range.', 'sewn-ws'),
                    $port,
                    SEWN_WS_DEFAULT_PORT
                )
            );
            return SEWN_WS_DEFAULT_PORT;
        }

        // Warn if using common development port
        $common_ports = [3000, 8080, 4200, 5000, 8000];
        if (in_array($port, $common_ports)) {
            add_settings_error(
                'sewn_ws_port',
                'common_port',
                sprintf(
                    __('Warning: Port %d is commonly used by development servers. Consider using default port %d to avoid conflicts.', 'sewn-ws'),
                    $port,
                    SEWN_WS_DEFAULT_PORT
                ),
                'warning'
            );
        }

        return $port;
    } 