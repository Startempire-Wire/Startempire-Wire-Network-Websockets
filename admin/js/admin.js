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

    (function ($) {
        'use strict';

        const WsAdmin = {
            init: function () {
                this.statsRefresh = null;
                this.bindEvents();
                this.startStatsRefresh();
            },

            bindEvents: function () {
                $('.sewn-ws-controls button').on('click', this.handleServerAction.bind(this));
            },

            startStatsRefresh: function () {
                this.updateStats();
                this.statsRefresh = setInterval(() => {
                    this.updateStats();
                }, sewnWsAdmin.refresh_interval);
            },

            updateStats: function () {
                $.ajax({
                    url: sewnWsAdmin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sewn_ws_get_stats',
                        nonce: sewnWsAdmin.nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            this.updateDashboard(response.data);
                        }
                    }
                });
            },

            updateDashboard: function (data) {
                // Update server status
                const statusDot = $('.status-dot');
                const statusText = $('.status-text');

                if (data.server_status === 'running') {
                    statusDot.addClass('active').removeClass('inactive');
                    statusText.text('Running');
                } else {
                    statusDot.addClass('inactive').removeClass('active');
                    statusText.text('Stopped');
                }

                // Update connection stats
                $('#live-connections-count').text(data.connections.active);
                $('#total-connections').text(data.connections.total);
                $('#peak-connections').text(data.connections.peak);

                // Update message rate
                $('#message-rate').text(data.message_rate + ' msg/s');

                // Update memory usage
                const memoryMB = (data.memory_usage / 1024 / 1024).toFixed(1);
                $('#memory-usage').text(memoryMB + ' MB');

                // Update uptime
                $('#server-uptime').text(data.uptime);

                // Update graphs if they exist
                if (window.connectionGraph) {
                    window.connectionGraph.addPoint(data.connections.active);
                }
                if (window.memoryGraph) {
                    window.memoryGraph.addPoint(parseFloat(memoryMB));
                }
            },

            handleServerAction: function (e) {
                const action = $(e.currentTarget).data('action');

                $.ajax({
                    url: sewnWsAdmin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sewn_ws_server_control',
                        command: action,
                        nonce: sewnWsAdmin.nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            this.updateStats();
                        } else {
                            alert(response.data.message || 'Action failed');
                        }
                    }
                });
            }
        };

        $(document).ready(function () {
            WsAdmin.init();
        });
    })(jQuery);

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

    function initializeSocketMonitoring() {
        console.log('Initializing WebSocket connection:', SEWN_WS_CONFIG);

        // Force WS protocol for local development
        let socketUrl = SEWN_WS_CONFIG.url;
        if (SEWN_WS_CONFIG.isLocalDev) {
            socketUrl = socketUrl.replace('wss://', 'ws://');
        }

        const socket = io(socketUrl, {
            transports: ['websocket'],
            reconnectionAttempts: SEWN_WS_CONFIG.maxReconnectAttempts,
            reconnectionDelay: SEWN_WS_CONFIG.reconnectDelay,
            timeout: SEWN_WS_CONFIG.timeout,
            auth: {
                token: SEWN_WS_CONFIG.token
            }
        });

        // Socket event handlers
        socket.on('connect', () => {
            console.log('[SEWN WebSocket] Admin socket connected');
            updateServerStatus('running');
        });

        socket.on('connect_error', (error) => {
            console.warn('[SEWN WebSocket] Connection error:', error.message);
            updateServerStatus('error');
        });

        socket.on('error', (error) => {
            console.error('[SEWN WebSocket] Socket error:', error);
            updateServerStatus('error');
        });

        socket.on('disconnect', (reason) => {
            console.log('[SEWN WebSocket] Disconnected:', reason);
            updateServerStatus('stopped');
        });

        socket.on('stats', (stats) => {
            updateStats(stats);
        });

        return socket;
    }

    function checkServerStatus() {
        $.post(ajaxurl, {
            action: 'sewn_ws_check_server_status',
            nonce: sewn_ws_admin.nonce
        }).done(function (response) {
            if (response.success && response.data) {
                updateServerUI(response.data.details || {}, response.data.external || false);
                if (!socketInitialized && response.data.running) {
                    initializeSocketMonitoring();
                }
            } else {
                console.warn('[SEWN WebSocket] Invalid server status response:', response);
                updateServerUI({ running: false, pid: null, uptime: 0, memory: 0 });
            }
        }).fail(function (error) {
            console.error('[SEWN WebSocket] Server status check failed:', error);
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
            nonce: sewn_ws_admin.nonce
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
            nonce: sewn_ws_admin.nonce
        }).done(function (response) {
            if (response.success) {
                checkServerStatus();
            } else {
                alert('Failed to stop server: ' + response.message);
            }
        });
    });
}); 