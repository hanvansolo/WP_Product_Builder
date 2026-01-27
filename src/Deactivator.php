<?php
/**
 * Plugin Deactivator
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder;

/**
 * Handles plugin deactivation
 */
class Deactivator {
    /**
     * Run deactivation tasks
     */
    public static function deactivate(): void {
        // Clear scheduled cron events
        self::clearCronEvents();

        // Clear transients
        self::clearTransients();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Clear all plugin cron events
     */
    private static function clearCronEvents(): void {
        $cron_hooks = [
            'wpb_daily_price_update',
            'wpb_weekly_cache_cleanup',
        ];

        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }

    /**
     * Clear plugin transients
     */
    private static function clearTransients(): void {
        global $wpdb;

        // Delete all plugin transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpb_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpb_%'");
    }
}
