<?php
/**
 * Handles AJAX actions for the WebSocket settings page
 *
 * @package SEWN\WebSockets
 * @subpackage Admin
 */

namespace SEWN\WebSockets\Admin;

if (!defined('ABSPATH')) exit;

class Settings_Ajax {
    /**
     * Rate limiting cache key prefix
     */
    private static $rate_limit_prefix = 'sewn_ws_rate_limit_';

    /**
     * Maximum requests per minute
     */
    private static $max_requests = 30;

    /**
     * Initialize the AJAX handlers
     */
    public static function init() {
        // Add security checks
        add_action('admin_init', array(__CLASS__, 'verify_environment'));
        
        // Register AJAX handlers with security wrapper
        foreach (['get_ssl_paths', 'test_ssl', 'check_module_dependencies', 'test_configuration', 'get_debug_logs', 'clear_debug_logs', 'export_debug_logs'] as $action) {
            add_action('wp_ajax_sewn_ws_' . $action, function() use ($action) {
                self::handle_ajax_request($action);
            });
        }
    }

    /**
     * Verify environment security
     */
    public static function verify_environment() {
        // Verify WordPress version
        if (version_compare(get_bloginfo('version'), '5.8', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>WebSocket plugin requires WordPress 5.8 or higher for security features.</p></div>';
            });
        }

