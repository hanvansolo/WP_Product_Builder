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
            $('#wpb-check-update').on('click', this.checkForUpdate);
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

                // CJ Affiliate settings
                cj_api_key: $('#cj_api_key').val() || null,
                cj_website_id: $('#cj_website_id').val(),

                // Awin settings
                awin_api_key: $('#awin_api_key').val() || null,
                awin_publisher_id: $('#awin_publisher_id').val(),

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
                    if (formData.cj_api_key) {
                        $('#cj_api_key').val('').attr('placeholder', '****');
                    }
                    if (formData.awin_api_key) {
                        $('#awin_api_key').val('').attr('placeholder', '****');
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
        /**
         * Check for plugin updates
         */
        checkForUpdate: function() {
            var $button = $('#wpb-check-update');
            var $status = $('#wpb-update-status');
            var $latestVersion = $('#wpb-latest-version');
            var $updateBtn = $('#wpb-do-update');
            var $badge = $('#wpb-update-badge');
            var $notes = $('#wpb-release-notes');

            $button.prop('disabled', true);
            $status.html('<span class="wpb-loading"></span> Checking...');
            $updateBtn.hide();
            $badge.hide();
            $notes.hide();

            $.ajax({
                url: wpbAdmin.apiUrl + '/update/check',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpbAdmin.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        $latestVersion.text('v' + response.latest_version);

                        if (response.has_update) {
                            $status.html('<span style="color: #d63638;">Update available!</span>');
                            $updateBtn.show();
                            $badge.show();
                            $('#wpb-update-badge-text').text('v' + response.latest_version);

                            if (response.release_notes) {
                                $('#wpb-release-notes-content').html(response.release_notes.replace(/\n/g, '<br>'));
                                $notes.show();
                            }
                        } else {
                            $status.html('<span style="color: #00a32a;">You are running the latest version.</span>');
                        }
                    } else {
                        $status.html('<span style="color: #d63638;">' + (response.message || 'Check failed.') + '</span>');
                    }
                },
                error: function(xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Could not check for updates.';
                    $status.html('<span style="color: #d63638;">' + message + '</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
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
