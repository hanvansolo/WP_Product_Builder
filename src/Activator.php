<?php
/**
 * Plugin Activator
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder;

use WPProductBuilder\Database\Migrator;

/**
 * Handles plugin activation
 */
class Activator {
    /**
     * Run activation tasks
     */
    public static function activate(): void {
        // Create database tables
        Migrator::migrate();

        // Register custom capabilities
        self::registerCapabilities();

        // Set default options
        self::setDefaultOptions();

        // Schedule cron events
        self::scheduleCronEvents();

        // Clear rewrite rules
        flush_rewrite_rules();

        // Set activation flag for redirect
        set_transient('wpb_activated', true, 30);
    }

    /**
     * Public method to ensure capabilities exist (called on upgrade too)
     */
    public static function ensureCapabilities(): void {
        self::registerCapabilities();
    }

    /**
     * Register custom capabilities for roles
     */
    private static function registerCapabilities(): void {
        // Administrator gets all capabilities
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_wpb_settings');
            $admin->add_cap('wpb_generate_content');
            $admin->add_cap('wpb_view_history');
            $admin->add_cap('wpb_manage_templates');
        }

        // Editor can generate content and view history
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('wpb_generate_content');
            $editor->add_cap('wpb_view_history');
        }

        // Author can only generate content
        $author = get_role('author');
        if ($author) {
            $author->add_cap('wpb_generate_content');
        }
    }

    /**
     * Set default plugin options
     */
    private static function setDefaultOptions(): void {
        $default_settings = [
            'claude_model' => 'claude-sonnet-4-20250514',
            'amazon_marketplace' => 'US',
            'default_post_status' => 'draft',
            'cache_duration_hours' => 24,
            'auto_insert_schema' => true,
            'enable_price_updates' => false,
            'price_update_frequency' => 'daily',
            'affiliate_disclosure' => 'This post contains affiliate links. If you click through and make a purchase, we may earn a commission at no additional cost to you.',
            'remove_data_on_uninstall' => false,
        ];

        // Only set if not already exists
        if (!get_option('wpb_settings')) {
            add_option('wpb_settings', $default_settings);
        }
    }

    /**
     * Schedule cron events for automated tasks
     */
    private static function scheduleCronEvents(): void {
        // Schedule daily price update check
        if (!wp_next_scheduled('wpb_daily_price_update')) {
            wp_schedule_event(time(), 'daily', 'wpb_daily_price_update');
        }

        // Schedule weekly cache cleanup
        if (!wp_next_scheduled('wpb_weekly_cache_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'wpb_weekly_cache_cleanup');
        }
    }
}
