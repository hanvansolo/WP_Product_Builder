<?php
/**
 * Statistics Tracker
 *
 * Tracks product views, clicks, and conversions (WZone-like feature)
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Services;

/**
 * Handles statistics tracking for products and affiliate links
 */
class StatisticsTracker {
    /**
     * Stats table
     */
    private string $statsTable;

    /**
     * Clicks table
     */
    private string $clicksTable;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->statsTable = $wpdb->prefix . 'wpb_product_stats';
        $this->clicksTable = $wpdb->prefix . 'wpb_click_tracking';
    }

    /**
     * Track product view
     *
     * @param string $asin Product ASIN
     * @param int|null $postId Post ID where viewed
     */
    public function trackView(string $asin, ?int $postId = null): void {
        global $wpdb;

        $today = current_time('Y-m-d');

        // Update or insert daily stat
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->statsTable}
             (asin, post_id, date_recorded, views, clicks, conversions, revenue)
             VALUES (%s, %d, %s, 1, 0, 0, 0)
             ON DUPLICATE KEY UPDATE views = views + 1",
            strtoupper($asin),
            $postId ?? 0,
            $today
        ));
    }

    /**
     * Track affiliate link click
     *
     * @param string $asin Product ASIN
     * @param int|null $postId Post ID
     * @param array $metadata Additional metadata
     */
    public function trackClick(string $asin, ?int $postId = null, array $metadata = []): void {
        global $wpdb;

        $today = current_time('Y-m-d');
        $asin = strtoupper($asin);

        // Update daily stat
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->statsTable}
             (asin, post_id, date_recorded, views, clicks, conversions, revenue)
             VALUES (%s, %d, %s, 0, 1, 0, 0)
             ON DUPLICATE KEY UPDATE clicks = clicks + 1",
            $asin,
            $postId ?? 0,
            $today
        ));

        // Log detailed click
        $wpdb->insert($this->clicksTable, [
            'asin' => $asin,
            'post_id' => $postId ?? 0,
            'ip_hash' => $this->hashIp($this->getClientIp()),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'metadata' => json_encode($metadata),
            'clicked_at' => current_time('mysql'),
        ]);
    }

    /**
     * Track conversion
     *
     * @param string $asin Product ASIN
     * @param int|null $postId Post ID
     * @param float $revenue Revenue amount
     */
    public function trackConversion(string $asin, ?int $postId = null, float $revenue = 0): void {
        global $wpdb;

        $today = current_time('Y-m-d');

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->statsTable}
             (asin, post_id, date_recorded, views, clicks, conversions, revenue)
             VALUES (%s, %d, %s, 0, 0, 1, %f)
             ON DUPLICATE KEY UPDATE conversions = conversions + 1, revenue = revenue + %f",
            strtoupper($asin),
            $postId ?? 0,
            $today,
            $revenue,
            $revenue
        ));
    }

    /**
     * Get product statistics
     *
     * @param string $asin Product ASIN
     * @param string $period Period: today, week, month, year, all
     * @return array Statistics
     */
    public function getProductStats(string $asin, string $period = 'month'): array {
        global $wpdb;

        $dateCondition = $this->getDateCondition($period);

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(views) as total_views,
                SUM(clicks) as total_clicks,
                SUM(conversions) as total_conversions,
                SUM(revenue) as total_revenue
             FROM {$this->statsTable}
             WHERE asin = %s {$dateCondition}",
            strtoupper($asin)
        ), ARRAY_A);

        return [
            'asin' => $asin,
            'period' => $period,
            'views' => (int) ($stats['total_views'] ?? 0),
            'clicks' => (int) ($stats['total_clicks'] ?? 0),
            'conversions' => (int) ($stats['total_conversions'] ?? 0),
            'revenue' => (float) ($stats['total_revenue'] ?? 0),
            'ctr' => $this->calculateCtr($stats['total_views'] ?? 0, $stats['total_clicks'] ?? 0),
        ];
    }

    /**
     * Get top performing products
     *
     * @param int $limit Number of products
     * @param string $sortBy Sort by: views, clicks, conversions, revenue
     * @param string $period Period
     * @return array Products with stats
     */
    public function getTopProducts(int $limit = 10, string $sortBy = 'views', string $period = 'month'): array {
        global $wpdb;

        $dateCondition = $this->getDateCondition($period);

        $orderBy = match($sortBy) {
            'clicks' => 'total_clicks DESC',
            'ctr' => '(SUM(clicks) / NULLIF(SUM(views), 0)) DESC',
            'conversions' => 'total_conversions DESC',
            'revenue' => 'total_revenue DESC',
            default => 'total_views DESC',
        };

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                asin,
                SUM(views) as total_views,
                SUM(clicks) as total_clicks,
                SUM(conversions) as total_conversions,
                SUM(revenue) as total_revenue,
                ROUND(SUM(clicks) / NULLIF(SUM(views), 0) * 100, 2) as ctr
             FROM {$this->statsTable}
             WHERE 1=1 {$dateCondition}
             GROUP BY asin
             ORDER BY {$orderBy}
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Get trending products (most growth in last period)
     *
     * @param int $limit Number of products
     * @return array Trending products
     */
    public function getTrendingProducts(int $limit = 5): array {
        global $wpdb;

        // Compare this week vs last week
        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                asin,
                SUM(CASE WHEN date_recorded >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN views ELSE 0 END) as this_week_views,
                SUM(CASE WHEN date_recorded < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    AND date_recorded >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) THEN views ELSE 0 END) as last_week_views,
                SUM(CASE WHEN date_recorded >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN clicks ELSE 0 END) as this_week_clicks
             FROM {$this->statsTable}
             WHERE date_recorded >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             GROUP BY asin
             HAVING this_week_views > last_week_views AND last_week_views > 0
             ORDER BY (this_week_views - last_week_views) DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Get overall statistics
     *
     * @param string $period Period
     * @return array Overall stats
     */
    public function getOverallStats(string $period = 'month'): array {
        global $wpdb;

        $dateCondition = $this->getDateCondition($period);

        $stats = $wpdb->get_row(
            "SELECT
                COUNT(DISTINCT asin) as unique_products,
                SUM(views) as total_views,
                SUM(clicks) as total_clicks,
                SUM(conversions) as total_conversions,
                SUM(revenue) as total_revenue
             FROM {$this->statsTable}
             WHERE 1=1 {$dateCondition}",
            ARRAY_A
        );

        return [
            'period' => $period,
            'unique_products' => (int) ($stats['unique_products'] ?? 0),
            'total_views' => (int) ($stats['total_views'] ?? 0),
            'total_clicks' => (int) ($stats['total_clicks'] ?? 0),
            'total_conversions' => (int) ($stats['total_conversions'] ?? 0),
            'total_revenue' => (float) ($stats['total_revenue'] ?? 0),
            'overall_ctr' => $this->calculateCtr($stats['total_views'] ?? 0, $stats['total_clicks'] ?? 0),
        ];
    }

    /**
     * Get daily statistics for charts
     *
     * @param int $days Number of days
     * @return array Daily stats
     */
    public function getDailyStats(int $days = 30): array {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                date_recorded,
                SUM(views) as views,
                SUM(clicks) as clicks,
                SUM(conversions) as conversions,
                SUM(revenue) as revenue
             FROM {$this->statsTable}
             WHERE date_recorded >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             GROUP BY date_recorded
             ORDER BY date_recorded ASC",
            $days
        ), ARRAY_A);
    }

    /**
     * Get statistics by post
     *
     * @param int $postId Post ID
     * @param string $period Period
     * @return array Post statistics
     */
    public function getPostStats(int $postId, string $period = 'month'): array {
        global $wpdb;

        $dateCondition = $this->getDateCondition($period);

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(DISTINCT asin) as products,
                SUM(views) as total_views,
                SUM(clicks) as total_clicks,
                SUM(conversions) as total_conversions,
                SUM(revenue) as total_revenue
             FROM {$this->statsTable}
             WHERE post_id = %d {$dateCondition}",
            $postId
        ), ARRAY_A);

        return [
            'post_id' => $postId,
            'period' => $period,
            'products' => (int) ($stats['products'] ?? 0),
            'views' => (int) ($stats['total_views'] ?? 0),
            'clicks' => (int) ($stats['total_clicks'] ?? 0),
            'conversions' => (int) ($stats['total_conversions'] ?? 0),
            'revenue' => (float) ($stats['total_revenue'] ?? 0),
        ];
    }

    /**
     * Get date condition SQL
     */
    private function getDateCondition(string $period): string {
        return match($period) {
            'today', 'day' => "AND date_recorded = CURDATE()",
            'week' => "AND date_recorded >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            'month' => "AND date_recorded >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            'year' => "AND date_recorded >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)",
            default => "",
        };
    }

    /**
     * Calculate CTR
     */
    private function calculateCtr(int $views, int $clicks): float {
        if ($views === 0) {
            return 0.0;
        }
        return round(($clicks / $views) * 100, 2);
    }

    /**
     * Get client IP
     */
    private function getClientIp(): string {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Anonymize IP (GDPR compliance)
        return $this->anonymizeIp($ip);
    }

    /**
     * Hash IP for storage (GDPR compliant)
     */
    private function hashIp(string $ip): string {
        return hash('sha256', $ip . wp_salt('auth'));
    }

    /**
     * Anonymize IP address
     */
    private function anonymizeIp(string $ip): string {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return preg_replace('/:[\da-f]+:[\da-f]+:[\da-f]+:[\da-f]+$/i', ':0:0:0:0', $ip);
        }

        return '0.0.0.0';
    }

    /**
     * Clean old click logs
     *
     * @param int $daysToKeep Days to keep detailed logs
     */
    public function cleanOldLogs(int $daysToKeep = 90): int {
        global $wpdb;

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->clicksTable}
             WHERE clicked_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $daysToKeep
        ));
    }

    /**
     * Export statistics to CSV
     *
     * @param string $period Period
     * @return string CSV content
     */
    public function exportToCsv(string $period = 'month'): string {
        $products = $this->getTopProducts(1000, 'views', $period);

        $csv = "ASIN,Views,Clicks,Conversions,Revenue,CTR\n";

        foreach ($products as $product) {
            $csv .= sprintf(
                "%s,%d,%d,%d,%.2f,%.2f%%\n",
                $product['asin'],
                $product['total_views'],
                $product['total_clicks'],
                $product['total_conversions'],
                $product['total_revenue'],
                $product['ctr']
            );
        }

        return $csv;
    }
}
