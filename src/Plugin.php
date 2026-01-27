<?php
/**
 * Main Plugin Class
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder;

use WPProductBuilder\Admin\AdminMenu;
use WPProductBuilder\Admin\AssetsLoader;
use WPProductBuilder\API\Endpoints\ProductSearchEndpoint;
use WPProductBuilder\API\Endpoints\ContentGenerateEndpoint;
use WPProductBuilder\API\Endpoints\SettingsEndpoint;
use WPProductBuilder\API\Endpoints\ImportEndpoint;
use WPProductBuilder\API\Endpoints\StatisticsEndpoint;
use WPProductBuilder\API\Endpoints\BulkContentEndpoint;
use WPProductBuilder\Blocks\BlockRegistrar;
use WPProductBuilder\WooCommerce\AutoImporter;
use WPProductBuilder\Content\BulkGenerator;
use WPProductBuilder\Services\StatisticsTracker;

/**
 * Main plugin class - Singleton pattern
 */
final class Plugin {
    /**
     * Plugin instance
     */
    private static ?Plugin $instance = null;

    /**
     * Plugin version
     */
    private string $version;

    /**
     * Plugin name/slug
     */
    private string $plugin_name = 'wp-product-builder';

    /**
     * Get the singleton instance
     */
    public static function getInstance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->version = WPB_VERSION;
        $this->registerHooks();
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup(): void {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks(): void {
        // Initialize plugin
        add_action('init', [$this, 'init']);
        add_action('init', [$this, 'loadTextDomain']);

        // Register REST API routes
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Admin-only hooks
        if (is_admin()) {
            add_action('admin_init', [$this, 'adminInit']);
        }

        // Register block assets
        add_action('init', [$this, 'registerBlocks']);

        // Register cron hooks
        $this->registerCronHooks();

        // Register affiliate link redirect handler
        add_action('template_redirect', [$this, 'handleAffiliateRedirect']);

        // Register click tracking for AJAX
        add_action('wp_ajax_wpb_track_click', [$this, 'ajaxTrackClick']);
        add_action('wp_ajax_nopriv_wpb_track_click', [$this, 'ajaxTrackClick']);
    }

    /**
     * Initialize plugin components
     */
    public function init(): void {
        // Initialize admin components
        if (is_admin()) {
            new AdminMenu();
            new AssetsLoader();
        }

        // Initialize statistics tracker for frontend views
        if (!is_admin()) {
            add_action('wp', [$this, 'trackProductViews']);
        }
    }

    /**
     * Register cron hooks and schedules
     */
    private function registerCronHooks(): void {
        // Add custom cron schedules
        add_filter('cron_schedules', function ($schedules) {
            $schedules['wpb_five_minutes'] = [
                'interval' => 300,
                'display' => __('Every 5 Minutes', 'wp-product-builder'),
            ];
            $schedules['wpb_fifteen_minutes'] = [
                'interval' => 900,
                'display' => __('Every 15 Minutes', 'wp-product-builder'),
            ];
            return $schedules;
        });

        // AutoImporter cron hooks
        AutoImporter::registerCronHooks();

        // BulkGenerator cron hooks
        BulkGenerator::registerCronHooks();

        // Schedule recurring events on activation
        add_action('wpb_schedule_cron_events', [$this, 'scheduleCronEvents']);
    }

    /**
     * Schedule cron events
     */
    public function scheduleCronEvents(): void {
        // Process import queue every 5 minutes
        if (!wp_next_scheduled('wpb_process_import_queue')) {
            wp_schedule_event(time(), 'wpb_five_minutes', 'wpb_process_import_queue');
        }

        // Run scheduled import jobs every 15 minutes
        if (!wp_next_scheduled('wpb_run_scheduled_jobs')) {
            wp_schedule_event(time(), 'wpb_fifteen_minutes', 'wpb_run_scheduled_jobs');
        }

        // Process content queue every 15 minutes
        if (!wp_next_scheduled('wpb_process_content_queue')) {
            wp_schedule_event(time(), 'wpb_fifteen_minutes', 'wpb_process_content_queue');
        }

        // Run scheduled content jobs hourly
        if (!wp_next_scheduled('wpb_run_scheduled_content_jobs')) {
            wp_schedule_event(time(), 'hourly', 'wpb_run_scheduled_content_jobs');
        }
    }

    /**
     * Track product views on frontend
     */
    public function trackProductViews(): void {
        if (!is_singular('product') && !is_singular('post')) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        // Get ASINs from post meta
        $asins = get_post_meta($post_id, '_wpb_product_asins', true);
        if (empty($asins)) {
            return;
        }

        $tracker = new StatisticsTracker();
        foreach ((array) $asins as $asin) {
            $tracker->trackView($asin, $post_id);
        }
    }

    /**
     * Handle affiliate link redirects
     */
    public function handleAffiliateRedirect(): void {
        if (!isset($_GET['wpb_affiliate'])) {
            return;
        }

        $asin = sanitize_text_field(wp_unslash($_GET['wpb_affiliate']));
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : null;

        if (empty($asin)) {
            return;
        }

        // Track the click
        $tracker = new StatisticsTracker();
        $tracker->trackClick($asin, $post_id, [
            'referrer' => wp_get_referer(),
        ]);

        // Get affiliate URL and redirect
        $settings = get_option('wpb_settings', []);
        $tag = $settings['amazon_tracking_id'] ?? '';
        $marketplace = $settings['amazon_marketplace'] ?? 'US';

        $domains = [
            'US' => 'amazon.com',
            'UK' => 'amazon.co.uk',
            'DE' => 'amazon.de',
            'FR' => 'amazon.fr',
            'ES' => 'amazon.es',
            'IT' => 'amazon.it',
            'CA' => 'amazon.ca',
            'JP' => 'amazon.co.jp',
            'AU' => 'amazon.com.au',
        ];

        $domain = $domains[$marketplace] ?? 'amazon.com';
        $url = "https://www.{$domain}/dp/{$asin}";

        if (!empty($tag)) {
            $url .= "?tag={$tag}";
        }

        wp_redirect($url, 301);
        exit;
    }

    /**
     * AJAX handler for click tracking
     */
    public function ajaxTrackClick(): void {
        check_ajax_referer('wpb_track_click', 'nonce');

        $asin = isset($_POST['asin']) ? sanitize_text_field(wp_unslash($_POST['asin'])) : '';
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : null;

        if (empty($asin)) {
            wp_send_json_error(['message' => 'ASIN required']);
        }

        $tracker = new StatisticsTracker();
        $tracker->trackClick($asin, $post_id);

        wp_send_json_success();
    }

    /**
     * Admin initialization
     */
    public function adminInit(): void {
        // Check for required database updates
        $this->checkDatabaseVersion();
    }

    /**
     * Load plugin text domain for translations
     */
    public function loadTextDomain(): void {
        load_plugin_textdomain(
            'wp-product-builder',
            false,
            dirname(WPB_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void {
        // Product search endpoint
        $product_search = new ProductSearchEndpoint();
        $product_search->register_routes();

        // Content generation endpoint
        $content_generate = new ContentGenerateEndpoint();
        $content_generate->register_routes();

        // Settings endpoint
        $settings = new SettingsEndpoint();
        $settings->register_routes();

        // Import endpoint (WooCommerce & Auto-import)
        $import = new ImportEndpoint();
        $import->register_routes();

        // Statistics endpoint
        $statistics = new StatisticsEndpoint();
        $statistics->register_routes();

        // Bulk content endpoint
        $bulk_content = new BulkContentEndpoint();
        $bulk_content->register_routes();
    }

    /**
     * Register Gutenberg blocks
     */
    public function registerBlocks(): void {
        // Only register if Gutenberg is available
        if (function_exists('register_block_type')) {
            new BlockRegistrar();
        }
    }

    /**
     * Check and update database version if needed
     */
    private function checkDatabaseVersion(): void {
        $current_version = get_option('wpb_db_version', '0');

        if (version_compare($current_version, $this->version, '<')) {
            Database\Migrator::migrate();
            update_option('wpb_db_version', $this->version);
        }
    }

    /**
     * Get plugin version
     */
    public function getVersion(): string {
        return $this->version;
    }

    /**
     * Get plugin name/slug
     */
    public function getPluginName(): string {
        return $this->plugin_name;
    }

    /**
     * Get plugin directory path
     */
    public function getPluginDir(): string {
        return WPB_PLUGIN_DIR;
    }

    /**
     * Get plugin URL
     */
    public function getPluginUrl(): string {
        return WPB_PLUGIN_URL;
    }
}
