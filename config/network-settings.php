<?php
/**
 * Location: Network configuration management
 * Dependencies: WordPress Settings API, SEWN\WebSockets\Core
 * Variables: $network_settings array, admin UI hooks
 * 
 * Handles network-wide WebSocket configuration including port settings, security parameters, and
 * cluster configuration. Validates settings against server capabilities during updates.
 */

namespace SEWN\WebSockets\Config;