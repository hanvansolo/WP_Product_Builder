/**
 * WP Product Builder - Admin Core JavaScript
 */
(function($) {
    'use strict';

    window.WPBAdmin = {
        /**
         * Initialize admin scripts
         */
        init: function() {
            this.bindEvents();
            this.loadDashboardStats();
        },

        /**
         * Bind common events
         */
        bindEvents: function() {
            // Toggle password visibility
            $(document).on('click', '.wpb-toggle-password', this.togglePassword);

            // Test API connection
            $(document).on('click', '.wpb-test-connection', this.testConnection);

            // Modal close
            $(document).on('click', '.wpb-modal-close', this.closeModal);
            $(document).on('click', '.wpb-modal', function(e) {
                if (e.target === this) {
                    WPBAdmin.closeModal();
                }
            });

            // ESC key closes modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    WPBAdmin.closeModal();
                }
            });
        },

        /**
         * Toggle password field visibility
         */
        togglePassword: function() {
            var targetId = $(this).data('target');
            var $input = $('#' + targetId);
            var $icon = $(this).find('.dashicons');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        },

        /**
         * Test API connection
         */
        testConnection: function() {
            var api = $(this).data('api');
            var $button = $(this);
            var $result = $('#' + api + '-connection-result');

            $button.prop('disabled', true).text(wpbAdmin.i18n.testing_connection);
            $result.removeClass('success error').hide();

            // Get API key from form if available
            var data = { api: api };
            if (api === 'claude') {
                var apiKey = $('#claude_api_key').val();
                if (apiKey) {
                    data.api_key = apiKey;
                }
            } else if (api === 'amazon') {
                var accessKey = $('#amazon_access_key').val();
                var secretKey = $('#amazon_secret_key').val();
                var partnerTag = $('#amazon_partner_tag').val();
                if (accessKey) data.access_key = accessKey;
                if (secretKey) data.secret_key = secretKey;
                if (partnerTag) data.partner_tag = partnerTag;
            }

            $.ajax({
                url: wpbAdmin.apiUrl + '/test-connection',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpbAdmin.nonce);
                },
                data: data,
                success: function(response) {
                    $result.addClass('success')
                           .text(wpbAdmin.i18n.connection_success)
                           .show();
                },
                error: function(xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : wpbAdmin.i18n.connection_failed;
                    $result.addClass('error').text(message).show();
                },
                complete: function() {
                    $button.prop('disabled', false).text(wpbAdmin.i18n.test_connection || 'Test Connection');
                }
            });
        },

        /**
         * Load dashboard statistics
         */
        loadDashboardStats: function() {
            if (!$('#wpb-dashboard-stats').length) {
                return;
            }

            $.ajax({
                url: wpbAdmin.apiUrl + '/stats',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpbAdmin.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        $('#stat-content-count').text(response.data.content_count || 0);
                        $('#stat-products-cached').text(response.data.products_cached || 0);
                        $('#stat-tokens-used').text(WPBAdmin.formatNumber(response.data.tokens_used || 0));
                    }
                }
            });
        },

        /**
         * Show a notice message
         */
        showNotice: function(message, type, container) {
            type = type || 'info';
            container = container || '#wpb-settings-notices';

            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $(container).html($notice);

            // Make dismissible work
            if (typeof wp !== 'undefined' && wp.notices) {
                wp.notices.initialize();
            }
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.wpb-modal').fadeOut(200);
        },

        /**
         * Open modal
         */
        openModal: function(title, content) {
            $('#wpb-modal-title').text(title);
            $('#wpb-modal-body').html(content);
            $('#wpb-content-modal').fadeIn(200);
        },

        /**
         * Format number with commas
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        /**
         * Make API request
         */
        apiRequest: function(endpoint, method, data) {
            return $.ajax({
                url: wpbAdmin.apiUrl + '/' + endpoint,
                method: method || 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpbAdmin.nonce);
                },
                data: data,
                contentType: method === 'POST' ? 'application/json' : undefined,
                dataType: 'json'
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        WPBAdmin.init();
    });

})(jQuery);
