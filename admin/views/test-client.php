<script>
jQuery(document).ready(function($) {
    // Initialize WebSocket test client
    let testSocket = null;
    const consoleMessages = $('.console-messages');
    const connectionStatus = $('.test-connection-status');
    const connectionDetails = $('.connection-details');
    let latencyInterval;
    
    function updateConnectionStatus(status, message) {
        const dot = $('.connection-dot');
        const text = $('.connection-text');
        
        dot.removeClass('connected disconnected error');
        dot.addClass(status);
        text.text(message);
    }

    function logMessage(message, type = 'system') {
        const timestamp = new Date().toLocaleTimeString();
        const msgHtml = `<div class="message ${type}">
            <span class="timestamp">${timestamp}</span>
            ${message}
        </div>`;
        consoleMessages.append(msgHtml);
        consoleMessages.scrollTop(consoleMessages[0].scrollHeight);
    }

    function initializeTestSocket(wsUrl) {
        // Close existing socket if any
        if (testSocket) {
            testSocket.disconnect();
            testSocket = null;
        }

        // Initialize Socket.IO client with proper configuration
        testSocket = io(wsUrl, {
            path: '/socket.io',
            transports: ['websocket', 'polling'],
            reconnection: true,
            reconnectionAttempts: 3,
            timeout: 45000,
            auth: {
                token: window.SEWN_WS_CONFIG.adminToken
            }
        });

        // Handle connection error
        testSocket.on('connect_error', (error) => {
            console.error('Test socket connection error:', error);
            updateConnectionStatus('error', `Connection error: ${error.message}`);
            logMessage(`Connection error: ${error.message}`, 'error');
            stopLatencyCheck();
        });

        // Handle successful connection
        testSocket.on('connect', () => {
            // Send explicit CONNECT packet
            testSocket.emit('connect');
            logMessage('Connected to WebSocket server, sending CONNECT packet');
        });

        // Handle connection confirmation
        testSocket.on('connect_confirmed', (data) => {
            updateConnectionStatus('connected', 'Connected');
            logMessage('Connection confirmed by server');
            
            // Enable controls
            $('.disconnect-test, .message-input input, .send-message').prop('disabled', false);
            $('.test-connection').prop('disabled', true);

            // Start latency check
            startLatencyCheck();
        });

        // Handle disconnection
        testSocket.on('disconnect', (reason) => {
            updateConnectionStatus('disconnected', 'Disconnected');
            logMessage(`Disconnected: ${reason}`);
            
            // Disable controls
            $('.disconnect-test, .message-input input, .send-message').prop('disabled', true);
            $('.test-connection').prop('disabled', false);
            
            // Stop latency check
            stopLatencyCheck();
        });

        // Handle server shutdown
        testSocket.on('server_shutdown', (data) => {
            logMessage(`Server shutting down: ${data.reason}`);
            testSocket.disconnect();
        });

        // Handle incoming messages
        testSocket.on('message', (data) => {
            logMessage(data, 'received');
        });

        return testSocket;
    }

    function startLatencyCheck() {
        stopLatencyCheck(); // Clear any existing interval
        
        latencyInterval = setInterval(() => {
            if (testSocket && testSocket.connected) {
                const start = Date.now();
                testSocket.emit('ping');
                testSocket.once('pong', () => {
                    const latency = Date.now() - start;
                    $('.connection-latency').text(`${latency}ms`);
                });
            }
        }, 5000);
    }

    function stopLatencyCheck() {
        if (latencyInterval) {
            clearInterval(latencyInterval);
            $('.connection-latency').text('-');
        }
    }

    // Handle test connection button click
    $('.test-connection').on('click', function() {
        const button = $(this);
        button.prop('disabled', true);
        
        // Get current site URL and protocol
        const config = window.SEWN_WS_CONFIG;
        const protocol = config.ssl.enabled ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.hostname}:${config.serverPort}`;
        
        // Update UI
        $('.connection-url').text(wsUrl);
        connectionDetails.show();
        logMessage('Initializing WebSocket connection...');
        
        try {
            initializeTestSocket(wsUrl);
        } catch (error) {
            logMessage(`Initialization error: ${error.message}`, 'error');
            button.prop('disabled', false);
        }
    });

    // Handle disconnect button click
    $('.disconnect-test').on('click', function() {
        if (testSocket) {
            testSocket.disconnect();
        }
    });

    // Handle message sending
    $('.send-message').on('click', function() {
        const input = $('.message-input input');
        const message = input.val().trim();
        
        if (message && testSocket && testSocket.connected) {
            testSocket.emit('message', message);
            logMessage(message, 'sent');
            input.val('');
        }
    });

    // Handle Enter key in message input
    $('.message-input input').on('keypress', function(e) {
        if (e.which === 13) {
            $('.send-message').click();
        }
    });
});
</script> 