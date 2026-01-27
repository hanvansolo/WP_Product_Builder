<?php
/**
 * WooCommerce Import Admin Page
 *
 * @package WPProductBuilder
 */

defined('ABSPATH') || exit;

use WPProductBuilder\WooCommerce\ProductImporter;
use WPProductBuilder\Services\ProductDataService;

$importer = new ProductImporter();
$productService = new ProductDataService();
$status = $productService->getStatus();

// Get WooCommerce categories
$categories = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => false,
]);
?>

<div class="wrap wpb-admin-page wpb-woo-import-page">
    <h1><?php esc_html_e('WooCommerce Product Import', 'wp-product-builder'); ?></h1>

    <div class="wpb-data-source-status">
        <h2><?php esc_html_e('Data Source Status', 'wp-product-builder'); ?></h2>
        <div class="wpb-status-cards">
            <div class="wpb-status-card <?php echo $status['api_configured'] ? 'wpb-status-active' : 'wpb-status-inactive'; ?>">
                <span class="dashicons <?php echo $status['api_configured'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                <strong><?php esc_html_e('Amazon PA-API', 'wp-product-builder'); ?></strong>
                <span><?php echo $status['api_configured'] ? esc_html__('Configured', 'wp-product-builder') : esc_html__('Not Configured', 'wp-product-builder'); ?></span>
            </div>
            <div class="wpb-status-card wpb-status-active">
                <span class="dashicons dashicons-yes-alt"></span>
                <strong><?php esc_html_e('Scraper', 'wp-product-builder'); ?></strong>
                <span><?php esc_html_e('Available', 'wp-product-builder'); ?></span>
            </div>
            <div class="wpb-status-card">
                <strong><?php esc_html_e('Current Mode', 'wp-product-builder'); ?></strong>
                <span><?php echo esc_html(ucfirst($status['mode'])); ?></span>
            </div>
        </div>
        <?php if (!$status['api_configured']) : ?>
            <div class="notice notice-info inline">
                <p>
                    <?php esc_html_e('Amazon PA-API is not configured. Products will be imported using the scraper. Configure API in Settings for better reliability.', 'wp-product-builder'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wp-product-builder-settings')); ?>"><?php esc_html_e('Go to Settings', 'wp-product-builder'); ?></a>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <div class="wpb-import-tabs">
        <button type="button" class="wpb-tab-btn active" data-tab="single"><?php esc_html_e('Single Import', 'wp-product-builder'); ?></button>
        <button type="button" class="wpb-tab-btn" data-tab="bulk"><?php esc_html_e('Bulk Import', 'wp-product-builder'); ?></button>
        <button type="button" class="wpb-tab-btn" data-tab="search"><?php esc_html_e('Search & Import', 'wp-product-builder'); ?></button>
    </div>

    <div class="wpb-tab-content active" id="wpb-tab-single">
        <div class="wpb-import-form">
            <h2><?php esc_html_e('Import Single Product', 'wp-product-builder'); ?></h2>
            <p><?php esc_html_e('Enter an Amazon ASIN to import the product to WooCommerce.', 'wp-product-builder'); ?></p>

            <form id="wpb-single-import-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wpb-asin"><?php esc_html_e('ASIN', 'wp-product-builder'); ?></label></th>
                        <td>
                            <input type="text" id="wpb-asin" name="asin" class="regular-text" placeholder="e.g., B08N5WRWNW" pattern="[A-Z0-9]{10}" required>
                            <p class="description"><?php esc_html_e('The 10-character Amazon product identifier.', 'wp-product-builder'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wpb-category"><?php esc_html_e('Category', 'wp-product-builder'); ?></label></th>
                        <td>
                            <select id="wpb-category" name="category_id">
                                <option value=""><?php esc_html_e('-- Select Category --', 'wp-product-builder'); ?></option>
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Options', 'wp-product-builder'); ?></th>
                        <td>
                            <label><input type="checkbox" name="download_images" value="1" checked> <?php esc_html_e('Download product images', 'wp-product-builder'); ?></label><br>
                            <label><input type="checkbox" name="publish" value="1" checked> <?php esc_html_e('Publish immediately', 'wp-product-builder'); ?></label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Import Product', 'wp-product-builder'); ?></button>
                    <span class="spinner"></span>
                </p>
            </form>

            <div id="wpb-import-result" class="hidden"></div>
        </div>
    </div>

    <div class="wpb-tab-content" id="wpb-tab-bulk">
        <div class="wpb-import-form">
            <h2><?php esc_html_e('Bulk Import Products', 'wp-product-builder'); ?></h2>
            <p><?php esc_html_e('Enter multiple ASINs (one per line) to import several products at once.', 'wp-product-builder'); ?></p>

            <form id="wpb-bulk-import-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wpb-asins"><?php esc_html_e('ASINs', 'wp-product-builder'); ?></label></th>
                        <td>
                            <textarea id="wpb-asins" name="asins" rows="10" class="large-text" placeholder="B08N5WRWNW&#10;B09V3KXJPB&#10;B07XJ8C8F5"></textarea>
                            <p class="description"><?php esc_html_e('Enter one ASIN per line. Maximum 50 products per batch.', 'wp-product-builder'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wpb-bulk-category"><?php esc_html_e('Category', 'wp-product-builder'); ?></label></th>
                        <td>
                            <select id="wpb-bulk-category" name="category_id">
                                <option value=""><?php esc_html_e('-- Select Category --', 'wp-product-builder'); ?></option>
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Start Bulk Import', 'wp-product-builder'); ?></button>
                    <span class="spinner"></span>
                </p>
            </form>

            <div id="wpb-bulk-import-progress" class="hidden">
                <h3><?php esc_html_e('Import Progress', 'wp-product-builder'); ?></h3>
                <div class="wpb-progress-bar">
                    <div class="wpb-progress-fill"></div>
                </div>
                <p class="wpb-progress-text"></p>
            </div>

            <div id="wpb-bulk-import-results" class="hidden"></div>
        </div>
    </div>

    <div class="wpb-tab-content" id="wpb-tab-search">
        <div class="wpb-import-form">
            <h2><?php esc_html_e('Search & Import', 'wp-product-builder'); ?></h2>
            <p><?php esc_html_e('Search for products on Amazon and select which ones to import.', 'wp-product-builder'); ?></p>

            <form id="wpb-search-form">
                <div class="wpb-search-box">
                    <input type="text" id="wpb-search-query" name="query" class="regular-text" placeholder="<?php esc_attr_e('Search for products...', 'wp-product-builder'); ?>">
                    <button type="submit" class="button"><?php esc_html_e('Search', 'wp-product-builder'); ?></button>
                    <span class="spinner"></span>
                </div>
            </form>

            <div id="wpb-search-results" class="hidden">
                <h3><?php esc_html_e('Search Results', 'wp-product-builder'); ?></h3>
                <div class="wpb-search-results-grid"></div>
                <p class="submit">
                    <button type="button" id="wpb-import-selected" class="button button-primary" disabled><?php esc_html_e('Import Selected', 'wp-product-builder'); ?></button>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var apiUrl = '<?php echo esc_url(rest_url('wp-product-builder/v1/')); ?>';
    var nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';

    // Tab switching
    $('.wpb-tab-btn').on('click', function() {
        var tab = $(this).data('tab');
        $('.wpb-tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.wpb-tab-content').removeClass('active');
        $('#wpb-tab-' + tab).addClass('active');
    });

    // Single import
    $('#wpb-single-import-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $spinner = $form.find('.spinner');
        var $result = $('#wpb-import-result');

        $spinner.addClass('is-active');
        $result.removeClass('notice-success notice-error').addClass('hidden');

        $.ajax({
            url: apiUrl + 'import/product',
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
            data: {
                asin: $('#wpb-asin').val().toUpperCase(),
                category_id: $('#wpb-category').val(),
                options: {
                    download_images: $form.find('[name="download_images"]').is(':checked'),
                    status: $form.find('[name="publish"]').is(':checked') ? 'publish' : 'draft'
                }
            },
            success: function(response) {
                $result.removeClass('hidden').addClass('notice notice-success');
                $result.html('<p><?php esc_html_e('Product imported successfully!', 'wp-product-builder'); ?> <a href="' + response.edit_url + '"><?php esc_html_e('Edit Product', 'wp-product-builder'); ?></a></p>');
                $('#wpb-asin').val('');
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || '<?php esc_html_e('Import failed', 'wp-product-builder'); ?>';
                $result.removeClass('hidden').addClass('notice notice-error');
                $result.html('<p>' + msg + '</p>');
            },
            complete: function() {
                $spinner.removeClass('is-active');
            }
        });
    });

    // Bulk import
    $('#wpb-bulk-import-form').on('submit', function(e) {
        e.preventDefault();

        var asins = $('#wpb-asins').val().split('\n').map(function(a) { return a.trim().toUpperCase(); }).filter(function(a) { return a.length === 10; });

        if (asins.length === 0) {
            alert('<?php esc_html_e('Please enter valid ASINs', 'wp-product-builder'); ?>');
            return;
        }

        var $progress = $('#wpb-bulk-import-progress');
        var $results = $('#wpb-bulk-import-results');

        $progress.removeClass('hidden');
        $results.addClass('hidden');

        $.ajax({
            url: apiUrl + 'import/products',
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
            data: {
                asins: asins,
                category_id: $('#wpb-bulk-category').val()
            },
            success: function(response) {
                $('.wpb-progress-fill').css('width', '100%');
                $('.wpb-progress-text').text('<?php esc_html_e('Import complete!', 'wp-product-builder'); ?>');

                var html = '<h3><?php esc_html_e('Import Results', 'wp-product-builder'); ?></h3>';
                html += '<p><?php esc_html_e('Imported:', 'wp-product-builder'); ?> ' + response.imported_count + ' | <?php esc_html_e('Failed:', 'wp-product-builder'); ?> ' + response.failed_count + '</p>';

                if (response.results.failed.length > 0) {
                    html += '<ul>';
                    response.results.failed.forEach(function(f) {
                        html += '<li><strong>' + f.asin + '</strong>: ' + f.error + '</li>';
                    });
                    html += '</ul>';
                }

                $results.removeClass('hidden').html(html);
            },
            error: function(xhr) {
                $('.wpb-progress-text').text('<?php esc_html_e('Import failed', 'wp-product-builder'); ?>');
            }
        });
    });

    // Search
    $('#wpb-search-form').on('submit', function(e) {
        e.preventDefault();

        var query = $('#wpb-search-query').val();
        var $spinner = $(this).find('.spinner');
        var $results = $('#wpb-search-results');

        $spinner.addClass('is-active');

        $.ajax({
            url: apiUrl + 'products/search',
            method: 'GET',
            headers: { 'X-WP-Nonce': nonce },
            data: { query: query },
            success: function(response) {
                var html = '';
                response.products.forEach(function(product) {
                    html += '<div class="wpb-search-result-item">';
                    html += '<input type="checkbox" class="wpb-product-checkbox" value="' + product.asin + '">';
                    html += '<img src="' + (product.image_url || '') + '" alt="">';
                    html += '<div class="wpb-product-info">';
                    html += '<strong>' + product.title + '</strong>';
                    html += '<span class="wpb-product-price">' + (product.price || 'N/A') + '</span>';
                    html += '<span class="wpb-product-asin">ASIN: ' + product.asin + '</span>';
                    html += '</div></div>';
                });

                $('.wpb-search-results-grid').html(html);
                $results.removeClass('hidden');
            },
            complete: function() {
                $spinner.removeClass('is-active');
            }
        });
    });

    // Enable import button when products selected
    $(document).on('change', '.wpb-product-checkbox', function() {
        var selected = $('.wpb-product-checkbox:checked').length;
        $('#wpb-import-selected').prop('disabled', selected === 0).text(selected > 0 ? '<?php esc_html_e('Import Selected', 'wp-product-builder'); ?> (' + selected + ')' : '<?php esc_html_e('Import Selected', 'wp-product-builder'); ?>');
    });

    // Import selected
    $('#wpb-import-selected').on('click', function() {
        var asins = [];
        $('.wpb-product-checkbox:checked').each(function() {
            asins.push($(this).val());
        });

        if (asins.length === 0) return;

        $(this).prop('disabled', true).text('<?php esc_html_e('Importing...', 'wp-product-builder'); ?>');

        $.ajax({
            url: apiUrl + 'import/products',
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
            data: { asins: asins },
            success: function(response) {
                alert('<?php esc_html_e('Imported', 'wp-product-builder'); ?> ' + response.imported_count + ' <?php esc_html_e('products', 'wp-product-builder'); ?>');
                $('.wpb-product-checkbox:checked').closest('.wpb-search-result-item').remove();
            },
            complete: function() {
                $('#wpb-import-selected').prop('disabled', false).text('<?php esc_html_e('Import Selected', 'wp-product-builder'); ?>');
            }
        });
    });
});
</script>

