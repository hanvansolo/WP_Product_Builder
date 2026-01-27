<?php
/**
 * Auto Import Admin Page
 *
 * @package WPProductBuilder
 */

defined('ABSPATH') || exit;

use WPProductBuilder\WooCommerce\AutoImporter;

$autoImporter = new AutoImporter();
$jobs = $autoImporter->getJobs();
$queue_status = $autoImporter->getQueueStatus();

// Get WooCommerce categories if available
$categories = [];
if (class_exists('WooCommerce')) {
    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ]);
}
?>

<div class="wrap wpb-admin-page wpb-auto-import-page">
    <h1><?php esc_html_e('Auto Import Jobs', 'wp-product-builder'); ?></h1>

    <div class="wpb-queue-status">
        <h2><?php esc_html_e('Import Queue Status', 'wp-product-builder'); ?></h2>
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
            <button type="button" id="wpb-process-queue" class="button"><?php esc_html_e('Process Queue Now', 'wp-product-builder'); ?></button>
            <span class="spinner"></span>
        </p>
    </div>

    <div class="wpb-jobs-section">
        <h2>
            <?php esc_html_e('Import Jobs', 'wp-product-builder'); ?>
            <button type="button" id="wpb-new-job-btn" class="page-title-action"><?php esc_html_e('Add New Job', 'wp-product-builder'); ?></button>
        </h2>

        <?php if (empty($jobs)) : ?>
            <div class="wpb-no-jobs">
                <p><?php esc_html_e('No import jobs created yet. Create a job to automatically import products based on keywords, categories, or ASIN lists.', 'wp-product-builder'); ?></p>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'wp-product-builder'); ?></th>
                        <th><?php esc_html_e('Type', 'wp-product-builder'); ?></th>
                        <th><?php esc_html_e('Schedule', 'wp-product-builder'); ?></th>
                        <th><?php esc_html_e('Status', 'wp-product-builder'); ?></th>
                        <th><?php esc_html_e('Last Run', 'wp-product-builder'); ?></th>
                        <th><?php esc_html_e('Products', 'wp-product-builder'); ?></th>
                        <th><?php esc_html_e('Actions', 'wp-product-builder'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($job['name']); ?></strong></td>
                            <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $job['type']))); ?></td>
                            <td><?php echo esc_html(ucfirst($job['schedule'])); ?></td>
                            <td>
                                <span class="wpb-status-badge wpb-status-<?php echo esc_attr($job['status']); ?>">
                                    <?php echo esc_html(ucfirst($job['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo $job['last_run'] ? esc_html(human_time_diff(strtotime($job['last_run']))) . ' ' . esc_html__('ago', 'wp-product-builder') : esc_html__('Never', 'wp-product-builder'); ?></td>
                            <td><?php echo esc_html($job['products_imported'] ?? 0); ?></td>
                            <td>
                                <button type="button" class="button button-small wpb-run-job" data-job-id="<?php echo esc_attr($job['id']); ?>"><?php esc_html_e('Run Now', 'wp-product-builder'); ?></button>
                                <button type="button" class="button button-small wpb-delete-job" data-job-id="<?php echo esc_attr($job['id']); ?>"><?php esc_html_e('Delete', 'wp-product-builder'); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- New Job Modal -->
<div id="wpb-new-job-modal" class="wpb-modal hidden">
    <div class="wpb-modal-content">
        <div class="wpb-modal-header">
            <h2><?php esc_html_e('Create Import Job', 'wp-product-builder'); ?></h2>
            <button type="button" class="wpb-modal-close">&times;</button>
        </div>
        <form id="wpb-new-job-form">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="job-name"><?php esc_html_e('Job Name', 'wp-product-builder'); ?></label></th>
                    <td><input type="text" id="job-name" name="name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="job-type"><?php esc_html_e('Import Type', 'wp-product-builder'); ?></label></th>
                    <td>
                        <select id="job-type" name="type" required>
                            <option value="keyword"><?php esc_html_e('Search by Keywords', 'wp-product-builder'); ?></option>
                            <option value="asin_list"><?php esc_html_e('ASIN List', 'wp-product-builder'); ?></option>
                            <option value="category"><?php esc_html_e('Amazon Category', 'wp-product-builder'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr class="job-field-keywords">
                    <th scope="row"><label for="job-keywords"><?php esc_html_e('Keywords', 'wp-product-builder'); ?></label></th>
                    <td>
                        <textarea id="job-keywords" name="keywords" rows="3" class="large-text" placeholder="<?php esc_attr_e('One keyword per line', 'wp-product-builder'); ?>"></textarea>
                    </td>
                </tr>
                <tr class="job-field-asins hidden">
                    <th scope="row"><label for="job-asins"><?php esc_html_e('ASINs', 'wp-product-builder'); ?></label></th>
                    <td>
                        <textarea id="job-asins" name="asins" rows="5" class="large-text" placeholder="<?php esc_attr_e('One ASIN per line', 'wp-product-builder'); ?>"></textarea>
                    </td>
                </tr>
                <tr class="job-field-category hidden">
                    <th scope="row"><label for="job-amazon-category"><?php esc_html_e('Category Name', 'wp-product-builder'); ?></label></th>
                    <td>
                        <input type="text" id="job-amazon-category" name="category" class="regular-text" placeholder="<?php esc_attr_e('e.g., Electronics, Books', 'wp-product-builder'); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="job-max-products"><?php esc_html_e('Max Products', 'wp-product-builder'); ?></label></th>
                    <td>
                        <input type="number" id="job-max-products" name="max_products" value="10" min="1" max="100">
                        <p class="description"><?php esc_html_e('Maximum products to import per job run.', 'wp-product-builder'); ?></p>
                    </td>
                </tr>
                <?php if (!empty($categories)) : ?>
                <tr>
                    <th scope="row"><label for="job-woo-category"><?php esc_html_e('WooCommerce Category', 'wp-product-builder'); ?></label></th>
                    <td>
                        <select id="job-woo-category" name="woo_category_id">
                            <option value=""><?php esc_html_e('-- Auto Create --', 'wp-product-builder'); ?></option>
                            <?php foreach ($categories as $cat) : ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th scope="row"><label for="job-schedule"><?php esc_html_e('Schedule', 'wp-product-builder'); ?></label></th>
                    <td>
                        <select id="job-schedule" name="schedule">
                            <option value="manual"><?php esc_html_e('Manual Only', 'wp-product-builder'); ?></option>
                            <option value="hourly"><?php esc_html_e('Hourly', 'wp-product-builder'); ?></option>
                            <option value="daily"><?php esc_html_e('Daily', 'wp-product-builder'); ?></option>
                            <option value="weekly"><?php esc_html_e('Weekly', 'wp-product-builder'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            <div class="wpb-modal-footer">
                <button type="button" class="button wpb-modal-close"><?php esc_html_e('Cancel', 'wp-product-builder'); ?></button>
                <button type="submit" class="button button-primary"><?php esc_html_e('Create Job', 'wp-product-builder'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var apiUrl = '<?php echo esc_url(rest_url('wp-product-builder/v1/')); ?>';
    var nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';

    // Toggle form fields based on job type
    $('#job-type').on('change', function() {
        var type = $(this).val();
        $('.job-field-keywords, .job-field-asins, .job-field-category').addClass('hidden');
        if (type === 'keyword') {
            $('.job-field-keywords').removeClass('hidden');
        } else if (type === 'asin_list') {
            $('.job-field-asins').removeClass('hidden');
        } else if (type === 'category') {
            $('.job-field-category').removeClass('hidden');
        }
    });

    // Open modal
    $('#wpb-new-job-btn').on('click', function() {
        $('#wpb-new-job-modal').removeClass('hidden');
    });

    // Close modal
    $('.wpb-modal-close').on('click', function() {
        $('#wpb-new-job-modal').addClass('hidden');
    });

    // Create job
    $('#wpb-new-job-form').on('submit', function(e) {
        e.preventDefault();

        var type = $('#job-type').val();
        var config = {
            name: $('#job-name').val(),
            type: type,
            max_products: parseInt($('#job-max-products').val()),
            woo_category_id: $('#job-woo-category').val() || null,
            schedule: $('#job-schedule').val()
        };

        if (type === 'keyword') {
            config.keywords = $('#job-keywords').val().split('\n').filter(function(k) { return k.trim(); });
        } else if (type === 'asin_list') {
            config.asins = $('#job-asins').val().split('\n').filter(function(a) { return a.trim(); });
        } else if (type === 'category') {
            config.category = $('#job-amazon-category').val();
        }

        $.ajax({
            url: apiUrl + 'import/jobs',
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
            contentType: 'application/json',
            data: JSON.stringify({
                name: config.name,
                type: config.type,
                config: config
            }),
            success: function(response) {
                location.reload();
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message || '<?php esc_html_e('Failed to create job', 'wp-product-builder'); ?>');
            }
        });
    });

    // Run job
    $('.wpb-run-job').on('click', function() {
        var $btn = $(this);
        var jobId = $btn.data('job-id');

        $btn.prop('disabled', true).text('<?php esc_html_e('Running...', 'wp-product-builder'); ?>');

        $.ajax({
            url: apiUrl + 'import/jobs/' + jobId + '/run',
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
            success: function(response) {
                alert(response.message);
                location.reload();
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message || '<?php esc_html_e('Failed to run job', 'wp-product-builder'); ?>');
                $btn.prop('disabled', false).text('<?php esc_html_e('Run Now', 'wp-product-builder'); ?>');
            }
        });
    });

    // Delete job
    $('.wpb-delete-job').on('click', function() {
        if (!confirm('<?php esc_html_e('Are you sure you want to delete this job?', 'wp-product-builder'); ?>')) {
            return;
        }

        var jobId = $(this).data('job-id');

        $.ajax({
            url: apiUrl + 'import/jobs/' + jobId,
            method: 'DELETE',
            headers: { 'X-WP-Nonce': nonce },
            success: function() {
                location.reload();
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message || '<?php esc_html_e('Failed to delete job', 'wp-product-builder'); ?>');
            }
        });
    });

    // Process queue
    $('#wpb-process-queue').on('click', function() {
        var $btn = $(this);
        var $spinner = $btn.next('.spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.ajax({
            url: apiUrl + 'import/queue/process',
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
            data: { batch_size: 10 },
            success: function(response) {
                alert('<?php esc_html_e('Processed', 'wp-product-builder'); ?> ' + response.processed + ' <?php esc_html_e('items', 'wp-product-builder'); ?>. <?php esc_html_e('Success:', 'wp-product-builder'); ?> ' + response.succeeded + ', <?php esc_html_e('Failed:', 'wp-product-builder'); ?> ' + response.failed);
                location.reload();
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message || '<?php esc_html_e('Failed to process queue', 'wp-product-builder'); ?>');
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
.wpb-status-paused { background: #fff3cd; color: #856404; }
.wpb-status-completed { background: #cce5ff; color: #004085; }

/* Modal */
.wpb-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000; display: flex; align-items: center; justify-content: center; }
.wpb-modal.hidden { display: none; }
.wpb-modal-content { background: #fff; border-radius: 4px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
.wpb-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #c3c4c7; }
.wpb-modal-header h2 { margin: 0; }
.wpb-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #646970; }
.wpb-modal-content .form-table { padding: 0 20px; }
.wpb-modal-footer { padding: 15px 20px; border-top: 1px solid #c3c4c7; text-align: right; display: flex; justify-content: flex-end; gap: 10px; }
</style>
