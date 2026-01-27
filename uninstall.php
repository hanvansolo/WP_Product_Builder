<?php
/**
 * Uninstall WP Product Builder
 *
 * This file is executed when the plugin is deleted from WordPress.
 * It removes all plugin data from the database.
 *
 * @package WPProductBuilder
 */

// Exit if accessed directly or not uninstalling
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Check if user wants to remove all data (option can be set in settings)
$settings = get_option('wpb_settings', []);
$remove_data = $settings['remove_data_on_uninstall'] ?? false;

if ($remove_data) {
    // Drop custom tables
    $tables = [
        $wpdb->prefix . 'wpb_content_history',
        $wpdb->prefix . 'wpb_product_cache',
        $wpdb->prefix . 'wpb_templates',
        $wpdb->prefix . 'wpb_api_usage',
    ];

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    // Remove options
    $options = [
        'wpb_settings',
        'wpb_credentials_encrypted',
        'wpb_encryption_check',
        'wpb_encryption_check_plain',
        'wpb_db_version',
    ];

    foreach ($options as $option) {
        delete_option($option);
    }

    // Remove post meta from all posts
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_wpb_%'");

    // Remove user meta
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wpb_%'");

    // Remove capabilities
    $roles = ['administrator', 'editor'];
    $capabilities = [
        'manage_wpb_settings',
        'wpb_generate_content',
        'wpb_view_history',
        'wpb_manage_templates',
    ];

    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($capabilities as $cap) {
                $role->remove_cap($cap);
            }
        }
    }

    // Clear any transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpb_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpb_%'");
}
