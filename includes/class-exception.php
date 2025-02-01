<?php
namespace SEWN\WebSockets;

class Exception extends \Exception {
    /**
     * Contextual error data
     * @var array
     */
    protected $context = [];

    /**
     * Enhanced constructor with context support
     * @param string $message
     * @param array $context Additional debugging info
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        $message = "",
        array $context = [],
        $code = 0,
        \Throwable $previous = null
    ) {
        $this->context = $context;
        parent::__construct($message, $code, $previous);
        
        // Auto-log exceptions
        $this->log();
    }

    /**
     * Automatic error logging with context
     */
    protected function log() {
        error_log('[SEWN] EXCEPTION: ' . $this->getMessage());
        error_log('[SEWN] CONTEXT: ' . print_r($this->context, true));
        error_log('[SEWN] STACK TRACE: ' . $this->getTraceAsString());
    }

    /**
     * Get exception context for debugging
     * @return array
     */
    public function getContext() {
        return $this->context;
    }

    /**
     * Convert to array for API responses
     * @return array
     */
    public function toArray() {
        return [
            'error' => $this->getMessage(),
            'code' => $this->getCode(),
            'context' => $this->context,
            'trace' => $this->getTrace()
        ];
    }
} 