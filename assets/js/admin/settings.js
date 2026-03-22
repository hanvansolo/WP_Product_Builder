/**
 * Nito Product Builder - Settings Page JavaScript
 */
(function($) {
    'use strict';

    var WPBSettings = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#wpb-settings-form').on('submit', this.saveSettings);
            $('#wpb-clear-cache').on('click', this.clearCache);
            $('#wpb-check-update').on('click', this.checkForUpdate);
        },

        saveSettings: function(e) {
            e.preventDefault();

            var $button = $('#wpb-save-settings');
            var $spinner = $(this).find('.spinner');

            // Prevent double click
            if ($button.prop('disabled')) return;
            $button.prop('disabled', true).text('Saving...');
            $spinner.addClass('is-active');

            // Safely get form values
            var val = function(id) {
                var el = document.getElementById(id);
                return el ? el.value : '';
            };
            var checked = function(id) {
                var el = document.getElementById(id);
                return el ? el.checked : false;
            };

            var formData = {
                claude_api_key: val('claude_api_key') || null,
                claude_model: val('claude_model'),
                amazon_access_key: val('amazon_access_key') || null,
                amazon_secret_key: val('amazon_secret_key') || null,
                amazon_partner_tag: val('amazon_partner_tag'),
                amazon_marketplace: val('amazon_marketplace'),
                cj_api_key: val('cj_api_key') || null,
                cj_website_id: val('cj_website_id'),
                awin_api_key: val('awin_api_key') || null,
                awin_publisher_id: val('awin_publisher_id'),
                focus_category: val('focus_category'),
                default_post_status: val('default_post_status'),
                auto_insert_schema: checked('auto_insert_schema'),
                affiliate_disclosure: val('affiliate_disclosure'),
                cache_duration_hours: parseInt(val('cache_duration_hours'), 10) || 24,
                enable_price_updates: checked('enable_price_updates'),
                remove_data_on_uninstall: checked('remove_data_on_uninstall')
            };

            $.ajax({
                url: wpbAdmin.apiUrl + '/settings',
                method: 'POST',
                timeout: 30000,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpbAdmin.nonce);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                },
                data: JSON.stringify(formData),
                success: function() {
                    WPBAdmin.showNotice('Settings saved!', 'success');

                    // Clear password fields after save
                    var pwFields = ['claude_api_key', 'amazon_access_key', 'amazon_secret_key', 'cj_api_key', 'awin_api_key'];
                    pwFields.forEach(function(id) {
                        var el = document.getElementById(id);
                        if (el && el.value) {
                            el.value = '';
                            el.placeholder = '••••••••';
                        }
                    });
                },
                error: function(xhr) {
                    var message = 'Save failed.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    } else if (xhr.statusText === 'timeout') {
                        message = 'Request timed out. Try again.';
                    } else if (xhr.status === 0) {
                        message = 'Connection error. Check your internet.';
                    } else if (xhr.status === 403) {
                        message = 'Permission denied. You may need to refresh the page.';
                    }
                    WPBAdmin.showNotice(message, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Save Settings');
                    $spinner.removeClass('is-active');
                }
            });
        },

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
                timeout: 15000,
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
                error: function() {
                    $status.html('<span style="color: #d63638;">Could not check for updates.</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        clearCache: function() {
            var $button = $(this);
            var $status = $('#wpb-cache-status');

            $button.prop('disabled', true);
            $status.html('<span class="wpb-loading"></span>');

            $.ajax({
                url: wpbAdmin.apiUrl + '/cache/clear',
                method: 'POST',
                timeout: 15000,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpbAdmin.nonce);
                },
                success: function() {
                    $status.html('<span style="color: green;">Cache cleared!</span>');
                    setTimeout(function() { $status.html(''); }, 3000);
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
