<?php
/**
 * Location: includes/class-protocol-base.php
 * Dependencies: None
 * Variables/Classes: Protocol_Base
 * Purpose: Defines the base protocol interface that all WebSocket protocol implementations must follow.
 * Ensures consistent protocol handling across the network's distributed components.
 */

namespace SEWN\WebSockets;

abstract class Protocol_Base {
    /**
     * Register protocol with the WebSocket server
     */
    abstract public function register();

    /**
     * Handle incoming WebSocket messages
     *
     * @param array $message The message data
     * @param array $context Additional context data
     * @return array Response data
     */
    abstract public function handle_message($message, $context);

    /**
     * Register this protocol with a protocol handler
     *
     * @param object $handler The protocol handler instance
     */
    abstract public function register_protocol($handler);

    /**
     * Initialize protocol configuration
     */
    abstract public function init_config();

    /**
     * Add protocol-specific configuration to client config
     *
     * @param array $config Existing configuration array
     * @return array Modified configuration array
     */
    abstract public function add_protocol_config($config);

    /**
     * Validate protocol message
     *
     * @param array $message The message to validate
     * @return bool True if valid, false otherwise
     */
    protected function validate_message($message) {
        return is_array($message) && !empty($message);
    }

    /**
     * Format protocol response
     *
     * @param string $status Status of the operation
     * @param array $data Response data
     * @return array Formatted response
     */
    protected function format_response($status, $data = []) {
        return array_merge([
            'status' => $status,
            'timestamp' => time()
        ], $data);
    }

    /**
     * Handle protocol errors
     *
     * @param string $error Error message
     * @param array $context Error context
     * @return array Error response
     */
    protected function handle_error($error, $context = []) {
        error_log(sprintf('[SEWN WebSocket Protocol] Error: %s | Context: %s', 
            $error, 
            json_encode($context)
        ));

        return $this->format_response('error', [
            'error' => $error,
            'context' => $context
        ]);
    }
} 