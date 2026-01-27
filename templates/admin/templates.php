<?php
/**
 * Templates Management Page
 *
 * @package WPProductBuilder
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpb-admin-page">
    <h1><?php esc_html_e('Content Templates', 'wp-product-builder'); ?></h1>

    <div class="wpb-templates-page">
        <div class="wpb-card">
            <h2><?php esc_html_e('Manage Templates', 'wp-product-builder'); ?></h2>
            <p><?php esc_html_e('Customize the templates used for generating different types of content.', 'wp-product-builder'); ?></p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Template Name', 'wp-product-builder'); ?></th>
                        <th><?php esc_html_e('Content Type', 'wp-product-builder'); ?></th>
                        <th><?php esc_html_e('Status', 'wp-product-builder'); ?></th>
                        <th><?php esc_html_e('Actions', 'wp-product-builder'); ?></th>
                    </tr>
                </thead>
                <tbody id="wpb-templates-list">
                    <?php
                    global $wpdb;
                    $table = $wpdb->prefix . 'wpb_templates';
                    $templates = $wpdb->get_results("SELECT * FROM {$table} ORDER BY content_type, name", ARRAY_A);

                    if (empty($templates)) :
                    ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e('No templates found. Default templates will be used.', 'wp-product-builder'); ?></td>
                        </tr>
                    <?php else :
                        foreach ($templates as $template) :
                    ?>
                        <tr data-id="<?php echo esc_attr($template['id']); ?>">
                            <td>
                                <strong><?php echo esc_html($template['name']); ?></strong>
                                <?php if ($template['is_default']) : ?>
                                    <span class="wpb-badge wpb-badge-default"><?php esc_html_e('Default', 'wp-product-builder'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $template['content_type']))); ?></td>
                            <td>
                                <?php if ($template['is_active']) : ?>
                                    <span class="wpb-status wpb-status-active"><?php esc_html_e('Active', 'wp-product-builder'); ?></span>
                                <?php else : ?>
                                    <span class="wpb-status wpb-status-inactive"><?php esc_html_e('Inactive', 'wp-product-builder'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small wpb-edit-template">
                                    <?php esc_html_e('Edit', 'wp-product-builder'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php
                        endforeach;
                    endif;
                    ?>
                </tbody>
            </table>
        </div>

        <div class="wpb-card">
            <h2><?php esc_html_e('Template Variables', 'wp-product-builder'); ?></h2>
            <p><?php esc_html_e('Use these variables in your templates to insert dynamic content:', 'wp-product-builder'); ?></p>
            <ul>
                <li><code>{product_title}</code> - <?php esc_html_e('Product title', 'wp-product-builder'); ?></li>
                <li><code>{product_price}</code> - <?php esc_html_e('Product price', 'wp-product-builder'); ?></li>
                <li><code>{product_rating}</code> - <?php esc_html_e('Product rating', 'wp-product-builder'); ?></li>
                <li><code>{product_image}</code> - <?php esc_html_e('Product image URL', 'wp-product-builder'); ?></li>
                <li><code>{affiliate_link}</code> - <?php esc_html_e('Affiliate link', 'wp-product-builder'); ?></li>
                <li><code>{product_features}</code> - <?php esc_html_e('Product features list', 'wp-product-builder'); ?></li>
            </ul>
        </div>
    </div>
</div>
