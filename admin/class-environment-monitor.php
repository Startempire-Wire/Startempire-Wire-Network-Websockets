<?php
/**
 * Environment monitoring functionality
 *
 * @package SEWN\WebSockets
 */

namespace SEWN\WebSockets\Admin;

/**
 * Class Environment_Monitor
 * Handles environment detection and system monitoring
 */
class Environment_Monitor {

    /**
     * Class instance
     *
     * @var Environment_Monitor
     */
    private static $instance = null;

    /**
     * Error logger instance
     *
     * @var Error_Logger
     */
    private $logger;

    /**
     * Environment information cache
     *
     * @var array
     */
    private $environment_info;

    /**
     * Last check timestamp
     *
     * @var int
     */
    private $last_check;

    /**
     * Get class instance
     *
     * @return Environment_Monitor
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = Error_Logger::get_instance();
        $this->environment_info = get_option( 'sewn_ws_environment_info', array() );
        $this->last_check = get_option( 'sewn_ws_last_environment_check', 0 );
    }

    /**
     * Check environment and update status
     *
     * @return array Environment information
     * @throws \Exception If check fails
     */
    public function check_environment() {
        try {
            $this->environment_info = array(
                'is_local'           => $this->detect_local_environment(),
                'server_info'        => $this->get_server_info(),
                'php_info'           => $this->get_php_info(),
                'ssl_info'           => $this->get_ssl_info(),
                'container_info'     => $this->detect_container_environment(),
                'wordpress_info'     => $this->get_wordpress_info(),
                'system_requirements' => $this->check_system_requirements(),
                'last_check'         => time(),
            );

            update_option( 'sewn_ws_environment_info', $this->environment_info );
            update_option( 'sewn_ws_last_environment_check', time() );

            $this->logger->log(
                'Environment check completed',
                array( 'environment' => $this->environment_info ),
                'info'
            );

            return $this->environment_info;
        } catch ( \Exception $e ) {
            $this->logger->log(
                'Environment check failed',
                array( 'error' => $e->getMessage() ),
                'error'
            );
            throw $e;
        }
    }

    /**
     * Get current environment status
     *
     * @return array Status information
     */
    public function get_environment_status() {
        if ( empty( $this->environment_info ) ) {
            // If no environment info exists, try to check it
            try {
                $this->check_environment();
            } catch ( \Exception $e ) {
                $this->logger->log(
                    'Failed to check environment on status request',
                    array( 'error' => $e->getMessage() ),
                    'error'
                );
                // Return safe defaults
                return array(
                    'status'        => 'unknown',
                    'message'       => __( 'Environment status unknown', 'sewn-ws' ),
                    'is_local'      => false,
                    'container_mode' => false,
                    'ssl_valid'     => false,
                    'system_ready'  => false,
                    'last_check'    => 0,
                );
            }
        }

        return array(
            'is_local'      => isset($this->environment_info['is_local']) ? $this->environment_info['is_local'] : false,
            'container_mode' => isset($this->environment_info['container_info']['is_container']) ? $this->environment_info['container_info']['is_container'] : false,
            'ssl_valid'     => isset($this->environment_info['ssl_info']['has_valid_cert']) ? $this->environment_info['ssl_info']['has_valid_cert'] : false,
            'system_ready'  => isset($this->environment_info['system_requirements']['meets_requirements']) ? $this->environment_info['system_requirements']['meets_requirements'] : false,
            'last_check'    => isset($this->environment_info['last_check']) ? $this->environment_info['last_check'] : 0,
        );
    }

