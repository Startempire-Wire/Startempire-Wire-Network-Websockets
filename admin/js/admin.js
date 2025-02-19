jQuery(document).ready(function ($) {
    'use strict';

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
}); 