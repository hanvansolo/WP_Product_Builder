<?php
/**
 * Statistics REST API Endpoint
 *
 * Handles product statistics and analytics
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\API\Endpoints;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPProductBuilder\Services\StatisticsTracker;

/**
 * REST API endpoints for statistics
 */
class StatisticsEndpoint extends WP_REST_Controller {
    /**
     * Namespace
     */
    protected $namespace = 'wp-product-builder/v1';

    /**
     * Resource name
     */
    protected $rest_base = 'statistics';

    /**
     * Statistics tracker
     */
    private StatisticsTracker $tracker;

    /**
     * Constructor
     */
    public function __construct() {
        $this->tracker = new StatisticsTracker();
    }

    /**
     * Register routes
     */
    public function register_routes(): void {
        // Get dashboard overview
        register_rest_route($this->namespace, '/' . $this->rest_base . '/overview', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_overview'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'period' => [
                        'type' => 'string',
                        'default' => 'month',
                        'enum' => ['day', 'week', 'month', 'year', 'all'],
                    ],
                ],
            ],
        ]);

        // Get top products
        register_rest_route($this->namespace, '/' . $this->rest_base . '/top-products', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_top_products'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'limit' => [
                        'type' => 'integer',
                        'default' => 10,
                    ],
                    'sort_by' => [
                        'type' => 'string',
                        'default' => 'views',
                        'enum' => ['views', 'clicks', 'conversions', 'revenue'],
                    ],
                    'period' => [
                        'type' => 'string',
                        'default' => 'month',
                    ],
                ],
            ],
        ]);

        // Get stats for specific product
        register_rest_route($this->namespace, '/' . $this->rest_base . '/product/(?P<asin>[A-Z0-9]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_product_stats'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'period' => [
                        'type' => 'string',
                        'default' => 'month',
                    ],
                ],
            ],
        ]);

        // Get stats for specific post
        register_rest_route($this->namespace, '/' . $this->rest_base . '/post/(?P<post_id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_post_stats'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'period' => [
                        'type' => 'string',
                        'default' => 'month',
                    ],
                ],
            ],
        ]);

        // Get click-through rate data
        register_rest_route($this->namespace, '/' . $this->rest_base . '/ctr', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_ctr_data'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'period' => [
                        'type' => 'string',
                        'default' => 'month',
                    ],
                ],
            ],
        ]);

        // Get trending products
        register_rest_route($this->namespace, '/' . $this->rest_base . '/trending', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_trending'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'limit' => [
                        'type' => 'integer',
                        'default' => 5,
                    ],
                ],
            ],
        ]);

        // Export statistics
        register_rest_route($this->namespace, '/' . $this->rest_base . '/export', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'export_stats'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'format' => [
                        'type' => 'string',
                        'default' => 'csv',
                        'enum' => ['csv', 'json'],
                    ],
                    'period' => [
                        'type' => 'string',
                        'default' => 'month',
                    ],
                ],
            ],
        ]);

        // Record conversion (for external tracking)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/conversion', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'record_conversion'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'asin' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                    'post_id' => [
                        'type' => 'integer',
                    ],
                    'revenue' => [
                        'type' => 'number',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Check permission
     */
    public function check_permission(): bool {
        return current_user_can('manage_options');
    }

    /**
     * Get overview statistics
     */
    public function get_overview(WP_REST_Request $request): WP_REST_Response {
        $period = $request->get_param('period');
        $date_range = $this->getDateRange($period);

        global $wpdb;
        $stats_table = $wpdb->prefix . 'wpb_product_stats';

        // Get totals
        $totals = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(views) as total_views,
                SUM(clicks) as total_clicks,
                SUM(conversions) as total_conversions,
                SUM(revenue) as total_revenue,
                COUNT(DISTINCT asin) as unique_products
             FROM {$stats_table}
             WHERE date_recorded BETWEEN %s AND %s",
            $date_range['start'],
            $date_range['end']
        ), ARRAY_A);

        // Calculate CTR
        $ctr = 0;
        if ($totals['total_views'] > 0) {
            $ctr = round(($totals['total_clicks'] / $totals['total_views']) * 100, 2);
        }

        // Get daily trend data
        $daily_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT
                date_recorded,
                SUM(views) as views,
                SUM(clicks) as clicks,
                SUM(conversions) as conversions
             FROM {$stats_table}
             WHERE date_recorded BETWEEN %s AND %s
             GROUP BY date_recorded
             ORDER BY date_recorded ASC",
            $date_range['start'],
            $date_range['end']
        ), ARRAY_A);

        return new WP_REST_Response([
            'success' => true,
            'overview' => [
                'total_views' => (int) ($totals['total_views'] ?? 0),
                'total_clicks' => (int) ($totals['total_clicks'] ?? 0),
                'total_conversions' => (int) ($totals['total_conversions'] ?? 0),
                'total_revenue' => (float) ($totals['total_revenue'] ?? 0),
                'unique_products' => (int) ($totals['unique_products'] ?? 0),
                'ctr' => $ctr,
            ],
            'trend' => $daily_stats,
            'period' => $period,
        ], 200);
    }

    /**
     * Get top products
     */
    public function get_top_products(WP_REST_Request $request): WP_REST_Response {
        $limit = $request->get_param('limit');
        $sort_by = $request->get_param('sort_by');
        $period = $request->get_param('period');

        $products = $this->tracker->getTopProducts($limit, $sort_by, $period);

        return new WP_REST_Response([
            'success' => true,
            'products' => $products,
        ], 200);
    }

    /**
     * Get stats for specific product
     */
    public function get_product_stats(WP_REST_Request $request): WP_REST_Response {
        $asin = strtoupper($request->get_param('asin'));
        $period = $request->get_param('period');

        $stats = $this->tracker->getProductStats($asin, $period);

        return new WP_REST_Response([
            'success' => true,
            'asin' => $asin,
            'stats' => $stats,
        ], 200);
    }

    /**
     * Get stats for specific post
     */
    public function get_post_stats(WP_REST_Request $request): WP_REST_Response {
        $post_id = (int) $request->get_param('post_id');
        $period = $request->get_param('period');

        $stats = $this->tracker->getPostStats($post_id, $period);

        return new WP_REST_Response([
            'success' => true,
            'post_id' => $post_id,
            'stats' => $stats,
        ], 200);
    }

    /**
     * Get CTR data
     */
    public function get_ctr_data(WP_REST_Request $request): WP_REST_Response {
        $period = $request->get_param('period');
        $date_range = $this->getDateRange($period);

        global $wpdb;
        $stats_table = $wpdb->prefix . 'wpb_product_stats';

        // Get products with best/worst CTR
        $ctr_data = $wpdb->get_results($wpdb->prepare(
            "SELECT
                asin,
                SUM(views) as views,
                SUM(clicks) as clicks,
                ROUND((SUM(clicks) / NULLIF(SUM(views), 0)) * 100, 2) as ctr
             FROM {$stats_table}
             WHERE date_recorded BETWEEN %s AND %s
             GROUP BY asin
             HAVING views > 10
             ORDER BY ctr DESC",
            $date_range['start'],
            $date_range['end']
        ), ARRAY_A);

        return new WP_REST_Response([
            'success' => true,
            'ctr_data' => $ctr_data,
        ], 200);
    }

    /**
     * Get trending products
     */
    public function get_trending(WP_REST_Request $request): WP_REST_Response {
        $limit = $request->get_param('limit');

        $trending = $this->tracker->getTrendingProducts($limit);

        return new WP_REST_Response([
            'success' => true,
            'trending' => $trending,
        ], 200);
    }

    /**
     * Export statistics
     */
    public function export_stats(WP_REST_Request $request): WP_REST_Response {
        $format = $request->get_param('format');
        $period = $request->get_param('period');
        $date_range = $this->getDateRange($period);

        global $wpdb;
        $stats_table = $wpdb->prefix . 'wpb_product_stats';

        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT
                asin,
                post_id,
                date_recorded,
                views,
                clicks,
                conversions,
                revenue
             FROM {$stats_table}
             WHERE date_recorded BETWEEN %s AND %s
             ORDER BY date_recorded DESC, asin ASC",
            $date_range['start'],
            $date_range['end']
        ), ARRAY_A);

        if ($format === 'csv') {
            $csv = "ASIN,Post ID,Date,Views,Clicks,Conversions,Revenue\n";
            foreach ($data as $row) {
                $csv .= implode(',', [
                    $row['asin'],
                    $row['post_id'] ?? '',
                    $row['date_recorded'],
                    $row['views'],
                    $row['clicks'],
                    $row['conversions'],
                    $row['revenue'],
                ]) . "\n";
            }

            return new WP_REST_Response([
                'success' => true,
                'format' => 'csv',
                'data' => $csv,
                'filename' => 'wpb-statistics-' . date('Y-m-d') . '.csv',
            ], 200);
        }

        return new WP_REST_Response([
            'success' => true,
            'format' => 'json',
            'data' => $data,
        ], 200);
    }

    /**
     * Record conversion
     */
    public function record_conversion(WP_REST_Request $request): WP_REST_Response {
        $asin = strtoupper($request->get_param('asin'));
        $post_id = $request->get_param('post_id');
        $revenue = $request->get_param('revenue') ?? 0;

        $this->tracker->trackConversion($asin, $post_id, $revenue);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Conversion recorded', 'wp-product-builder'),
        ], 200);
    }

    /**
     * Get date range for period
     */
    private function getDateRange(string $period): array {
        $end = date('Y-m-d');

        switch ($period) {
            case 'day':
                $start = $end;
                break;
            case 'week':
                $start = date('Y-m-d', strtotime('-7 days'));
                break;
            case 'month':
                $start = date('Y-m-d', strtotime('-30 days'));
                break;
            case 'year':
                $start = date('Y-m-d', strtotime('-1 year'));
                break;
            case 'all':
            default:
                $start = '2000-01-01';
                break;
        }

        return ['start' => $start, 'end' => $end];
    }
}
