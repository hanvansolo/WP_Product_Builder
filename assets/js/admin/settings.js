/**
 * WP Product Builder - Settings Page JavaScript
 */
(function($) {
    'use strict';

    var WPBSettings = {
        /**
         * Initialize settings page
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            $('#wpb-settings-form').on('submit', this.saveSettings);
            $('#wpb-clear-cache').on('click', this.clearCache);
        },

        /**
         * Save settings via AJAX
         */
        saveSettings: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $('#wpb-save-settings');
            var $spinner = $form.find('.spinner');

            // Collect form data
            var formData = {
                // Claude settings
                claude_api_key: $('#claude_api_key').val() || null,
                claude_model: $('#claude_model').val(),

                // Amazon settings
                amazon_access_key: $('#amazon_access_key').val() || null,
                amazon_secret_key: $('#amazon_secret_key').val() || null,
                amazon_partner_tag: $('#amazon_partner_tag').val(),
                amazon_marketplace: $('#amazon_marketplace').val(),

                // Content settings
                default_post_status: $('#default_post_status').val(),
                auto_insert_schema: $('#auto_insert_schema').is(':checked'),
                affiliate_disclosure: $('#affiliate_disclosure').val(),

                // Cache settings
                cache_duration_hours: parseInt($('#cache_duration_hours').val(), 10),
                enable_price_updates: $('#enable_price_updates').is(':checked'),

                // Advanced settings
                remove_data_on_uninstall: $('#remove_data_on_uninstall').is(':checked')
            };

            $button.prop('disabled', true).text(wpbAdmin.i18n.saving);
            $spinner.addClass('is-active');

            $.ajax({
                url: wpbAdmin.apiUrl + '/settings',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpbAdmin.nonce);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                },
                data: JSON.stringify(formData),
                success: function(response) {
                    WPBAdmin.showNotice(wpbAdmin.i18n.saved, 'success');

                    // Clear password fields (they're saved)
                    if (formData.claude_api_key) {
                        $('#claude_api_key').val('').attr('placeholder', '****');
                    }
                    if (formData.amazon_access_key) {
                        $('#amazon_access_key').val('').attr('placeholder', '****');
                    }
                    if (formData.amazon_secret_key) {
                        $('#amazon_secret_key').val('').attr('placeholder', '****');
                    }
                },
                error: function(xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : wpbAdmin.i18n.error;
                    WPBAdmin.showNotice(message, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Save Settings');
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Clear product cache
         */
        clearCache: function() {
            var $button = $(this);
            var $status = $('#wpb-cache-status');

            $button.prop('disabled', true);
            $status.html('<span class="wpb-loading"></span>');

            $.ajax({
                url: wpbAdmin.apiUrl + '/cache/clear',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpbAdmin.nonce);
                },
                success: function(response) {
                    $status.html('<span style="color: green;">Cache cleared!</span>');
                    setTimeout(function() {
                        $status.html('');
                    }, 3000);
                },
                error: function() {
                    $status.html('<span style="color: red;">Error clearing cache</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        }
    };

    $(document).ready(function() {
        WPBSettings.init();
    });

})(jQuery);
