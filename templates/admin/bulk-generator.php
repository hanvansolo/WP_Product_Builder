<?php
/**
 * Bulk Generator Admin Page
 *
 * @package WPProductBuilder
 */

defined('ABSPATH') || exit;

use WPProductBuilder\Content\BulkGenerator;

$bulkGenerator = new BulkGenerator();
$jobs = $bulkGenerator->getJobs();
$queue_status = $bulkGenerator->getQueueStatus();

// Get content types
$content_types = [
    'product_review' => __('Product Review', 'wp-product-builder'),
    'products_roundup' => __('Products Roundup', 'wp-product-builder'),
    'products_comparison' => __('Products Comparison', 'wp-product-builder'),
    'listicle' => __('Listicle', 'wp-product-builder'),
    'deals' => __('Deals', 'wp-product-builder'),
];

// Get post categories
$categories = get_categories(['hide_empty' => false]);
?>

<div class="wrap wpb-admin-page wpb-bulk-generator-page">
    <h1><?php esc_html_e('Bulk Content Generator', 'wp-product-builder'); ?></h1>

    <div class="wpb-queue-status">
        <h2><?php esc_html_e('Content Queue Status', 'wp-product-builder'); ?></h2>
        <div class="wpb-queue-stats">
            <div class="wpb-queue-stat">
                <span class="wpb-stat-number"><?php echo esc_html($queue_status['pending']); ?></span>
                <span class="wpb-stat-label"><?php esc_html_e('Pending', 'wp-product-builder'); ?></span>
            </div>
            <div class="wpb-queue-stat">
                <span class="wpb-stat-number"><?php echo esc_html($queue_status['processing']); ?></span>
                <span class="wpb-stat-label"><?php esc_html_e('Processing', 'wp-product-builder'); ?></span>
            </div>
            <div class="wpb-queue-stat wpb-stat-success">
                <span class="wpb-stat-number"><?php echo esc_html($queue_status['completed']); ?></span>
                <span class="wpb-stat-label"><?php esc_html_e('Completed', 'wp-product-builder'); ?></span>
            </div>
            <div class="wpb-queue-stat wpb-stat-error">
                <span class="wpb-stat-number"><?php echo esc_html($queue_status['failed']); ?></span>
                <span class="wpb-stat-label"><?php esc_html_e('Failed', 'wp-product-builder'); ?></span>
            </div>
        </div>
        <p>
            <button type="button" id="wpb-process-content-queue" class="button"><?php esc_html_e('Process Queue Now', 'wp-product-builder'); ?></button>
            <span class="spinner"></span>
        </p>
    </div>

    <div class="wpb-bulk-create-section">
        <h2><?php esc_html_e('Create Bulk Content Job', 'wp-product-builder'); ?></h2>
        <p><?php esc_html_e('Generate multiple articles at once with optional scheduling.', 'wp-product-builder'); ?></p>

        <form id="wpb-bulk-content-form" class="wpb-bulk-form">
            <div class="wpb-form-section">
                <h3><?php esc_html_e('Job Settings', 'wp-product-builder'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="bulk-job-name"><?php esc_html_e('Job Name', 'wp-product-builder'); ?></label></th>
                        <td><input type="text" id="bulk-job-name" name="name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bulk-content-type"><?php esc_html_e('Content Type', 'wp-product-builder'); ?></label></th>
                        <td>
                            <select id="bulk-content-type" name="content_type" required>
                                <?php foreach ($content_types as $type => $label) : ?>
                                    <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="wpb-form-section">
                <h3><?php esc_html_e('Articles to Generate', 'wp-product-builder'); ?></h3>
                <p><?php esc_html_e('Add articles with their titles and product ASINs.', 'wp-product-builder'); ?></p>

                <div id="wpb-articles-list">
                    <div class="wpb-article-item" data-index="0">
                        <div class="wpb-article-header">
                            <span class="wpb-article-number">#1</span>
                            <input type="text" name="articles[0][title]" class="regular-text" placeholder="<?php esc_attr_e('Article Title', 'wp-product-builder'); ?>" required>
                            <button type="button" class="button wpb-remove-article">&times;</button>
                        </div>
                        <textarea name="articles[0][asins]" rows="2" placeholder="<?php esc_attr_e('ASINs (one per line or comma-separated)', 'wp-product-builder'); ?>"></textarea>
                    </div>
                </div>

                <button type="button" id="wpb-add-article" class="button">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e('Add Article', 'wp-product-builder'); ?>
                </button>
            </div>

            <div class="wpb-form-section">
                <h3><?php esc_html_e('Content Options', 'wp-product-builder'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="bulk-category"><?php esc_html_e('Post Category', 'wp-product-builder'); ?></label></th>
                        <td>
                            <select id="bulk-category" name="category_id">
                                <option value=""><?php esc_html_e('-- Uncategorized --', 'wp-product-builder'); ?></option>
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bulk-tone"><?php esc_html_e('Writing Tone', 'wp-product-builder'); ?></label></th>
                        <td>
                            <select id="bulk-tone" name="tone">
                                <option value="professional"><?php esc_html_e('Professional', 'wp-product-builder'); ?></option>
                                <option value="casual"><?php esc_html_e('Casual', 'wp-product-builder'); ?></option>
                                <option value="enthusiastic"><?php esc_html_e('Enthusiastic', 'wp-product-builder'); ?></option>
                                <option value="informative"><?php esc_html_e('Informative', 'wp-product-builder'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bulk-length"><?php esc_html_e('Content Length', 'wp-product-builder'); ?></label></th>
                        <td>
                            <select id="bulk-length" name="length">
                                <option value="short"><?php esc_html_e('Short (~800 words)', 'wp-product-builder'); ?></option>
                                <option value="medium" selected><?php esc_html_e('Medium (~1500 words)', 'wp-product-builder'); ?></option>
                                <option value="long"><?php esc_html_e('Long (~2500 words)', 'wp-product-builder'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Options', 'wp-product-builder'); ?></th>
                        <td>
                            <label><input type="checkbox" name="include_faq" value="1"> <?php esc_html_e('Include FAQ section', 'wp-product-builder'); ?></label><br>
                            <label><input type="checkbox" name="include_comparison_table" value="1"> <?php esc_html_e('Include comparison table', 'wp-product-builder'); ?></label><br>
                            <label><input type="checkbox" name="spin_content" value="1"> <?php esc_html_e('Apply content variation (spinning)', 'wp-product-builder'); ?></label>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="wpb-form-section">
                <h3><?php esc_html_e('Publishing Schedule', 'wp-product-builder'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="bulk-schedule-type"><?php esc_html_e('Schedule Type', 'wp-product-builder'); ?></label></th>
                        <td>
                            <select id="bulk-schedule-type" name="schedule_type">
                                <option value="immediate"><?php esc_html_e('Generate All Immediately', 'wp-product-builder'); ?></option>
                                <option value="drip"><?php esc_html_e('Drip Schedule (Spread Over Time)', 'wp-product-builder'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr class="wpb-drip-options hidden">
                        <th scope="row"><label for="bulk-drip-interval"><?php esc_html_e('Publish Interval', 'wp-product-builder'); ?></label></th>
                        <td>
                            <select id="bulk-drip-interval" name="drip_interval">
                                <option value="hourly"><?php esc_html_e('Every Hour', 'wp-product-builder'); ?></option>
                                <option value="daily" selected><?php esc_html_e('Daily', 'wp-product-builder'); ?></option>
                                <option value="weekly"><?php esc_html_e('Weekly', 'wp-product-builder'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Time between each article generation.', 'wp-product-builder'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bulk-post-status"><?php esc_html_e('Post Status', 'wp-product-builder'); ?></label></th>
                        <td>
                            <select id="bulk-post-status" name="post_status">
                                <option value="draft"><?php esc_html_e('Draft', 'wp-product-builder'); ?></option>
                                <option value="publish"><?php esc_html_e('Published', 'wp-product-builder'); ?></option>
                                <option value="pending"><?php esc_html_e('Pending Review', 'wp-product-builder'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary button-hero"><?php esc_html_e('Create Bulk Job', 'wp-product-builder'); ?></button>
            </p>
        </form>
    </div>

    <div class="wpb-jobs-section">
        <h2><?php esc_html_e('Content Jobs', 'wp-product-builder'); ?></h2>

        <?php if (empty($jobs)) : ?>
            <div class="wpb-no-jobs">
                <p><?php esc_html_e('No content jobs created yet.', 'wp-product-builder'); ?></p>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'wp-product-builder'); ?></th>
                        <th><?php esc_html_e('Type', 'wp-product-builder'); ?></th>
                        <th><?php esc_html_e('Progress', 'wp-product-builder'); ?></th>
                        <th><?php esc_html_e('Status', 'wp-product-builder'); ?></th>
                        <th><?php esc_html_e('Created', 'wp-product-builder'); ?></th>
                        <th><?php esc_html_e('Actions', 'wp-product-builder'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job) : ?>
                        <?php
                        $progress = $job['total_items'] > 0
                            ? round(($job['completed_items'] / $job['total_items']) * 100)
                            : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($job['name']); ?></strong></td>
                            <td><?php echo esc_html($content_types[$job['content_type']] ?? $job['content_type']); ?></td>
                            <td>
                                <div class="wpb-mini-progress">
                                    <div class="wpb-mini-progress-bar" style="width: <?php echo esc_attr($progress); ?>%"></div>
                                </div>
                                <span><?php echo esc_html($job['completed_items']); ?>/<?php echo esc_html($job['total_items']); ?></span>
                            </td>
                            <td>
                                <span class="wpb-status-badge wpb-status-<?php echo esc_attr($job['status']); ?>">
                                    <?php echo esc_html(ucfirst($job['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(human_time_diff(strtotime($job['created_at']))) . ' ' . esc_html__('ago', 'wp-product-builder'); ?></td>
                            <td>
                                <button type="button" class="button button-small wpb-delete-content-job" data-job-id="<?php echo esc_attr($job['id']); ?>"><?php esc_html_e('Delete', 'wp-product-builder'); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var apiUrl = '<?php echo esc_url(rest_url('wp-product-builder/v1/')); ?>';
    var nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';
    var articleIndex = 1;

    // Toggle drip options
    $('#bulk-schedule-type').on('change', function() {
        if ($(this).val() === 'drip') {
            $('.wpb-drip-options').removeClass('hidden');
        } else {
            $('.wpb-drip-options').addClass('hidden');
        }
    });

    // Add article
    $('#wpb-add-article').on('click', function() {
        var html = '<div class="wpb-article-item" data-index="' + articleIndex + '">';
        html += '<div class="wpb-article-header">';
        html += '<span class="wpb-article-number">#' + (articleIndex + 1) + '</span>';
        html += '<input type="text" name="articles[' + articleIndex + '][title]" class="regular-text" placeholder="<?php esc_attr_e('Article Title', 'wp-product-builder'); ?>" required>';
        html += '<button type="button" class="button wpb-remove-article">&times;</button>';
        html += '</div>';
        html += '<textarea name="articles[' + articleIndex + '][asins]" rows="2" placeholder="<?php esc_attr_e('ASINs (one per line or comma-separated)', 'wp-product-builder'); ?>"></textarea>';
        html += '</div>';

        $('#wpb-articles-list').append(html);
        articleIndex++;
        updateArticleNumbers();
    });

    // Remove article
    $(document).on('click', '.wpb-remove-article', function() {
        if ($('.wpb-article-item').length > 1) {
            $(this).closest('.wpb-article-item').remove();
            updateArticleNumbers();
        } else {
            alert('<?php esc_html_e('You need at least one article.', 'wp-product-builder'); ?>');
        }
    });

    function updateArticleNumbers() {
        $('.wpb-article-item').each(function(i) {
            $(this).find('.wpb-article-number').text('#' + (i + 1));
        });
    }

    // Submit form
    $('#wpb-bulk-content-form').on('submit', function(e) {
        e.preventDefault();

        var articles = [];
        $('.wpb-article-item').each(function() {
            var title = $(this).find('input[name*="[title]"]').val();
            var asinsText = $(this).find('textarea').val();
            var asins = asinsText.split(/[\n,]+/).map(function(a) { return a.trim().toUpperCase(); }).filter(function(a) { return a; });

            if (title) {
                articles.push({ title: title, asins: asins });
            }
        });

        if (articles.length === 0) {
            alert('<?php esc_html_e('Please add at least one article.', 'wp-product-builder'); ?>');
            return;
        }

        var data = {
            name: $('#bulk-job-name').val(),
            content_type: $('#bulk-content-type').val(),
            articles: articles,
            content_options: {
                tone: $('#bulk-tone').val(),
                length: $('#bulk-length').val(),
                include_faq: $('[name="include_faq"]').is(':checked'),
                include_comparison_table: $('[name="include_comparison_table"]').is(':checked'),
                spin_content: $('[name="spin_content"]').is(':checked'),
                category_id: $('#bulk-category').val() || null
            },
            schedule_type: $('#bulk-schedule-type').val(),
            schedule_interval: $('#bulk-drip-interval').val(),
            post_status: $('#bulk-post-status').val()
        };

        $.ajax({
            url: apiUrl + 'content/bulk',
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                alert('<?php esc_html_e('Bulk job created with', 'wp-product-builder'); ?> ' + response.items_queued + ' <?php esc_html_e('articles queued!', 'wp-product-builder'); ?>');
                location.reload();
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message || '<?php esc_html_e('Failed to create job', 'wp-product-builder'); ?>');
            }
        });
    });

    // Delete job
    $('.wpb-delete-content-job').on('click', function() {
        if (!confirm('<?php esc_html_e('Delete this job and all queued articles?', 'wp-product-builder'); ?>')) return;

        var jobId = $(this).data('job-id');

        $.ajax({
            url: apiUrl + 'content/bulk/' + jobId,
            method: 'DELETE',
            headers: { 'X-WP-Nonce': nonce },
            success: function() {
                location.reload();
            }
        });
    });

    // Process queue
    $('#wpb-process-content-queue').on('click', function() {
        var $btn = $(this);
        var $spinner = $btn.next('.spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.ajax({
            url: apiUrl + 'content/queue/process',
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
            success: function(response) {
                alert('<?php esc_html_e('Processed', 'wp-product-builder'); ?> ' + response.processed + ' <?php esc_html_e('articles', 'wp-product-builder'); ?>');
                location.reload();
            },
            complete: function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});
</script>

<style>
.wpb-bulk-form { background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px; margin: 20px 0; }
.wpb-form-section { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e2e4e7; }
.wpb-form-section:last-child { border-bottom: none; }
.wpb-form-section h3 { margin-top: 0; }
#wpb-articles-list { margin: 15px 0; }
.wpb-article-item { background: #f6f7f7; padding: 15px; border-radius: 4px; margin-bottom: 10px; }
.wpb-article-header { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.wpb-article-number { background: #2271b1; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; }
.wpb-article-item textarea { width: 100%; }
.wpb-remove-article { color: #d63638; }
.wpb-queue-status { background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px; margin: 20px 0; }
.wpb-queue-stats { display: flex; gap: 30px; margin: 20px 0; }
.wpb-queue-stat { text-align: center; }
.wpb-stat-number { display: block; font-size: 32px; font-weight: 600; }
.wpb-stat-label { color: #646970; }
.wpb-stat-success .wpb-stat-number { color: #00a32a; }
.wpb-stat-error .wpb-stat-number { color: #d63638; }
.wpb-jobs-section { background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px; margin: 20px 0; }
.wpb-no-jobs { text-align: center; padding: 40px; color: #646970; }
.wpb-status-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; }
.wpb-status-active { background: #d4edda; color: #155724; }
.wpb-status-completed { background: #cce5ff; color: #004085; }
.wpb-status-paused { background: #fff3cd; color: #856404; }
.wpb-mini-progress { width: 60px; height: 8px; background: #e2e4e7; border-radius: 4px; display: inline-block; vertical-align: middle; margin-right: 5px; }
.wpb-mini-progress-bar { height: 100%; background: #2271b1; border-radius: 4px; }
</style>