        // Verify PHP version and extensions
        if (version_compare(PHP_VERSION, '7.4', '<') || !extension_loaded('openssl')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>WebSocket plugin requires PHP 7.4+ and OpenSSL extension.</p></div>';
            });
        }
    }

    /**
     * Handle AJAX request with security wrapper
     */
    private static function handle_ajax_request($action) {
        try {
            if (!self::verify_request()) {
                throw new \Exception('Invalid request');
            }

            if (!self::check_rate_limit()) {
                throw new \Exception('Rate limit exceeded');
            }

            // Call the actual handler method
            $method = $action . '_handler';
            if (!method_exists(__CLASS__, $method)) {
                throw new \Exception('Invalid action');
            }

            self::$method();

        } catch (\Exception $e) {
            $logger = Error_Logger::get_instance();
            $logger->log('AJAX request failed: ' . $e->getMessage(), 'error', [
                'action' => $action,
                'code' => $e->getCode()
            ]);

            wp_send_json_error([
                'message' => esc_html($e->getMessage()),
                'code' => $e->getCode()
            ]);
        }
    }

    /**
     * Verify request security
     */
    private static function verify_request() {
        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            return false;
        }

        // Verify nonce
        if (!check_ajax_referer('sewn_ws_admin', 'nonce', false)) {
            return false;
        }

        // Verify referer
        $referer = wp_get_referer();
        if (!$referer || parse_url($referer, PHP_URL_HOST) !== $_SERVER['HTTP_HOST']) {
            return false;
        }

        // Verify origin
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (!$origin || parse_url($origin, PHP_URL_HOST) !== $_SERVER['HTTP_HOST']) {
            return false;
        }

        return true;
    }

    /**
     * Check rate limiting
     */
    private static function check_rate_limit() {
        $user_id = get_current_user_id();
        $cache_key = self::$rate_limit_prefix . $user_id;
        
        $requests = get_transient($cache_key);
        if ($requests === false) {
            set_transient($cache_key, 1, MINUTE_IN_SECONDS);
            return true;
        }

        if ($requests >= self::$max_requests) {
            return false;
        }

        set_transient($cache_key, $requests + 1, MINUTE_IN_SECONDS);
        return true;
    }

    /**
     * Get SSL paths handler
     */
    private static function get_ssl_paths_handler() {
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        if (!in_array($type, ['cert', 'key'], true)) {
            throw new \Exception('Invalid SSL path type');
        }

        $monitor = Environment_Monitor::get_instance();
        $env_status = $monitor->get_environment_status();
        $paths = $env_status['common_paths'][$type === 'cert' ? 'ssl_cert' : 'ssl_key'];

        // Filter and validate paths
        $filtered_paths = array_filter($paths, function($path) use ($type) {
            if (!file_exists($path)) {
                return false;
            }

            if ($type === 'cert') {
                return (bool)(glob($path . '*.crt') || glob($path . '*.pem'));
            } else {
                return (bool)glob($path . '*.key');
            }
        });

        wp_send_json_success([
            'paths' => array_map('esc_html', array_values($filtered_paths))
        ]);
    }

    /**
     * Test SSL certificate configuration
     */
    public static function test_ssl_config() {
        try {
            if (!current_user_can('manage_options')) {
                throw new \Exception('Insufficient permissions');
            }

            check_ajax_referer('sewn_ws_admin', 'nonce');

            $cert_path = isset($_POST['cert']) ? sanitize_text_field($_POST['cert']) : '';
            $key_path = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';

            $logger = Error_Logger::get_instance();
            $logger->log('Testing SSL configuration', 'info', [
                'cert_path' => $cert_path,
                'key_path' => $key_path
            ]);

            if (empty($cert_path) || empty($key_path)) {
                throw new \Exception('Certificate and key paths are required');
            }

            // Security context validation
            if (!self::validate_ssl_path($cert_path) || !self::validate_ssl_path($key_path)) {
                throw new \Exception('Invalid certificate or key path');
            }

            // Verify certificate exists and is readable
            if (!file_exists($cert_path)) {
                throw new \Exception(sprintf('Certificate file not found at path: %s', $cert_path));
            }
            if (!is_readable($cert_path)) {
                throw new \Exception(sprintf('Certificate file not readable at path: %s', $cert_path));
            }

            // Verify private key exists and is readable
            if (!file_exists($key_path)) {
                throw new \Exception(sprintf('Private key file not found at path: %s', $key_path));
            }
            if (!is_readable($key_path)) {
                throw new \Exception(sprintf('Private key file not readable at path: %s', $key_path));
            }

            // Verify certificate format and expiration
            $cert_content = file_get_contents($cert_path);
            if ($cert_content === false) {
                throw new \Exception('Failed to read certificate file');
            }

            $cert_data = openssl_x509_parse($cert_content);
            if ($cert_data === false) {
                throw new \Exception('Invalid certificate format');
            }

            // Check certificate expiration
            $expiry = $cert_data['validTo_time_t'];
            if (time() > $expiry) {
                throw new \Exception(sprintf(
                    'Certificate has expired on %s',
                    date('Y-m-d H:i:s', $expiry)
                ));
            }

            // Warn if certificate is expiring soon (30 days)
            $expiry_warning = 30 * 24 * 60 * 60; // 30 days in seconds
            if (time() + $expiry_warning > $expiry) {
                $logger->log('Certificate expiring soon', 'warning', [
                    'expiry_date' => date('Y-m-d H:i:s', $expiry)
                ]);
            }

            // Verify private key matches certificate
            $cert = openssl_pkey_get_public($cert_content);
            if ($cert === false) {
                throw new \Exception('Failed to load certificate');
            }

            $key_content = file_get_contents($key_path);
            if ($key_content === false) {
                throw new \Exception('Failed to read private key file');
            }

            $key = openssl_pkey_get_private($key_content);
            if ($key === false) {
                throw new \Exception('Failed to load private key');
            }

            $cert_details = openssl_pkey_get_details($cert);
            $key_details = openssl_pkey_get_details($key);

            if ($cert_details === false || $key_details === false) {
                throw new \Exception('Failed to get key details');
            }

            if ($cert_details['key'] !== $key_details['key']) {
                throw new \Exception('Certificate and private key do not match');
            }

            $logger->log('SSL configuration test successful', 'info', [
                'expires' => date('Y-m-d H:i:s', $expiry)
            ]);

            wp_send_json_success([
                'message' => 'SSL configuration is valid',
                'expires' => date('Y-m-d H:i:s', $expiry),
                'warnings' => time() + $expiry_warning > $expiry ? [
                    sprintf(
                        'Certificate will expire on %s',
                        date('Y-m-d H:i:s', $expiry)
                    )
                ] : []
            ]);

        } catch (\Exception $e) {
            $logger = Error_Logger::get_instance();
            $logger->log('SSL configuration test failed: ' . $e->getMessage(), 'error');
            
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }

    /**
     * Validate SSL file path for security
     *
     * @param string $path File path to validate
     * @return bool Whether the path is valid
     */
    private static function validate_ssl_path($path) {
        // Normalize path
        $path = realpath($path);
        if ($path === false) {
            return false;
        }

        // Get allowed SSL directories
        $monitor = Environment_Monitor::get_instance();
        $env_status = $monitor->get_environment_status();
        $allowed_paths = $env_status['common_paths']['ssl_cert'];

        // Check if path is within allowed directories
        foreach ($allowed_paths as $allowed_path) {
            if (strpos($path, $allowed_path) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check module dependencies and conflicts
     */
    public static function check_module_dependencies() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        check_ajax_referer('sewn_ws_admin', 'nonce');

        $module_id = isset($_POST['module']) ? sanitize_text_field($_POST['module']) : '';
        if (empty($module_id)) {
            wp_send_json_error('Module ID is required');
        }

        // Get module registry
        $registry = apply_filters('sewn_ws_module_registry', array());
        if (!isset($registry[$module_id])) {
            wp_send_json_error('Module not found');
        }

        $module = $registry[$module_id];
        $warnings = array();

        // Check required dependencies
        if (!empty($module['dependencies'])) {
            foreach ($module['dependencies'] as $dep) {
                if (!isset($registry[$dep]) || !$registry[$dep]['active']) {
                    $warnings[] = sprintf(
                        __('Required module "%s" is not active', 'sewn-ws'),
                        $registry[$dep]['name'] ?? $dep
                    );
                }
            }
        }

        // Check conflicts
        if (!empty($module['conflicts'])) {
            foreach ($module['conflicts'] as $conflict) {
                if (isset($registry[$conflict]) && $registry[$conflict]['active']) {
                    $warnings[] = sprintf(
                        __('Conflicts with active module "%s"', 'sewn-ws'),
                        $registry[$conflict]['name'] ?? $conflict
                    );
                }
            }
        }

        // Check version requirements
        if (!empty($module['requires'])) {
            foreach ($module['requires'] as $requirement => $version) {
                if ($requirement === 'php' && version_compare(PHP_VERSION, $version, '<')) {
                    $warnings[] = sprintf(
                        __('Requires PHP %s or higher (current: %s)', 'sewn-ws'),
                        $version,
                        PHP_VERSION
                    );
                } elseif ($requirement === 'wp' && version_compare(get_bloginfo('version'), $version, '<')) {
                    $warnings[] = sprintf(
                        __('Requires WordPress %s or higher (current: %s)', 'sewn-ws'),
                        $version,
                        get_bloginfo('version')
                    );
                }
            }
        }

        wp_send_json_success(array(
            'warnings' => $warnings
        ));
    }

    /**
     * Test configuration handler
     */
    private static function test_configuration_handler() {
        try {
            $logger = Error_Logger::get_instance();
            $monitor = Environment_Monitor::get_instance();
            $registry = \SEWN\WebSockets\Module_Registry::get_instance();

            // 1. Test WebSocket server configuration
            $host = isset($_POST['host']) ? sanitize_text_field($_POST['host']) : '';
            $port = isset($_POST['port']) ? (int)$_POST['port'] : 0;
            
            if (empty($host) || $port <= 0) {
                throw new \Exception('Invalid host or port configuration');
            }

            // 2. Validate port availability
            if (!self::is_port_available($host, $port)) {
                throw new \Exception(sprintf('Port %d is not available on host %s', $port, $host));
            }

            // 3. Test SSL configuration (reuse existing method)
            $cert_path = isset($_POST['cert']) ? sanitize_text_field($_POST['cert']) : '';
            $key_path = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
            
            if (!empty($cert_path) && !empty($key_path)) {
                self::test_ssl_config();
            }

            // 4. Verify environment settings
            $env_status = $monitor->get_environment_status();
            if (!$env_status['requirements_met']) {
                throw new \Exception('Environment requirements not met: ' . implode(', ', $env_status['missing_requirements']));
            }

            // 5. Test module dependencies
            $modules = $registry->get_modules();
            $dependency_issues = [];
            
            foreach ($modules as $module_id => $module) {
                $validation = $registry->validate_activation($module_id);
                if (!$validation['valid']) {
                    $dependency_issues[$module_id] = $validation['messages'];
                }
            }

            if (!empty($dependency_issues)) {
                $logger->log('Module dependency issues found', 'warning', ['issues' => $dependency_issues]);
            }

            // Log successful configuration test
            $logger->log('Configuration test completed successfully', 'info', [
                'host' => $host,
                'port' => $port,
                'ssl_enabled' => !empty($cert_path),
                'modules_checked' => count($modules)
            ]);

            wp_send_json_success([
                'message' => 'Configuration test completed successfully',
                'details' => [
                    'environment' => $env_status,
                    'dependencies' => empty($dependency_issues) ? 'All modules validated' : $dependency_issues,
                    'ssl_status' => !empty($cert_path) ? 'SSL configured' : 'SSL not configured'
                ]
            ]);

        } catch (\Exception $e) {
            $logger->log('Configuration test failed: ' . $e->getMessage(), 'error');
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if port is available
     *
     * @param string $host Host to check
     * @param int $port Port to check
     * @return bool True if port is available
     */
    private static function is_port_available($host, $port) {
        $socket = @fsockopen($host, $port, $errno, $errstr, 5);
        if ($socket) {
            fclose($socket);
            return false; // Port is in use
        }
        return true; // Port is available
    }

    /**
     * Get debug logs handler
     */
    private static function get_debug_logs_handler() {
        try {
            $logger = Error_Logger::get_instance();
            $filters = isset($_POST['filters']) ? (array)$_POST['filters'] : [];
            
            // Sanitize filters
            $filters = array_map('sanitize_text_field', $filters);
            
            // Get logs with filters
            $logs = $logger->get_logs($filters);
            
            wp_send_json_success([
                'logs' => array_map(function($log) {
                    return [
                        'timestamp' => esc_html($log['timestamp']),
                        'level' => esc_html($log['level']),
                        'message' => esc_html($log['message']),
                        'context' => !empty($log['context']) ? $log['context'] : null
                    ];
                }, $logs)
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear debug logs handler
     */
    private static function clear_debug_logs_handler() {
        try {
            $logger = Error_Logger::get_instance();
            $logger->clear_logs();
            
            wp_send_json_success([
                'message' => 'Logs cleared successfully'
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Export debug logs handler
     */
    private static function export_debug_logs_handler() {
        try {
            $logger = Error_Logger::get_instance();
            $filters = isset($_POST['filters']) ? (array)$_POST['filters'] : [];
            
            // Sanitize filters
            $filters = array_map('sanitize_text_field', $filters);
            
            // Get logs with filters
            $logs = $logger->get_logs($filters);
            
            // Generate export file
            $filename = 'websocket-debug-logs-' . date('Y-m-d-H-i-s') . '.json';
            $upload_dir = wp_upload_dir();
            $export_path = $upload_dir['basedir'] . '/' . $filename;
            
            file_put_contents($export_path, json_encode($logs, JSON_PRETTY_PRINT));
            
            wp_send_json_success([
                'download_url' => $upload_dir['baseurl'] . '/' . $filename
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
} 