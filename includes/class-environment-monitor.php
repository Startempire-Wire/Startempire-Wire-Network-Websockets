<?php
/**
 * Environment Monitor Class
 *
 * Handles environment detection and configuration for the WebSocket plugin.
 * Detects local development environments, containerization, and SSL availability.
 *
 * @package SEWN\WebSockets
 */

namespace SEWN\WebSockets;

if (!defined('ABSPATH')) exit;

class Environment_Monitor {
    /**
     * Singleton instance
     *
     * @var Environment_Monitor
     */
    private static $instance = null;

    /**
     * Cached environment status
     *
     * @var array|null
     */
    private $environment_status = null;

    /**
     * Get singleton instance
     *
     * @return Environment_Monitor
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_init', array($this, 'detect_environment'));
    }

    /**
     * Detect the current environment
     */
    public function detect_environment() {
        if ($this->environment_status !== null) {
            return;
        }

        $this->environment_status = array(
            'is_local' => $this->is_local_environment(),
            'is_container' => $this->is_container_environment(),
            'type' => $this->detect_environment_type(),
            'ssl_available' => $this->is_ssl_available(),
            'detected_url' => $this->detect_site_url(),
            'server_software' => $this->detect_server_software(),
            'php_version' => PHP_VERSION,
            'os_type' => PHP_OS_FAMILY,
            'common_paths' => $this->get_common_paths()
        );

        // Update environment options if auto-detection is enabled
        if (get_option('sewn_ws_env_auto_detect', true)) {
            $this->update_environment_options();
        }
    }

    /**
     * Get the current environment status
     *
     * @return array Environment status data
     */
    public function get_environment_status() {
        if ($this->environment_status === null) {
            $this->detect_environment();
        }
        return $this->environment_status;
    }

    /**
     * Check if running in a local development environment
     *
     * @return bool
     */
    private function is_local_environment() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $ip = $_SERVER['SERVER_ADDR'] ?? '';
        
        // Check for common local development domains
        if (strpos($host, '.local') !== false ||
            strpos($host, '.test') !== false ||
            strpos($host, '.localhost') !== false ||
            $host === 'localhost' ||
            in_array($ip, array('127.0.0.1', '::1'))) {
            return true;
        }

        // Check for common local development environments
        $server_software = $this->detect_server_software();
        if (strpos($server_software, 'MAMP') !== false ||
            strpos($server_software, 'XAMPP') !== false ||
            strpos($server_software, 'Local by Flywheel') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Check if running in a containerized environment
     *
     * @return bool
     */
    private function is_container_environment() {
        // Check for Docker
        if (file_exists('/.dockerenv') || file_exists('/run/.containerenv')) {
            return true;
        }

        // Check for common container environment variables
        $env_vars = array('KUBERNETES_SERVICE_HOST', 'DOCKER_CONTAINER');
        foreach ($env_vars as $var) {
            if (getenv($var) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect the type of environment
     *
     * @return string
     */
    private function detect_environment_type() {
        if ($this->is_container_environment()) {
            if (getenv('KUBERNETES_SERVICE_HOST')) {
                return 'Kubernetes';
            }
            return 'Docker';
        }

        $server_software = $this->detect_server_software();
        if (strpos($server_software, 'MAMP') !== false) {
            return 'MAMP';
        }
        if (strpos($server_software, 'XAMPP') !== false) {
            return 'XAMPP';
        }
        if (strpos($server_software, 'Local by Flywheel') !== false) {
            return 'Local by Flywheel';
        }

        return 'Standard';
    }

    /**
     * Check if SSL is available
     *
     * @return bool
     */
    private function is_ssl_available() {
        // Check if WordPress is configured for SSL
        if (is_ssl()) {
            return true;
        }

        // Check common SSL certificate locations
        $common_paths = $this->get_common_paths();
        foreach ($common_paths['ssl_cert'] as $path) {
            if (file_exists($path) && is_readable($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect the site URL
     *
     * @return string
     */
    private function detect_site_url() {
        $site_url = get_site_url();

        // If in container, try to detect the container hostname
        if ($this->is_container_environment()) {
            $container_host = getenv('VIRTUAL_HOST');
            if ($container_host) {
                $site_url = (is_ssl() ? 'https://' : 'http://') . $container_host;
            }
        }

        return $site_url;
    }

    /**
     * Detect the server software
     *
     * @return string
     */
    private function detect_server_software() {
        return $_SERVER['SERVER_SOFTWARE'] ?? '';
    }

    /**
     * Get common paths for the current environment
     *
     * @return array
     */
    private function get_common_paths() {
        $paths = array(
            'ssl_cert' => array(),
            'ssl_key' => array()
        );

        if (PHP_OS_FAMILY === 'Darwin') { // macOS
            $paths['ssl_cert'] = array(
                '/etc/ssl/certs/',
                '/usr/local/etc/openssl/certs/',
                '/opt/homebrew/etc/openssl/certs/'
            );
            $paths['ssl_key'] = array(
                '/etc/ssl/private/',
                '/usr/local/etc/openssl/private/',
                '/opt/homebrew/etc/openssl/private/'
            );
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $paths['ssl_cert'] = array(
                'C:\\Program Files\\Local\\resources\\certs\\',
                'C:\\xampp\\apache\\conf\\ssl\\',
                'C:\\wamp64\\bin\\apache\\apache2.4.46\\conf\\ssl\\'
            );
            $paths['ssl_key'] = array(
                'C:\\Program Files\\Local\\resources\\certs\\',
                'C:\\xampp\\apache\\conf\\ssl\\',
                'C:\\wamp64\\bin\\apache\\apache2.4.46\\conf\\ssl\\'
            );
        } else { // Linux
            $paths['ssl_cert'] = array(
                '/etc/ssl/certs/',
                '/etc/nginx/ssl/',
                '/etc/apache2/ssl/'
            );
            $paths['ssl_key'] = array(
                '/etc/ssl/private/',
                '/etc/nginx/ssl/',
                '/etc/apache2/ssl/'
            );
        }

        return $paths;
    }

    /**
     * Update environment options based on detection
     */
    private function update_environment_options() {
        $status = $this->get_environment_status();

        // Update local mode
        if ($status['is_local']) {
            update_option('sewn_ws_env_local_mode', true);
        }

        // Update container mode
        if ($status['is_container']) {
            update_option('sewn_ws_env_container_mode', true);
        }

        // Store detected environment type
        update_option('sewn_ws_env_type', $status['type']);

        // Store detected URL if different from current
        $current_url = get_option('sewn_ws_site_url', '');
        if ($current_url !== $status['detected_url']) {
            update_option('sewn_ws_site_url', $status['detected_url']);
        }
    }

    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the instance
     */
    private function __wakeup() {}
} 