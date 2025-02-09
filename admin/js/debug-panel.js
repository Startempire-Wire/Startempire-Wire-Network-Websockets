jQuery(document).ready(function ($) {
    console.log('Debug Panel JS Initialized');
    var logRefreshInterval;
    var lastLogId = 0;

    // Log initial state
    var initialDebugEnabled = $('#sewn_ws_debug_enabled').is(':checked');
    console.log('Initial Debug Mode State:', initialDebugEnabled);
    console.log('Initial Debug Panel Display:', $('.sewn-ws-debug-panel').is(':visible'));

    // Toggle debug panel
    $('#sewn_ws_debug_enabled').on('change', function () {
        var isEnabled = $(this).is(':checked');
        console.log('Debug Toggle Changed:', isEnabled);

        // Log the panel state before toggle
        console.log('Debug Panel Before Toggle:', $('.sewn-ws-debug-panel').is(':visible'));

        $('.sewn-ws-debug-panel').toggle(isEnabled);

        // Log the panel state after toggle
        console.log('Debug Panel After Toggle:', $('.sewn-ws-debug-panel').is(':visible'));

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sewn_ws_toggle_debug',
                enabled: isEnabled,
                nonce: sewnWsAdmin.nonce
            },
            beforeSend: function () {
                console.log('Sending AJAX request to toggle debug mode');
            },
            success: function (response) {
                console.log('AJAX Response:', response);
                if (response.success) {
                    console.log('Debug mode updated successfully');
                    if (isEnabled) {
                        console.log('Starting log refresh');
                        startLogRefresh();
                    } else {
                        console.log('Stopping log refresh');
                        stopLogRefresh();
                    }
                } else {
                    console.error('Failed to update debug mode:', response.data);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', {
                    status: status,
                    error: error,
                    xhr: xhr
                });
            }
        });
    });

    // Load logs
    function loadLogs() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sewn_ws_get_logs',
                last_id: lastLogId,
                nonce: sewnWsAdmin.nonce
            },
            success: function (response) {
                if (response.success) {
                    updateLogDisplay(response.data);
                }
            }
        });
    }

    // Update log display
    function updateLogDisplay(logs) {
        var $content = $('.log-content');
        var activeFilters = $('.log-level-filter:checked').map(function () {
            return $(this).val();
        }).get();

        logs.forEach(function (log) {
            if (activeFilters.includes(log.level)) {
                lastLogId = Math.max(lastLogId, log.id);
                var $entry = $('<div class="log-entry ' + log.level + '">' +
                    '<div class="timestamp">' + log.time + '</div>' +
                    '<div class="message">' + log.message + '</div>' +
                    '<div class="context">' + JSON.stringify(log.context, null, 2) + '</div>' +
                    '</div>');
                $content.prepend($entry);
            }
        });

        $('.log-loading').hide();
    }

    // Start log refresh
    function startLogRefresh() {
        console.log('Starting log refresh interval');
        loadLogs();
        logRefreshInterval = setInterval(loadLogs, 5000);
    }

    // Stop log refresh
    function stopLogRefresh() {
        console.log('Stopping log refresh interval');
        clearInterval(logRefreshInterval);
    }

    // Clear logs
    $('.clear-logs').on('click', function () {
        if (!confirm(sewnWsAdmin.i18n.confirmClearLogs)) {
            return;
        }

        $.post(ajaxurl, {
            action: 'sewn_ws_clear_logs',
            nonce: sewnWsAdmin.nonce
        }, function (response) {
            if (response.success) {
                $('.log-content').empty();
                lastLogId = 0;
            }
        });
    });

    // Export logs
    $('.export-logs').on('click', function () {
        window.location.href = ajaxurl + '?' + $.param({
            action: 'sewn_ws_export_logs',
            nonce: sewnWsAdmin.nonce
        });
    });

    // Filter logs
    $('.log-level-filter').on('change', function () {
        var level = $(this).val();
        $('.log-entry.' + level).toggle($(this).is(':checked'));
    });

    // Initialize if debug mode is enabled
    if ($('#sewn_ws_debug_enabled').is(':checked')) {
        console.log('Debug mode is enabled on load, starting refresh');
        startLogRefresh();
    } else {
        console.log('Debug mode is disabled on load');
    }
}); 