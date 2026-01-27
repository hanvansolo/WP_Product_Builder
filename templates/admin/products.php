<?php
/**
 * Products Cache Page
 *
 * @package WPProductBuilder
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$cache_table = $wpdb->prefix . 'wpb_product_cache';

// Get cache statistics
$stats = $wpdb->get_row("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN expires_at > NOW() THEN 1 ELSE 0 END) as valid,
        SUM(CASE WHEN expires_at <= NOW() THEN 1 ELSE 0 END) as expired
    FROM {$cache_table}
", ARRAY_A);

// Get recent products
$products = $wpdb->get_results("
    SELECT * FROM {$cache_table}
    ORDER BY last_fetched DESC
    LIMIT 50
", ARRAY_A);
?>
<div class="wrap wpb-admin-page">
    <h1><?php esc_html_e('Product Cache', 'wp-product-builder'); ?></h1>

    <div class="wpb-products-page">
        <!-- Cache Statistics -->
        <div class="wpb-stats-row">
            <div class="wpb-stat-card">
                <span class="wpb-stat-number"><?php echo esc_html($stats['total'] ?? 0); ?></span>
                <span class="wpb-stat-label"><?php esc_html_e('Total Cached', 'wp-product-builder'); ?></span>
            </div>
            <div class="wpb-stat-card">
                <span class="wpb-stat-number"><?php echo esc_html($stats['valid'] ?? 0); ?></span>
                <span class="wpb-stat-label"><?php esc_html_e('Valid Cache', 'wp-product-builder'); ?></span>
            </div>
            <div class="wpb-stat-card">
                <span class="wpb-stat-number"><?php echo esc_html($stats['expired'] ?? 0); ?></span>
                <span class="wpb-stat-label"><?php esc_html_e('Expired', 'wp-product-builder'); ?></span>
            </div>
        </div>

        <!-- Actions -->
        <div class="wpb-card">
            <h2><?php esc_html_e('Cache Actions', 'wp-product-builder'); ?></h2>
            <p>
                <button type="button" class="button button-secondary" id="wpb-clear-expired-cache">
                    <?php esc_html_e('Clear Expired Cache', 'wp-product-builder'); ?>
                </button>
                <button type="button" class="button button-secondary" id="wpb-clear-all-cache">
                    <?php esc_html_e('Clear All Cache', 'wp-product-builder'); ?>
                </button>
            </p>
        </div>

        <!-- Cached Products Table -->
        <div class="wpb-card">
            <h2><?php esc_html_e('Cached Products', 'wp-product-builder'); ?></h2>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 80px;"><?php esc_html_e('Image', 'wp-product-builder'); ?></th>
                        <th><?php esc_html_e('Product', 'wp-product-builder'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('ASIN', 'wp-product-builder'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Price', 'wp-product-builder'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Marketplace', 'wp-product-builder'); ?></th>
                        <th style="width: 150px;"><?php esc_html_e('Expires', 'wp-product-builder'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)) : ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e('No cached products found.', 'wp-product-builder'); ?></td>
                        </tr>
                    <?php else :
                        foreach ($products as $product) :
                            $data = json_decode($product['product_data'], true);
                            $expired = strtotime($product['expires_at']) < time();
                    ?>
                        <tr class="<?php echo $expired ? 'wpb-expired' : ''; ?>">
                            <td>
                                <?php if (!empty($product['image_url'])) : ?>
                                    <img src="<?php echo esc_url($product['image_url']); ?>" alt="" style="width: 60px; height: 60px; object-fit: contain;">
                                <?php else : ?>
                                    <span class="dashicons dashicons-format-image" style="font-size: 40px; color: #ccc;"></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html($product['title'] ?? 'Unknown'); ?></strong>
                                <?php if ($expired) : ?>
                                    <span class="wpb-badge wpb-badge-expired"><?php esc_html_e('Expired', 'wp-product-builder'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code><?php echo esc_html($product['asin']); ?></code>
                            </td>
                            <td>
                                <?php echo esc_html($product['price'] ?? '-'); ?>
                            </td>
                            <td>
                                <?php echo esc_html($product['marketplace']); ?>
                            </td>
                            <td>
                                <?php
                                $expires = strtotime($product['expires_at']);
                                if ($expired) {
                                    echo '<span class="wpb-text-danger">' . esc_html(human_time_diff($expires)) . ' ' . esc_html__('ago', 'wp-product-builder') . '</span>';
                                } else {
                                    echo esc_html__('in', 'wp-product-builder') . ' ' . esc_html(human_time_diff($expires));
                                }
                                ?>
                            </td>
                        </tr>
                    <?php
                        endforeach;
                    endif;
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#wpb-clear-expired-cache').on('click', function() {
        if (confirm('<?php echo esc_js(__('Clear all expired cache entries?', 'wp-product-builder')); ?>')) {
            $.post(wpbAdmin.ajaxUrl, {
                action: 'wpb_clear_expired_cache',
                nonce: wpbAdmin.ajaxNonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
    });

    $('#wpb-clear-all-cache').on('click', function() {
        if (confirm('<?php echo esc_js(__('Clear ALL cached products? This cannot be undone.', 'wp-product-builder')); ?>')) {
            $.post(wpbAdmin.ajaxUrl, {
                action: 'wpb_clear_all_cache',
                nonce: wpbAdmin.ajaxNonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
    });
});
</script>