    /**
     * Detect if running in a local environment
     *
     * @return bool
     */
    private function detect_local_environment() {
        $local_indicators = array(
            'IS_LOCAL_SITE',
            'WP_LOCAL_DEV',
            'FLYWHEEL_CONFIG_DIR',
        );

        foreach ( $local_indicators as $constant ) {
            if ( defined( $constant ) && constant( $constant ) ) {
                return true;
            }
        }

        $local_domains = array(
            '.test',
            '.local',
            '.localhost',
            '.dev',
            '127.0.0.1',
            'localhost',
        );

        $site_url = parse_url( get_site_url(), PHP_URL_HOST );
        foreach ( $local_domains as $domain ) {
            if ( false !== strpos( $site_url, $domain ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect container environment
     *
     * @return array Container information
     */
    private function detect_container_environment() {
        $container_info = array(
            'is_container' => false,
            'type'        => 'standard',
            'details'     => array(),
        );

        if ( file_exists( '/.dockerenv' ) || file_exists( '/run/.containerenv' ) ) {
            $container_info['is_container'] = true;
            $container_info['type'] = 'docker';
        }

        if ( defined( 'FLYWHEEL_CONFIG_DIR' ) ) {
            $container_info['is_container'] = true;
            $container_info['type'] = 'flywheel';
        }

        return $container_info;
    }

    /**
     * Get server information
     *
     * @return array Server details
     */
    private function get_server_info() {
        return array(
            'software'     => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'unknown',
            'protocol'     => isset( $_SERVER['SERVER_PROTOCOL'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_PROTOCOL'] ) ) : 'unknown',
            'name'         => isset( $_SERVER['SERVER_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : 'unknown',
            'os'          => PHP_OS,
            'architecture' => PHP_INT_SIZE === 8 ? '64-bit' : '32-bit',
        );
    }

    /**
     * Get PHP information
     *
     * @return array PHP configuration details
     */
    private function get_php_info() {
        return array(
            'version'            => PHP_VERSION,
            'extensions'         => get_loaded_extensions(),
            'memory_limit'       => ini_get( 'memory_limit' ),
            'max_execution_time' => ini_get( 'max_execution_time' ),
            'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
            'post_max_size'      => ini_get( 'post_max_size' ),
        );
    }

    /**
     * Get SSL certificate information
     *
     * @return array SSL configuration and certificate details
     */
    public function get_ssl_info() {
        $ssl_info = array(
            'has_valid_cert' => false,
            'cert_paths'     => array(),
            'cert_details'   => array(),
        );

        $cert_locations = array(
            '/etc/ssl/certs',
            '/etc/pki/tls/certs',
            '/etc/apache2/ssl',
            '/etc/nginx/ssl',
            ABSPATH . 'certificates',
        );

        foreach ( $cert_locations as $location ) {
            if ( is_dir( $location ) ) {
                $ssl_info['cert_paths'][] = $location;
                
                $cert_files = glob( $location . '/*.{crt,pem,key}', GLOB_BRACE );
                if ( $cert_files ) {
                    foreach ( $cert_files as $cert_file ) {
                        if ( is_readable( $cert_file ) ) {
                            $ssl_info['has_valid_cert'] = true;
                            $ssl_info['cert_details'][] = array(
                                'path'        => $cert_file,
                                'type'        => pathinfo( $cert_file, PATHINFO_EXTENSION ),
                                'permissions' => substr( sprintf( '%o', fileperms( $cert_file ) ), -4 ),
                            );
                        }
                    }
                }
            }
        }

        if ( is_ssl() ) {
            $ssl_info['wordpress_ssl'] = true;
            $ssl_info['site_url'] = get_site_url();
        }

        return $ssl_info;
    }

    /**
     * Get WordPress information
     *
     * @return array WordPress configuration details
     */
    private function get_wordpress_info() {
        global $wp_version;
        return array(
            'version'      => $wp_version,
            'is_multisite' => is_multisite(),
            'debug_mode'   => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'theme'        => wp_get_theme()->get( 'Name' ),
            'active_plugins' => get_option( 'active_plugins' ),
        );
    }

    /**
     * Check system requirements
     *
     * @return array Requirements check results
     */
    private function check_system_requirements() {
        $requirements = array(
            'meets_requirements' => true,
            'details'           => array(),
        );

        $min_php = '7.4';
        $requirements['details']['php_version'] = array(
            'required' => $min_php,
            'current'  => PHP_VERSION,
            'meets'    => version_compare( PHP_VERSION, $min_php, '>=' ),
        );

        $required_extensions = array( 'openssl', 'mbstring', 'json' );
        $missing_extensions = array();
        foreach ( $required_extensions as $ext ) {
            if ( ! extension_loaded( $ext ) ) {
                $missing_extensions[] = $ext;
            }
        }
        $requirements['details']['php_extensions'] = array(
            'required' => $required_extensions,
            'missing'  => $missing_extensions,
            'meets'    => empty( $missing_extensions ),
        );

        foreach ( $requirements['details'] as $check ) {
            if ( isset( $check['meets'] ) && ! $check['meets'] ) {
                $requirements['meets_requirements'] = false;
                break;
            }
        }

        return $requirements;
    }
} 