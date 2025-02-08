jQuery(document).ready(function ($) {
    var logRefreshInterval;
    var lastLogId = 0;

    // Toggle debug panel
    $('#sewn_ws_debug_enabled').on('change', function () {
        var isEnabled = $(this).is(':checked');
        $('.sewn-ws-debug-panel').toggle(isEnabled);

        $.post(ajaxurl, {
            action: 'sewn_ws_toggle_debug',
            enabled: isEnabled,
            nonce: sewnWsAdmin.nonce
        });

        if (isEnabled) {
            startLogRefresh();
        } else {
            stopLogRefresh();
        }
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
        loadLogs();
        logRefreshInterval = setInterval(loadLogs, 5000);
    }

    // Stop log refresh
    function stopLogRefresh() {
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
        startLogRefresh();
    }
}); 