jQuery(document).ready(function ($) {
    'use strict';

    let socketInitialized = false;
    let pollInterval = 1000;
    const maxPollInterval = 5000;
    let socketReconnectAttempts = 0;
    const maxReconnectAttempts = 5;

    // Environment check handler
    $('#check-environment').on('click', function () {
        var $button = $(this);
        var $status = $('.environment-status');

        $button.prop('disabled', true).text(sewnWsAdmin.i18n.checking);

        $.ajax({
            url: sewnWsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sewn_ws_check_environment',
                nonce: sewnWsAdmin.nonce
            },
            success: function (response) {
                if (response.success) {
                    $status.html(response.data.result);
                    $button.text(sewnWsAdmin.i18n.success);
                    setTimeout(function () {
                        window.location.reload();
                    }, 1500);
                } else {
                    $button.text(sewnWsAdmin.i18n.error);
                    alert(response.data);
                }
            },
            error: function () {
                $button.text(sewnWsAdmin.i18n.error);
                alert(sewnWsAdmin.i18n.error);
            },
            complete: function () {
                setTimeout(function () {
                    $button.prop('disabled', false).text(sewnWsAdmin.i18n.check);
                }, 2000);
            }
        });
    });

    // SSL certificate detection
    $('#detect-ssl-cert, #detect-ssl-key').on('click', function () {
        var $button = $(this);
        var $input = $button.prev('input');

        $button.prop('disabled', true);

        $.ajax({
            url: sewnWsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sewn_ws_get_ssl_paths',
                nonce: sewnWsAdmin.nonce
            },
            success: function (response) {
                if (response.success && response.data.paths.length > 0) {
                    var paths = response.data.paths;
                    var $select = $('<select>').addClass('ssl-path-select');

                    $select.append($('<option>').val('').text('-- Select Path --'));

                    paths.forEach(function (path) {
                        $select.append($('<option>').val(path).text(path));
                    });

                    $select.insertAfter($button).on('change', function () {
                        $input.val($(this).val());
                        $(this).remove();
                    });
                } else {
                    alert('No SSL certificates found in common locations.');
                }
            },
            error: function () {
                alert('Failed to detect SSL certificates.');
            },
            complete: function () {
                $button.prop('disabled', false);
            }
        });
    });

    // Log management
    var $logContent = $('.log-content');
    var $clearLogsBtn = $('#clear-logs');
    var $exportLogsBtn = $('#export-logs');

    function loadLogs() {
        $.ajax({
            url: sewnWsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sewn_ws_get_logs',
                nonce: sewnWsAdmin.nonce
            },
            success: function (response) {
                if (response.success) {
                    $logContent.html(response.data);
                }
            }
        });
    }

    $clearLogsBtn.on('click', function () {
        if (!confirm('Are you sure you want to clear all logs?')) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true);

        $.ajax({
            url: sewnWsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sewn_ws_clear_logs',
                nonce: sewnWsAdmin.nonce
            },
            success: function (response) {
                if (response.success) {
                    loadLogs();
                } else {
                    alert(response.data);
                }
            },
            error: function () {
                alert('Failed to clear logs.');
            },
            complete: function () {
                $button.prop('disabled', false);
            }
        });
    });

    $exportLogsBtn.on('click', function () {
        window.location.href = sewnWsAdmin.ajaxUrl + '?' + $.param({
            action: 'sewn_ws_export_logs',
            nonce: sewnWsAdmin.nonce
        });
    });

    // Initial log load
    loadLogs();

    // Auto-refresh logs every 30 seconds
    setInterval(loadLogs, 30000);

    // Local environment mode handling
    var $localMode = $('#local_mode');
    var $containerMode = $('#container_mode');
    var $localSiteUrl = $('#local_site_url');
    var $sslFields = $('#ssl_cert_path, #ssl_key_path');

    $localMode.on('change', function () {
        var isLocal = $(this).is(':checked');
        $containerMode.prop('disabled', !isLocal);
        $localSiteUrl.prop('disabled', !isLocal);
        $sslFields.prop('disabled', !isLocal);

        if (!isLocal) {
            $containerMode.prop('checked', false);
            $localSiteUrl.val('');
            $sslFields.val('');
        }
    });

    // Trigger initial state
    $localMode.trigger('change');

    class WebSocketAdmin {
        constructor(config) {
            // Force SSL if page is HTTPS
            if (window.location.protocol === 'https:') {
                config.protocol = 'wss';
                config.ssl.enabled = true;
            }

            this.config = config;
            debugLog.info('Server Configuration:', this.config);

            this.pendingRequests = false;
            this.initializeControls();
        }

        initializeControls() {
            const self = this;
            $('.sewn-ws-controls button').on('click', function (e) {
                e.preventDefault();
                const action = $(this).data('action');
                self.handleServerAction(action);
            });
        }

        async handleServerAction(action) {
            if (this.pendingRequests) {
                console.log('Request already in progress...');
                return;
            }

            try {
                this.pendingRequests = true;
                this.updateServerStatus('starting');
                console.log(`Sending ${action} request to server...`);

                const response = await $.ajax({
                    url: this.config.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sewn_ws_server_control',
                        server_action: action,
                        nonce: this.config.nonce
                    }
                });

                console.log('Server response received:', response.status);
                console.log('Response data:', response);

                if (response.success) {
                    const serverStatus = response.data?.status || 'error';
                    console.log('Extracted server status:', serverStatus);
                    this.updateServerStatus(serverStatus);

                    if (response.data?.details) {
                        console.log('Updating stats with:', response.data.details);
                        this.updateStats(response.data.details);
                    }
                } else {
                    console.error('Server request failed:', response.data?.message || 'Unknown error');
                    this.updateServerStatus('error');
                }
            } catch (error) {
                console.error('Server request error:', error);
                this.updateServerStatus('error');
            } finally {
                this.pendingRequests = false;
            }
        }

        updateServerStatus(status) {
            console.log('Updating server status to:', status);
            $('.sewn-ws-status')
                .removeClass('uninitialized stopped starting running error')
                .addClass(status);

            $('.sewn-ws-status .status-text').text(status);
        }

        updateStats(stats) {
            if (!stats) return;

            const { running, pid, uptime, memory } = stats;
            $('.server-stats .stat-value.running').text(running ? 'Yes' : 'No');
            $('.server-stats .stat-value.pid').text(pid || 'N/A');
            $('.server-stats .stat-value.uptime').text(uptime ? `${uptime}s` : 'N/A');
            $('.server-stats .stat-value.memory').text(memory ? `${memory}MB` : 'N/A');
        }
    }

    // Initialize WebSocket Admin when document is ready
    jQuery(document).ready(function ($) {
        if (typeof serverConfig !== 'undefined') {
            window.wsAdmin = new WebSocketAdmin(serverConfig);
        }
    });

    function verifySocketIOLoaded() {
        if (typeof io === 'undefined') {
            console.error('[SEWN WebSocket] Socket.IO client not loaded. Please check your network connection or contact support.');
            $('#socket-status')
                .text('Socket.IO Not Loaded')
                .removeClass('status-ok')
                .addClass('status-error');
            return false;
        }
        return true;
    }

    function verifyConfig() {
        if (!window.SEWN_WS_CONFIG) {
            console.error('[SEWN WebSocket] Configuration not found. Please refresh the page or contact support.');
            return false;
        }
        return true;
    }

    function getWebSocketUrl() {
        if (!window.SEWN_WS_CONFIG || !window.SEWN_WS_CONFIG.url) {
            console.error('[SEWN WebSocket] Invalid configuration: Missing WebSocket URL');
            return null;
        }

        try {
            const url = new URL(window.SEWN_WS_CONFIG.url);
            // Automatically switch protocol based on page protocol
            url.protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
            return url.toString();
        } catch (e) {
            console.error('[SEWN WebSocket] Invalid WebSocket URL:', e);
            return null;
        }
    }

    // Add debug logging system
    const DEBUG = true;  // Enable/disable debug logging
    const debugLog = {
        info: function (...args) {
            if (DEBUG) {
                console.info('[SEWN WebSocket]', ...args);
            }
        },
        warn: function (...args) {
            if (DEBUG) {
                console.warn('[SEWN WebSocket]', ...args);
            }
        },
        error: function (...args) {
            if (DEBUG) {
                console.error('[SEWN WebSocket]', ...args);
            }
        }
    };

    // Update socket initialization with enhanced logging
    function initializeSocket(config) {
        debugLog.info('Initializing socket with config:', config);

        if (!config || !config.host) {
            debugLog.error('Invalid socket configuration:', config);
            return null;
        }

        // Determine correct protocol
        const isSecure = window.location.protocol === 'https:';
        const wsProtocol = isSecure ? 'wss://' : 'ws://';
        const socketHost = `${wsProtocol}${config.host}`;

        debugLog.info('Using WebSocket host:', socketHost);

        const socket = io(socketHost, {
            path: '/socket.io',
            transports: ['polling', 'websocket'],
            autoConnect: false,
            reconnection: true,
            reconnectionAttempts: 5,
            reconnectionDelay: 1000,
            reconnectionDelayMax: 5000,
            timeout: 20000,
            rejectUnauthorized: false, // Allow self-signed certs in local
            secure: isSecure, // Match page protocol
            withCredentials: true // Important for local SSL
        });

        // Connection monitoring with enhanced logging
        socket.on("connect", () => {
            debugLog.info('Socket connected!', {
                transport: socket.io.engine.transport.name,
                id: socket.id,
                protocol: wsProtocol
            });

            socket.io.engine.on("upgrade", () => {
                debugLog.info('Transport upgraded:', {
                    from: socket.io.engine.transport.name,
                    id: socket.id
                });
            });
        });

        // Enhanced error handling
        socket.on("connect_error", (err) => {
            debugLog.error('Connection error:', {
                message: err.message,
                description: err.description,
                context: err.context,
                type: err.type,
                protocol: wsProtocol,
                host: socketHost
            });
        });

        socket.on("disconnect", (reason) => {
            debugLog.warn('Socket disconnected:', {
                reason,
                wasConnected: socket.connected,
                id: socket.id
            });
        });

        socket.on("error", (error) => {
            debugLog.error('Socket error:', {
                error,
                id: socket.id,
                state: socket.connected ? 'connected' : 'disconnected'
            });
        });

        // Add ping monitoring
        socket.on("ping", () => {
            debugLog.info('Ping received');
        });

        socket.on("pong", (latency) => {
            debugLog.info('Pong received, latency:', latency, 'ms');
        });

        return socket;
    }

    // Update server status checking with logging
    function checkServerStatus() {
        debugLog.info('Checking server status...');

        $.post(ajaxurl, {
            action: 'sewn_ws_check_server_status',
            nonce: sewnWsAdmin.nonce
        })
            .done(function (response) {
                debugLog.info('Server status response:', response);

                if (response.success && response.data) {
                    updateServerUI(response.data.details || {}, response.data.external || false);

                    if (!socketInitialized && response.data.running) {
                        debugLog.info('Server is running, initializing socket...');
                        const socket = initializeSocket(response.data.details);
                        if (socket) {
                            socketInitialized = true;
                            socket.connect();
                        }
                    }
                } else {
                    debugLog.warn('Invalid server status response:', response);
                    updateServerUI({ running: false, pid: null, uptime: 0, memory: 0 });
                }
            })
            .fail(function (error) {
                debugLog.error('Server status check failed:', error);
                updateServerUI({ running: false, pid: null, uptime: 0, memory: 0 });
            });
    }

    function updateServerUI(details, isExternal) {
        const statusEl = $('#server-status');
        statusEl.text(isExternal ? 'Running (External)' : 'Running')
            .removeClass('status-stopped status-error')
            .addClass('status-running');

        $('#server-pid').text(details.pid || 'N/A');
        $('#server-uptime').text(formatUptime(details.uptime));
        $('#server-memory').text(formatMemory(details.memory));
        $('#server-connections').text(details.connections || 0);

        $('#start-server').prop('disabled', true);
        $('#stop-server').prop('disabled', false);

        // Update connection status if available
        if (details.socket_status) {
            $('#socket-status').text(details.socket_status)
                .removeClass('status-error')
                .addClass('status-ok');
        }
    }

    function formatUptime(seconds) {
        if (!seconds) return '0s';

        const days = Math.floor(seconds / 86400);
        seconds %= 86400;
        const hours = Math.floor(seconds / 3600);
        seconds %= 3600;
        const minutes = Math.floor(seconds / 60);
        seconds %= 60;

        let uptime = '';
        if (days) uptime += days + 'd ';
        if (hours) uptime += hours + 'h ';
        if (minutes) uptime += minutes + 'm ';
        if (seconds) uptime += seconds + 's';

        return uptime.trim();
    }

    function formatMemory(bytes) {
        if (!bytes) return '0 B';

        const units = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));

        return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + units[i];
    }

    function setupPolling() {
        checkServerStatus();

        if (pollInterval < maxPollInterval) {
            pollInterval = Math.min(pollInterval * 1.5, maxPollInterval);
        }

        setTimeout(setupPolling, pollInterval);
    }

    // Initialize polling
    setupPolling();

    // Handle server control buttons
    $('#start-server').on('click', function () {
        $.post(ajaxurl, {
            action: 'sewn_ws_start_server',
            nonce: sewnWsAdmin.nonce
        }).done(function (response) {
            if (response.success) {
                checkServerStatus();
            } else {
                alert('Failed to start server: ' + response.message);
            }
        });
    });

    $('#stop-server').on('click', function () {
        $.post(ajaxurl, {
            action: 'sewn_ws_stop_server',
            nonce: sewnWsAdmin.nonce
        }).done(function (response) {
            if (response.success) {
                checkServerStatus();
            } else {
                alert('Failed to stop server: ' + response.message);
            }
        });
    });
}); 