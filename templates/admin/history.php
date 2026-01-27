<?php
/**
 * Admin Content History Template
 *
 * @package WPProductBuilder
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'wpb_content_history';

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Get total count
$total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
$total_pages = ceil($total_items / $per_page);

// Get history items
$items = $wpdb->get_results($wpdb->prepare(
    "SELECT h.*, u.display_name as author_name, p.post_title as post_title_linked
     FROM {$table} h
     LEFT JOIN {$wpdb->users} u ON h.user_id = u.ID
     LEFT JOIN {$wpdb->posts} p ON h.post_id = p.ID
     ORDER BY h.created_at DESC
     LIMIT %d OFFSET %d",
    $per_page,
    $offset
));

$content_type_labels = [
    'product_review' => __('Product Review', 'wp-product-builder'),
    'products_roundup' => __('Products Roundup', 'wp-product-builder'),
    'products_comparison' => __('Products Comparison', 'wp-product-builder'),
    'listicle' => __('Listicle', 'wp-product-builder'),
    'deals' => __('Deals', 'wp-product-builder'),
];
?>
<div class="wrap wpb-admin-wrap">
    <h1><?php esc_html_e('Content History', 'wp-product-builder'); ?></h1>

    <div class="wpb-card">
        <?php if (empty($items)): ?>
        <p><?php esc_html_e('No content has been generated yet.', 'wp-product-builder'); ?></p>
        <?php else: ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="column-title"><?php esc_html_e('Title', 'wp-product-builder'); ?></th>
                    <th scope="col" class="column-type"><?php esc_html_e('Type', 'wp-product-builder'); ?></th>
                    <th scope="col" class="column-author"><?php esc_html_e('Author', 'wp-product-builder'); ?></th>
                    <th scope="col" class="column-status"><?php esc_html_e('Status', 'wp-product-builder'); ?></th>
                    <th scope="col" class="column-tokens"><?php esc_html_e('Tokens', 'wp-product-builder'); ?></th>
                    <th scope="col" class="column-date"><?php esc_html_e('Date', 'wp-product-builder'); ?></th>
                    <th scope="col" class="column-actions"><?php esc_html_e('Actions', 'wp-product-builder'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="column-title">
                        <strong>
                            <?php if ($item->post_id): ?>
                            <a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>">
                                <?php echo esc_html($item->title); ?>
                            </a>
                            <?php else: ?>
                            <?php echo esc_html($item->title); ?>
                            <?php endif; ?>
                        </strong>
                    </td>
                    <td class="column-type">
                        <?php echo esc_html($content_type_labels[$item->content_type] ?? $item->content_type); ?>
                    </td>
                    <td class="column-author">
                        <?php echo esc_html($item->author_name ?? __('Unknown', 'wp-product-builder')); ?>
                    </td>
                    <td class="column-status">
                        <span class="wpb-status-badge wpb-status-<?php echo esc_attr($item->status); ?>">
                            <?php echo esc_html(ucfirst($item->status)); ?>
                        </span>
                    </td>
                    <td class="column-tokens">
                        <?php echo esc_html(number_format($item->tokens_used)); ?>
                    </td>
                    <td class="column-date">
                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at))); ?>
                    </td>
                    <td class="column-actions">
                        <button type="button"
                                class="button button-small wpb-view-content"
                                data-id="<?php echo esc_attr($item->id); ?>">
                            <?php esc_html_e('View', 'wp-product-builder'); ?>
                        </button>
                        <?php if (!$item->post_id): ?>
                        <button type="button"
                                class="button button-small wpb-create-post"
                                data-id="<?php echo esc_attr($item->id); ?>">
                            <?php esc_html_e('Create Post', 'wp-product-builder'); ?>
                        </button>
                        <?php endif; ?>
                        <button type="button"
                                class="button button-small wpb-delete-history"
                                data-id="<?php echo esc_attr($item->id); ?>">
                            <?php esc_html_e('Delete', 'wp-product-builder'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $current_page,
                ]);
                ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- Content Preview Modal -->
<div id="wpb-content-modal" class="wpb-modal" style="display: none;">
    <div class="wpb-modal-content">
        <div class="wpb-modal-header">
            <h2 id="wpb-modal-title"></h2>
            <button type="button" class="wpb-modal-close">&times;</button>
        </div>
        <div class="wpb-modal-body" id="wpb-modal-body"></div>
        <div class="wpb-modal-footer">
            <button type="button" class="button" id="wpb-modal-copy">
                <?php esc_html_e('Copy Content', 'wp-product-builder'); ?>
            </button>
            <button type="button" class="button wpb-modal-close">
                <?php esc_html_e('Close', 'wp-product-builder'); ?>
            </button>
        </div>
    </div>
</div>
