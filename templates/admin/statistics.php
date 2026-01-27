<?php
/**
 * Statistics Admin Page
 *
 * @package WPProductBuilder
 */

defined('ABSPATH') || exit;

use WPProductBuilder\Services\StatisticsTracker;

$tracker = new StatisticsTracker();
$period = $_GET['period'] ?? 'month';
$overall_stats = $tracker->getOverallStats($period);
$top_products = $tracker->getTopProducts(10, 'views', $period);
$daily_stats = $tracker->getDailyStats(30);
?>

<div class="wrap wpb-admin-page wpb-statistics-page">
    <h1><?php esc_html_e('Product Statistics', 'wp-product-builder'); ?></h1>

    <div class="wpb-period-filter">
        <label><?php esc_html_e('Period:', 'wp-product-builder'); ?></label>
        <select id="wpb-period-select" onchange="window.location.href='?page=wp-product-builder-statistics&period='+this.value">
            <option value="day" <?php selected($period, 'day'); ?>><?php esc_html_e('Today', 'wp-product-builder'); ?></option>
            <option value="week" <?php selected($period, 'week'); ?>><?php esc_html_e('Last 7 Days', 'wp-product-builder'); ?></option>
            <option value="month" <?php selected($period, 'month'); ?>><?php esc_html_e('Last 30 Days', 'wp-product-builder'); ?></option>
            <option value="year" <?php selected($period, 'year'); ?>><?php esc_html_e('Last Year', 'wp-product-builder'); ?></option>
            <option value="all" <?php selected($period, 'all'); ?>><?php esc_html_e('All Time', 'wp-product-builder'); ?></option>
        </select>
    </div>

    <div class="wpb-stats-grid">
        <div class="wpb-stat-card">
            <div class="wpb-stat-icon dashicons dashicons-visibility"></div>
            <div class="wpb-stat-content">
                <div class="wpb-stat-value"><?php echo esc_html(number_format($overall_stats['total_views'])); ?></div>
                <div class="wpb-stat-label"><?php esc_html_e('Total Views', 'wp-product-builder'); ?></div>
            </div>
        </div>

        <div class="wpb-stat-card">
            <div class="wpb-stat-icon dashicons dashicons-external"></div>
            <div class="wpb-stat-content">
                <div class="wpb-stat-value"><?php echo esc_html(number_format($overall_stats['total_clicks'])); ?></div>
                <div class="wpb-stat-label"><?php esc_html_e('Total Clicks', 'wp-product-builder'); ?></div>
            </div>
        </div>

        <div class="wpb-stat-card">
            <div class="wpb-stat-icon dashicons dashicons-chart-line"></div>
            <div class="wpb-stat-content">
                <div class="wpb-stat-value"><?php echo esc_html($overall_stats['overall_ctr']); ?>%</div>
                <div class="wpb-stat-label"><?php esc_html_e('Click-Through Rate', 'wp-product-builder'); ?></div>
            </div>
        </div>

        <div class="wpb-stat-card">
            <div class="wpb-stat-icon dashicons dashicons-cart"></div>
            <div class="wpb-stat-content">
                <div class="wpb-stat-value"><?php echo esc_html(number_format($overall_stats['total_conversions'])); ?></div>
                <div class="wpb-stat-label"><?php esc_html_e('Conversions', 'wp-product-builder'); ?></div>
            </div>
        </div>

        <div class="wpb-stat-card wpb-stat-card-wide">
            <div class="wpb-stat-icon dashicons dashicons-money-alt"></div>
            <div class="wpb-stat-content">
                <div class="wpb-stat-value">$<?php echo esc_html(number_format($overall_stats['total_revenue'], 2)); ?></div>
                <div class="wpb-stat-label"><?php esc_html_e('Estimated Revenue', 'wp-product-builder'); ?></div>
            </div>
        </div>

        <div class="wpb-stat-card">
            <div class="wpb-stat-icon dashicons dashicons-products"></div>
            <div class="wpb-stat-content">
                <div class="wpb-stat-value"><?php echo esc_html(number_format($overall_stats['unique_products'])); ?></div>
                <div class="wpb-stat-label"><?php esc_html_e('Active Products', 'wp-product-builder'); ?></div>
            </div>
        </div>
    </div>

    <div class="wpb-stats-row">
        <div class="wpb-stats-section wpb-chart-section">
            <h2><?php esc_html_e('Daily Performance', 'wp-product-builder'); ?></h2>
            <canvas id="wpb-stats-chart" height="300"></canvas>
        </div>

        <div class="wpb-stats-section wpb-top-products-section">
            <h2><?php esc_html_e('Top Performing Products', 'wp-product-builder'); ?></h2>
            <?php if (!empty($top_products)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ASIN', 'wp-product-builder'); ?></th>
                            <th><?php esc_html_e('Views', 'wp-product-builder'); ?></th>
                            <th><?php esc_html_e('Clicks', 'wp-product-builder'); ?></th>
                            <th><?php esc_html_e('CTR', 'wp-product-builder'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_products as $product) : ?>
                            <tr>
                                <td>
                                    <a href="https://www.amazon.com/dp/<?php echo esc_attr($product['asin']); ?>" target="_blank">
                                        <?php echo esc_html($product['asin']); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html(number_format($product['total_views'])); ?></td>
                                <td><?php echo esc_html(number_format($product['total_clicks'])); ?></td>
                                <td><?php echo esc_html($product['ctr']); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="wpb-no-data"><?php esc_html_e('No statistics data available yet.', 'wp-product-builder'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="wpb-export-section">
        <h2><?php esc_html_e('Export Statistics', 'wp-product-builder'); ?></h2>
        <p><?php esc_html_e('Download your statistics data for further analysis.', 'wp-product-builder'); ?></p>
        <button type="button" id="wpb-export-csv" class="button button-secondary">
            <span class="dashicons dashicons-download"></span>
            <?php esc_html_e('Export to CSV', 'wp-product-builder'); ?>
        </button>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Chart data
    var dailyStats = <?php echo wp_json_encode($daily_stats); ?>;

    if (dailyStats.length > 0 && typeof Chart !== 'undefined') {
        var ctx = document.getElementById('wpb-stats-chart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dailyStats.map(function(s) { return s.date_recorded; }),
                datasets: [
                    {
                        label: '<?php esc_html_e('Views', 'wp-product-builder'); ?>',
                        data: dailyStats.map(function(s) { return s.views; }),
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: '<?php esc_html_e('Clicks', 'wp-product-builder'); ?>',
                        data: dailyStats.map(function(s) { return s.clicks; }),
                        borderColor: '#00a32a',
                        backgroundColor: 'rgba(0, 163, 42, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Export CSV
    $('#wpb-export-csv').on('click', function() {
        window.location.href = '<?php echo esc_url(rest_url('wp-product-builder/v1/statistics/export')); ?>?format=csv&period=<?php echo esc_attr($period); ?>&_wpnonce=<?php echo wp_create_nonce('wp_rest'); ?>';
    });
});
</script>

<style>
.wpb-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}
.wpb-stat-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}
.wpb-stat-card-wide {
    grid-column: span 2;
}
.wpb-stat-icon {
    font-size: 32px;
    width: 50px;
    height: 50px;
    background: #f0f6fc;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2271b1;
}
.wpb-stat-value {
    font-size: 24px;
    font-weight: 600;
    color: #1d2327;
}
.wpb-stat-label {
    color: #646970;
    font-size: 13px;
}
.wpb-period-filter {
    margin: 20px 0;
}
.wpb-period-filter select {
    padding: 5px 10px;
}
.wpb-stats-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin: 20px 0;
}
.wpb-stats-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
}
.wpb-stats-section h2 {
    margin-top: 0;
    border-bottom: 1px solid #c3c4c7;
    padding-bottom: 10px;
}
.wpb-chart-section canvas {
    max-height: 300px;
}
.wpb-no-data {
    color: #646970;
    text-align: center;
    padding: 40px;
}
.wpb-export-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}
.wpb-export-section h2 {
    margin-top: 0;
}
@media (max-width: 782px) {
    .wpb-stats-row {
        grid-template-columns: 1fr;
    }
    .wpb-stat-card-wide {
        grid-column: span 1;
    }
}
</style>
