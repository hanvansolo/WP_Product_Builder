<?php
/**
 * Admin Dashboard Template
 *
 * @package WPProductBuilder
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('wpb_settings', []);
$credentials = get_option('wpb_credentials_encrypted', []);
$has_claude_key = !empty($credentials['claude_api_key']);
$has_amazon_keys = !empty($credentials['amazon_access_key']) && !empty($credentials['amazon_secret_key']);
?>
<div class="wrap wpb-admin-wrap">
    <h1><?php esc_html_e('WP Product Builder', 'wp-product-builder'); ?></h1>

    <?php if (!$has_claude_key || !$has_amazon_keys): ?>
    <div class="notice notice-warning">
        <p>
            <?php esc_html_e('Please configure your API keys to get started.', 'wp-product-builder'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-product-builder-settings')); ?>">
                <?php esc_html_e('Go to Settings', 'wp-product-builder'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <div class="wpb-dashboard-grid">
        <!-- Quick Actions -->
        <div class="wpb-card">
            <h2><?php esc_html_e('Quick Actions', 'wp-product-builder'); ?></h2>
            <div class="wpb-quick-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-product-builder-generate')); ?>" class="button button-primary button-hero">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e('Generate Content', 'wp-product-builder'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-product-builder-history')); ?>" class="button button-secondary button-hero">
                    <span class="dashicons dashicons-backup"></span>
                    <?php esc_html_e('View History', 'wp-product-builder'); ?>
                </a>
            </div>
        </div>

        <!-- Content Types -->
        <div class="wpb-card">
            <h2><?php esc_html_e('Content Types', 'wp-product-builder'); ?></h2>
            <ul class="wpb-content-types-list">
                <li>
                    <span class="dashicons dashicons-star-filled"></span>
                    <strong><?php esc_html_e('Product Review', 'wp-product-builder'); ?></strong>
                    <span><?php esc_html_e('Detailed single product review', 'wp-product-builder'); ?></span>
                </li>
                <li>
                    <span class="dashicons dashicons-list-view"></span>
                    <strong><?php esc_html_e('Products Roundup', 'wp-product-builder'); ?></strong>
                    <span><?php esc_html_e('Best X products in category', 'wp-product-builder'); ?></span>
                </li>
                <li>
                    <span class="dashicons dashicons-columns"></span>
                    <strong><?php esc_html_e('Products Comparison', 'wp-product-builder'); ?></strong>
                    <span><?php esc_html_e('Compare 2-4 products', 'wp-product-builder'); ?></span>
                </li>
                <li>
                    <span class="dashicons dashicons-editor-ol"></span>
                    <strong><?php esc_html_e('Listicle', 'wp-product-builder'); ?></strong>
                    <span><?php esc_html_e('Numbered list content', 'wp-product-builder'); ?></span>
                </li>
                <li>
                    <span class="dashicons dashicons-tag"></span>
                    <strong><?php esc_html_e('Deals', 'wp-product-builder'); ?></strong>
                    <span><?php esc_html_e('Promotional content', 'wp-product-builder'); ?></span>
                </li>
            </ul>
        </div>

        <!-- Stats -->
        <div class="wpb-card">
            <h2><?php esc_html_e('Statistics', 'wp-product-builder'); ?></h2>
            <div class="wpb-stats" id="wpb-dashboard-stats">
                <div class="wpb-stat">
                    <span class="wpb-stat-value" id="stat-content-count">-</span>
                    <span class="wpb-stat-label"><?php esc_html_e('Content Generated', 'wp-product-builder'); ?></span>
                </div>
                <div class="wpb-stat">
                    <span class="wpb-stat-value" id="stat-products-cached">-</span>
                    <span class="wpb-stat-label"><?php esc_html_e('Products Cached', 'wp-product-builder'); ?></span>
                </div>
                <div class="wpb-stat">
                    <span class="wpb-stat-value" id="stat-tokens-used">-</span>
                    <span class="wpb-stat-label"><?php esc_html_e('Tokens Used', 'wp-product-builder'); ?></span>
                </div>
            </div>
        </div>

        <!-- API Status -->
        <div class="wpb-card">
            <h2><?php esc_html_e('API Status', 'wp-product-builder'); ?></h2>
            <div class="wpb-api-status">
                <div class="wpb-api-item">
                    <span class="wpb-api-name"><?php esc_html_e('Claude API', 'wp-product-builder'); ?></span>
                    <span class="wpb-api-status-badge <?php echo $has_claude_key ? 'configured' : 'not-configured'; ?>">
                        <?php echo $has_claude_key ? esc_html__('Configured', 'wp-product-builder') : esc_html__('Not Configured', 'wp-product-builder'); ?>
                    </span>
                </div>
                <div class="wpb-api-item">
                    <span class="wpb-api-name"><?php esc_html_e('Amazon PA-API', 'wp-product-builder'); ?></span>
                    <span class="wpb-api-status-badge <?php echo $has_amazon_keys ? 'configured' : 'not-configured'; ?>">
                        <?php echo $has_amazon_keys ? esc_html__('Configured', 'wp-product-builder') : esc_html__('Not Configured', 'wp-product-builder'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
