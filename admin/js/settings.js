jQuery(document).ready(function ($) {
    'use strict';

    // Tab Navigation
    function initTabs() {
        $('.sewn-ws-tab-nav a').on('click', function (e) {
            e.preventDefault();
            const targetId = $(this).attr('href');

            // Update active states
            $('.sewn-ws-tab-nav a').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.sewn-ws-tab-content').removeClass('active');
            $(targetId).addClass('active');

            // Save active tab to sessionStorage
            sessionStorage.setItem('sewnWsActiveTab', targetId);
        });

        // Restore active tab from session
        const activeTab = sessionStorage.getItem('sewnWsActiveTab');
        if (activeTab) {
            $(`.sewn-ws-tab-nav a[href="${activeTab}"]`).trigger('click');
        } else {
            // Activate first tab by default
            $('.sewn-ws-tab-nav a:first').trigger('click');
        }
    }

    // Security utilities
    const Security = {
        escapeHtml: function (unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        },

        sanitizeInput: function (input) {
            if (typeof input !== 'string') {
                return input;
            }
            return input.trim().replace(/[<>]/g, '');
        },

        validateJson: function (data) {
            try {
                if (typeof data === 'string') {
                    data = JSON.parse(data);
                }
                return data;
            } catch (e) {
                console.error('Invalid JSON data:', e);
                return null;
            }
        }
    };

    // AJAX wrapper with security
    function secureAjax(action, data = {}) {
        return new Promise((resolve, reject) => {
            const secureData = {
                action: 'sewn_ws_' + action,
                nonce: sewnWsAdmin.nonce,
                ...Object.entries(data).reduce((acc, [key, value]) => {
                    acc[key] = Security.sanitizeInput(value);
                    return acc;
                }, {})
            };

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: secureData,
                success: function (response) {
                    const validatedData = Security.validateJson(response);
                    if (!validatedData) {
                        reject(new Error('Invalid response format'));
                        return;
                    }
                    resolve(validatedData);
                },
                error: function (xhr, status, error) {
                    reject(new Error(error));
                }
            });
        });
    }

    // Form Validation
    function initFormValidation() {
        const validateField = (element) => {
            const $field = $(element);
            const value = Security.sanitizeInput($field.val());
            const type = $field.data('validate');
            let isValid = true;
            let message = '';
            let validationState = {};

            switch (type) {
                case 'port':
                    const port = parseInt(value);
                    isValid = port >= 1024 && port <= 65535;
                    message = isValid ? '' : 'Port must be between 1024 and 65535';
                    validationState = { port: isValid };
                    break;
                case 'host':
                    isValid = /^[a-zA-Z0-9.-]+$/.test(value);
                    message = isValid ? '' : 'Invalid hostname format';
                    validationState = { host: isValid };
                    break;
                case 'rate':
                    const rate = parseInt(value);
                    isValid = rate > 0;
                    message = isValid ? '' : 'Rate limit must be greater than 0';
                    validationState = { rate: isValid };
                    break;
                case 'ssl_path':
                    isValid = value === '' || /^[\/\\](?:[^\/\\]*[\/\\])*[^\/\\]*$/.test(value);
                    message = isValid ? '' : 'Invalid file path format';
                    validationState = { ssl_path: isValid };
                    break;
            }

            // Store validation state securely
            try {
                sessionStorage.setItem('sewnWsValidation', JSON.stringify({
                    ...JSON.parse(sessionStorage.getItem('sewnWsValidation') || '{}'),
                    ...validationState
                }));
            } catch (e) {
                console.error('Failed to store validation state:', e);
            }

            const $container = $field.closest('.field-container');
            const $error = $container.find('.sewn-ws-field-error');
            const $indicator = $container.find('.sewn-ws-validation-indicator');

            if (!isValid) {
                if (!$error.length) {
                    $container.append(`<span class="sewn-ws-field-error">${Security.escapeHtml(message)}</span>`);
                }
                if (!$indicator.length) {
                    $container.append('<span class="sewn-ws-validation-indicator error"></span>');
                }
                $field.addClass('sewn-ws-field-invalid');
            } else {
                $error.remove();
                if (!$indicator.length) {
                    $container.append('<span class="sewn-ws-validation-indicator success"></span>');
                }
                $field.removeClass('sewn-ws-field-invalid');
            }

            return isValid;
        };

        // Restore validation state
        const restoreValidationState = () => {
            const state = JSON.parse(sessionStorage.getItem('sewnWsValidation') || '{}');
            $('.sewn-ws-validate-field').each(function () {
                validateField(this);
            });
        };

        // Validate on input with debounce
        let validationTimeout;
        $('.sewn-ws-validate-field').on('input', function () {
            const $field = $(this);
            clearTimeout(validationTimeout);
            validationTimeout = setTimeout(() => {
                validateField($field);
                updateFormState();
            }, 300);
        });

        // Update form submit button state
        const updateFormState = () => {
            const $form = $('#sewn-ws-settings-form');
            const $submit = $form.find('input[type="submit"]');
            const hasErrors = $form.find('.sewn-ws-field-invalid').length > 0;

            $submit.prop('disabled', hasErrors);
            if (hasErrors) {
                $submit.addClass('button-disabled');
            } else {
                $submit.removeClass('button-disabled');
            }
        };

        // Validate on form submit
        $('#sewn-ws-settings-form').on('submit', function (e) {
            let isValid = true;
            const $summary = $('#sewn-ws-validation-summary');
            const errors = [];

            $('.sewn-ws-validate-field').each(function () {
                if (!validateField(this)) {
                    isValid = false;
                    const fieldName = $(this).attr('name');
                    const errorMessage = $(this).next('.sewn-ws-field-error').text();
                    errors.push(`${fieldName}: ${errorMessage}`);
                }
            });

            if (!isValid) {
                e.preventDefault();
                $summary.html(`
                    <div class="notice notice-error">
                        <p>Please correct the following errors:</p>
                        <ul>
                            ${errors.map(error => `<li>${error}</li>`).join('')}
                        </ul>
                    </div>
                `);
                $summary[0].scrollIntoView({ behavior: 'smooth' });
            }
        });

        // Initialize validation state
        restoreValidationState();
        updateFormState();
    }

    // Environment Detection
    function initEnvironmentControls() {
        $('#sewn_ws_env_local_mode').on('change', function () {
            const isEnabled = $(this).is(':checked');
            $('.container-mode-row, .local-url-row, .ssl-config-row').toggleClass('disabled', !isEnabled);
            $('#sewn_ws_env_container_mode, #sewn_ws_local_site_url, #sewn_ws_ssl_cert, #sewn_ws_ssl_key')
                .prop('disabled', !isEnabled);
            $('.detect-ssl, .browse-ssl, .test-ssl, .reset-url').prop('disabled', !isEnabled);

            if (!isEnabled) {
                $('#sewn_ws_env_container_mode').prop('checked', false);
            }
        });

        // Reset URL to detected value
        $('.reset-url').on('click', function () {
            const $input = $('#sewn_ws_local_site_url');
            const detectedUrl = $input.data('detected-url');
            $input.val(detectedUrl);
            updateUrlStatus(detectedUrl);
        });
    }

    // SSL Certificate Management
    function initSSLManagement() {
        $('.detect-ssl').on('click', async function () {
            const $button = $(this);
            const type = $button.data('type');
            const $input = type === 'cert' ? $('#sewn_ws_ssl_cert') : $('#sewn_ws_ssl_key');

            $button.prop('disabled', true);

            try {
                const response = await secureAjax('get_ssl_paths', { type });
                if (response.success && response.data.paths.length > 0) {
                    $input.val(response.data.paths[0]);
                } else {
                    alert('No SSL certificates found in common locations.');
                }
            } catch (error) {
                console.error('SSL detection failed:', error);
                alert('Failed to detect SSL certificates.');
            } finally {
                $button.prop('disabled', false);
            }
        });

        // SSL certificate file browser
        $('.browse-ssl').on('click', function () {
            const $button = $(this);
            const type = $button.data('type');
            const $input = type === 'cert' ? $('#sewn_ws_ssl_cert') : $('#sewn_ws_ssl_key');

            const $fileInput = $('<input type="file">').hide();
            if (type === 'cert') {
                $fileInput.attr('accept', '.crt,.pem');
            } else {
                $fileInput.attr('accept', '.key');
            }

            $('body').append($fileInput);

            $fileInput.trigger('click');

            $fileInput.on('change', function () {
                const file = this.files[0];
                if (file) {
                    $input.val(file.name);
                }
                $fileInput.remove();
            });
        });

        // Test SSL configuration
        $('.test-ssl').on('click', function () {
            const $button = $(this);
            $button.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sewn_ws_test_ssl',
                    cert: $('#sewn_ws_ssl_cert').val(),
                    key: $('#sewn_ws_ssl_key').val(),
                    nonce: sewnWsAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        alert('SSL configuration is valid.');
                    } else {
                        alert('SSL configuration is invalid: ' + response.data);
                    }
                },
                error: function () {
                    alert('Failed to test SSL configuration.');
                },
                complete: function () {
                    $button.prop('disabled', false);
                }
            });
        });
    }

    // Debug Panel
    function initDebugPanel() {
        $('#sewn_ws_debug_enabled').on('change', function () {
            const isEnabled = $(this).is(':checked');
            $('.sewn-ws-debug-panel').toggle(isEnabled);

            if (isEnabled) {
                loadDebugLogs();
            }
        });

        $('.log-level-filter').on('change', function () {
            filterLogs();
        });

        $('.clear-logs').on('click', function () {
            clearLogs();
        });

        $('.export-logs').on('click', function () {
            exportLogs();
        });
    }

    // Module Overview
    function initModuleOverview() {
        $('.sewn-ws-module-card').each(function () {
            const $card = $(this);
            const moduleId = $card.data('module-id');

            // Check module dependencies
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sewn_ws_check_module_dependencies',
                    module: moduleId,
                    nonce: sewnWsAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        if (response.data.warnings.length > 0) {
                            const $warnings = $('<div class="module-warnings"></div>');
                            response.data.warnings.forEach(warning => {
                                $warnings.append(`<p class="warning">${warning}</p>`);
                            });
                            $card.append($warnings);
                        }
                    }
                }
            });
        });
    }

    function loadDebugLogs() {
        const $logContent = $('.log-content');
        const $loading = $('.log-loading');
        const filters = getActiveFilters();

        $loading.show();
        $logContent.empty();

        secureAjax('get_debug_logs', { filters })
            .then(response => {
                if (response.success && response.data.logs) {
                    renderLogs(response.data.logs);
                } else {
                    $logContent.html('<p class="no-logs">No logs found</p>');
                }
            })
            .catch(error => {
                $logContent.html(`<p class="error">Failed to load logs: ${Security.escapeHtml(error.message)}</p>`);
            })
            .finally(() => {
                $loading.hide();
            });
    }

    function getActiveFilters() {
        const filters = {};
        $('.log-level-filter:checked').each(function () {
            filters[$(this).val()] = true;
        });
        return filters;
    }

    function renderLogs(logs) {
        const $logContent = $('.log-content');
        $logContent.empty();

        logs.forEach(log => {
            const $entry = $('<div>', {
                class: `log-entry ${log.level}`,
                html: `
                    <span class="log-timestamp">${Security.escapeHtml(log.timestamp)}</span>
                    <span class="log-level ${log.level}">${Security.escapeHtml(log.level.toUpperCase())}</span>
                    <span class="log-message">${Security.escapeHtml(log.message)}</span>
                    ${log.context ? `<pre class="log-context">${Security.escapeHtml(JSON.stringify(log.context, null, 2))}</pre>` : ''}
                `
            });
            $logContent.append($entry);
        });
    }

    function clearLogs() {
        if (!confirm(sewnWsAdmin.i18n.confirmClearLogs)) {
            return;
        }

        secureAjax('clear_debug_logs')
            .then(response => {
                if (response.success) {
                    loadDebugLogs();
                } else {
                    alert('Failed to clear logs');
                }
            })
            .catch(error => {
                alert(`Error clearing logs: ${error.message}`);
            });
    }

    function exportLogs() {
        const filters = getActiveFilters();
        secureAjax('export_debug_logs', { filters })
            .then(response => {
                if (response.success && response.data.download_url) {
                    window.location.href = response.data.download_url;
                } else {
                    alert('Failed to export logs');
                }
            })
            .catch(error => {
                alert(`Error exporting logs: ${error.message}`);
            });
    }

    // Initialize all components
    function init() {
        initTabs();
        initFormValidation();
        initEnvironmentControls();
        initSSLManagement();
        initDebugPanel();
        initModuleOverview();
    }

    // Start initialization
    init();
}); 