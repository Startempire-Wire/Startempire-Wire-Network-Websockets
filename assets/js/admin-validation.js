/**
 * Location: Admin interface validation utilities
 * Dependencies: jQuery, WordPress AJAX API, sewn_ws_admin localized object
 * Variables: validateConfig object, DOM elements for settings fields
 * 
 * Handles client-side validation of WebSocket server configuration in WordPress admin. Provides
 * real-time feedback and pre-save validation checks for critical server settings like ports, SSL
 * certificates, and rate limiting rules. Integrates with backend via AJAX for configuration tests.
 */

jQuery(document).ready(function ($) {
    const validateConfig = {
        init() {
            this.bindEvents();
            this.setupLiveValidation();
        },

        bindEvents() {
            $('#sewn-ws-test-config').on('click', this.testConfiguration);
            $('#sewn-ws-settings-form').on('submit', this.validateBeforeSave);
            $('.sewn-ws-validate-field').on('change', this.validateField);
        },

        setupLiveValidation() {
            const fields = {
                'sewn_ws_port': this.validatePort,
                'sewn_ws_host': this.validateHost,
                'sewn_ws_ssl_cert': this.validateSSLPath,
                'sewn_ws_rate_limit': this.validateRateLimit
            };

            Object.entries(fields).forEach(([id, validator]) => {
                $(`#${id}`).on('input', function () {
                    validator($(this));
                });
            });
        },

        async testConfiguration(e) {
            e.preventDefault();
            const button = $(this);
            const resultDiv = $('#sewn-ws-test-result');

            button.prop('disabled', true);
            resultDiv.html('<span class="spinner is-active"></span> Testing configuration...');

            try {
                const response = await $.post(ajaxurl, {
                    action: 'sewn_ws_test_config',
                    nonce: sewn_ws_admin.nonce
                });

                if (response.success) {
                    resultDiv.html('<div class="notice notice-success"><p>Configuration test successful!</p></div>');
                } else {
                    resultDiv.html(`<div class="notice notice-error"><p>Configuration test failed: ${response.data.message}</p></div>`);
                }
            } catch (error) {
                resultDiv.html('<div class="notice notice-error"><p>Test failed: Network error</p></div>');
            } finally {
                button.prop('disabled', false);
            }
        },

        validateField(field) {
            const value = field.val();
            const type = field.data('validate');
            let isValid = true;
            let message = '';

            switch (type) {
                case 'port':
                    isValid = value >= 1024 && value <= 65535;
                    message = 'Port must be between 1024 and 65535';
                    break;

                case 'host':
                    isValid = /^[a-zA-Z0-9.-]+$/.test(value);
                    message = 'Invalid hostname format';
                    break;

                case 'rate':
                    isValid = value > 0;
                    message = 'Rate limit must be greater than 0';
                    break;
            }

            this.showFieldValidation(field, isValid, message);
            return isValid;
        },

        showFieldValidation(field, isValid, message) {
            const feedback = field.next('.sewn-ws-validation-feedback');

            if (isValid) {
                feedback.removeClass('notice-error').addClass('notice-success').html('✓');
            } else {
                feedback.removeClass('notice-success').addClass('notice-error').html(message);
            }
        },

        validateBeforeSave(e) {
            const form = $(this);
            let isValid = true;

            $('.sewn-ws-validate-field').each(function () {
                if (!validateConfig.validateField($(this))) {
                    isValid = false;
                }
            });

            if (!isValid) {
                e.preventDefault();
                $('#sewn-ws-validation-summary').html(
                    '<div class="notice notice-error"><p>Please correct the configuration errors before saving.</p></div>'
                );
            }
        }
    };

    validateConfig.init();
}); 