<style>
.wpb-data-source-status { margin: 20px 0; }
.wpb-status-cards { display: flex; gap: 15px; margin: 15px 0; }
.wpb-status-card { background: #fff; border: 1px solid #c3c4c7; padding: 15px 20px; border-radius: 4px; display: flex; align-items: center; gap: 10px; }
.wpb-status-active { border-left: 4px solid #00a32a; }
.wpb-status-inactive { border-left: 4px solid #dba617; }
.wpb-status-card .dashicons-yes-alt { color: #00a32a; }
.wpb-status-card .dashicons-warning { color: #dba617; }
.wpb-import-tabs { display: flex; gap: 5px; margin: 20px 0; border-bottom: 1px solid #c3c4c7; }
.wpb-tab-btn { background: none; border: none; padding: 10px 20px; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -1px; }
.wpb-tab-btn.active { border-bottom-color: #2271b1; color: #2271b1; font-weight: 600; }
.wpb-tab-content { display: none; }
.wpb-tab-content.active { display: block; }
.wpb-import-form { background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px; margin: 20px 0; }
.wpb-search-box { display: flex; gap: 10px; align-items: center; }
.wpb-search-results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin: 20px 0; }
.wpb-search-result-item { display: flex; gap: 10px; align-items: center; background: #f6f7f7; padding: 10px; border-radius: 4px; }
.wpb-search-result-item img { width: 60px; height: 60px; object-fit: contain; background: #fff; }
.wpb-product-info { flex: 1; }
.wpb-product-info strong { display: block; font-size: 13px; line-height: 1.3; }
.wpb-product-price { color: #00a32a; font-weight: 600; }
.wpb-product-asin { color: #646970; font-size: 11px; }
.wpb-progress-bar { height: 20px; background: #f0f0f1; border-radius: 10px; overflow: hidden; }
.wpb-progress-fill { height: 100%; background: #2271b1; width: 0; transition: width 0.3s; }
</style>
