/**
 * WebSocket Test Functionality
 * 
 * Handles WebSocket connection testing and status monitoring in the admin dashboard.
 * Ensures proper state management and user feedback.
 */

(function ($) {
    'use strict';

    let socket = null;
    let statusCheckInterval = null;
    let pingInterval = null;
    let lastPingTime = 0;

    const STATUS_CHECK_INTERVAL = 5000; // Check server status every 5 seconds
    const PING_INTERVAL = 2000; // Send ping every 2 seconds when connected

    // Elements
    const $serverStatus = $('#server-status-indicator');
    const $testButton = $('#test-connection');
    const $testStatus = $('#test-status');
    const $testResults = $('#test-results');
    const $connectionStatus = $('#connection-status');
    const $connectionLatency = $('#connection-latency');
    const $connectionProtocol = $('#connection-protocol');

    /**
     * Initialize the WebSocket test functionality
     */
    function init() {
        checkServerStatus();

        // Start periodic status checks
        statusCheckInterval = setInterval(checkServerStatus, STATUS_CHECK_INTERVAL);

        // Test button click handler
        $testButton.on('click', function () {
            if ($(this).prop('disabled')) return;

            if (!socket || socket.disconnected) {
                startTest();
            } else {
                stopTest();
            }
        });

        // Cleanup on page unload
        $(window).on('unload', cleanup);
    }

    /**
     * Check if the WebSocket server is running
     */
    function checkServerStatus() {
        $.ajax({
            url: sewn_ws_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'sewn_ws_check_server_status',
                nonce: sewn_ws_admin.nonce
            },
            success: function (response) {
                if (response.success) {
                    updateServerStatus(response.data.status);
                } else {
                    updateServerStatus('error');
                }
            },
            error: function () {
                updateServerStatus('error');
            }
        });
    }

    /**
     * Update the server status indicator and test button state
     */
    function updateServerStatus(status) {
        $serverStatus.attr('data-status', status);

        switch (status) {
            case 'running':
                $serverStatus.find('.status-text').text('Server Running');
                $testButton.prop('disabled', false);
                break;
            case 'stopped':
                $serverStatus.find('.status-text').text('Server Stopped');
                $testButton.prop('disabled', true);
                stopTest();
                break;
            case 'error':
                $serverStatus.find('.status-text').text('Server Error');
                $testButton.prop('disabled', true);
                stopTest();
                break;
            default:
                $serverStatus.find('.status-text').text('Status Unknown');
                $testButton.prop('disabled', true);
                stopTest();
        }
    }

    /**
     * Start the WebSocket connection test
     */
    function startTest() {
        $testStatus.text('Connecting...');
        $testButton.text('Stop Test');
        $testResults.show();

        // Initialize Socket.IO connection
        socket = io(sewn_ws_admin.server_url, {
            path: sewn_ws_admin.socket_path,
            transports: ['websocket', 'polling'],
            secure: sewn_ws_admin.ssl_enabled === '1'
        });

        // Connection event handlers
        socket.on('connect', function () {
            $connectionStatus.text('Connected');
            $connectionProtocol.text(socket.io.engine.transport.name);
            startPingTest();
        });

        socket.on('disconnect', function () {
            $connectionStatus.text('Disconnected');
            stopPingTest();
        });

        socket.on('connect_error', function (error) {
            console.error('Connection error:', error);
            $connectionStatus.text('Connection Error');
            $testStatus.text('Connection failed: ' + error.message);
            stopTest();
        });

        socket.on('pong', function () {
            const latency = Date.now() - lastPingTime;
            $connectionLatency.text(latency + 'ms');
        });
    }

    /**
     * Stop the WebSocket connection test
     */
    function stopTest() {
        if (socket) {
            socket.disconnect();
            socket = null;
        }

        stopPingTest();
        $testButton.text('Test Connection');
        $testStatus.text('');
        $connectionStatus.text('-');
        $connectionLatency.text('-');
        $connectionProtocol.text('-');
    }

    /**
     * Start sending ping messages to measure latency
     */
    function startPingTest() {
        stopPingTest();
        pingInterval = setInterval(function () {
            if (socket && socket.connected) {
                lastPingTime = Date.now();
                socket.emit('ping');
            }
        }, PING_INTERVAL);
    }

    /**
     * Stop sending ping messages
     */
    function stopPingTest() {
        if (pingInterval) {
            clearInterval(pingInterval);
            pingInterval = null;
        }
    }

    /**
     * Cleanup function for page unload
     */
    function cleanup() {
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
        }
        stopTest();
    }

    // Initialize when document is ready
    $(document).ready(init);

})(jQuery); 