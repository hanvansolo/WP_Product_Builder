<?php
/**
 * Admin Content Generator Template
 *
 * @package WPProductBuilder
 */

if (!defined('ABSPATH')) {
    exit;
}

$credentials = get_option('wpb_credentials_encrypted', []);
// Only require Claude API key - Amazon PA-API is optional (scraper fallback available)
$has_api_keys = !empty($credentials['claude_api_key']);
?>
<div class="wrap wpb-admin-wrap">
    <h1><?php esc_html_e('Generate Content', 'wp-product-builder'); ?></h1>

    <?php if (!$has_api_keys): ?>
    <div class="notice notice-error">
        <p>
            <?php esc_html_e('Please configure your API keys before generating content.', 'wp-product-builder'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-product-builder-settings')); ?>">
                <?php esc_html_e('Go to Settings', 'wp-product-builder'); ?>
            </a>
        </p>
    </div>
    <?php else: ?>

    <div id="wpb-generator-notices"></div>

    <div class="wpb-generator-wizard" id="wpb-generator">
        <!-- Progress Steps -->
        <div class="wpb-wizard-steps">
            <div class="wpb-step active" data-step="1">
                <span class="wpb-step-number">1</span>
                <span class="wpb-step-label"><?php esc_html_e('Content Type', 'wp-product-builder'); ?></span>
            </div>
            <div class="wpb-step" data-step="2">
                <span class="wpb-step-number">2</span>
                <span class="wpb-step-label"><?php esc_html_e('Products', 'wp-product-builder'); ?></span>
            </div>
            <div class="wpb-step" data-step="3">
                <span class="wpb-step-number">3</span>
                <span class="wpb-step-label"><?php esc_html_e('Options', 'wp-product-builder'); ?></span>
            </div>
            <div class="wpb-step" data-step="4">
                <span class="wpb-step-number">4</span>
                <span class="wpb-step-label"><?php esc_html_e('Generate', 'wp-product-builder'); ?></span>
            </div>
            <div class="wpb-step" data-step="5">
                <span class="wpb-step-number">5</span>
                <span class="wpb-step-label"><?php esc_html_e('Publish', 'wp-product-builder'); ?></span>
            </div>
        </div>

        <!-- Step 1: Select Content Type -->
        <div class="wpb-wizard-content" data-step="1">
            <h2><?php esc_html_e('Select Content Type', 'wp-product-builder'); ?></h2>
            <p class="description"><?php esc_html_e('Choose the type of content you want to create.', 'wp-product-builder'); ?></p>

            <div class="wpb-content-types-grid">
                <div class="wpb-content-type-card" data-type="product_review">
                    <span class="dashicons dashicons-star-filled"></span>
                    <h3><?php esc_html_e('Product Review', 'wp-product-builder'); ?></h3>
                    <p><?php esc_html_e('Detailed review of a single product with pros, cons, and verdict.', 'wp-product-builder'); ?></p>
                </div>

                <div class="wpb-content-type-card" data-type="products_roundup">
                    <span class="dashicons dashicons-list-view"></span>
                    <h3><?php esc_html_e('Products Roundup', 'wp-product-builder'); ?></h3>
                    <p><?php esc_html_e('Best X products in a category with brief descriptions.', 'wp-product-builder'); ?></p>
                </div>

                <div class="wpb-content-type-card" data-type="products_comparison">
                    <span class="dashicons dashicons-columns"></span>
                    <h3><?php esc_html_e('Products Comparison', 'wp-product-builder'); ?></h3>
                    <p><?php esc_html_e('Compare 2-4 products side by side with feature comparison.', 'wp-product-builder'); ?></p>
                </div>

                <div class="wpb-content-type-card" data-type="listicle">
                    <span class="dashicons dashicons-editor-ol"></span>
                    <h3><?php esc_html_e('Listicle', 'wp-product-builder'); ?></h3>
                    <p><?php esc_html_e('Numbered list format with products or tips.', 'wp-product-builder'); ?></p>
                </div>

                <div class="wpb-content-type-card" data-type="deals">
                    <span class="dashicons dashicons-tag"></span>
                    <h3><?php esc_html_e('Deals', 'wp-product-builder'); ?></h3>
                    <p><?php esc_html_e('Promotional content highlighting deals and discounts.', 'wp-product-builder'); ?></p>
                </div>
            </div>
        </div>

        <!-- Step 2: Select Products -->
        <div class="wpb-wizard-content" data-step="2" style="display: none;">
            <h2><?php esc_html_e('Select Products', 'wp-product-builder'); ?></h2>
            <p class="description" id="wpb-products-description">
                <?php esc_html_e('Search for products or enter ASINs directly.', 'wp-product-builder'); ?>
            </p>

            <div class="wpb-product-search">
                <div class="wpb-search-box">
                    <input type="text"
                           id="wpb-product-search"
                           placeholder="<?php esc_attr_e('Search Amazon products...', 'wp-product-builder'); ?>"
                           class="regular-text">
                    <button type="button" class="button" id="wpb-search-btn">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e('Search', 'wp-product-builder'); ?>
                    </button>
                </div>

                <div class="wpb-asin-input">
                    <label for="wpb-asin-direct"><?php esc_html_e('Or enter ASIN directly:', 'wp-product-builder'); ?></label>
                    <input type="text"
                           id="wpb-asin-direct"
                           placeholder="B08N5WRWNW"
                           class="regular-text">
                    <button type="button" class="button" id="wpb-add-asin-btn">
                        <?php esc_html_e('Add Product', 'wp-product-builder'); ?>
                    </button>
                </div>
            </div>

            <div class="wpb-search-results" id="wpb-search-results" style="display: none;">
                <h3><?php esc_html_e('Search Results', 'wp-product-builder'); ?></h3>
                <div class="wpb-results-list"></div>
            </div>

            <div class="wpb-selected-products">
                <h3><?php esc_html_e('Selected Products', 'wp-product-builder'); ?> (<span id="wpb-product-count">0</span>)</h3>
                <div class="wpb-selected-list" id="wpb-selected-products"></div>
            </div>
        </div>

        <!-- Step 3: Content Options -->
        <div class="wpb-wizard-content" data-step="3" style="display: none;">
            <h2><?php esc_html_e('Content Options', 'wp-product-builder'); ?></h2>
            <p class="description"><?php esc_html_e('Configure how your content should be generated.', 'wp-product-builder'); ?></p>

            <div class="wpb-options-form">
                <div class="wpb-option-group">
                    <label for="wpb-title"><?php esc_html_e('Title', 'wp-product-builder'); ?></label>
                    <input type="text"
                           id="wpb-title"
                           class="large-text"
                           placeholder="<?php esc_attr_e('Leave empty for auto-generated title', 'wp-product-builder'); ?>">
                </div>

                <div class="wpb-option-group">
                    <label for="wpb-keywords"><?php esc_html_e('Focus Keywords', 'wp-product-builder'); ?></label>
                    <input type="text"
                           id="wpb-keywords"
                           class="large-text"
                           placeholder="<?php esc_attr_e('keyword1, keyword2, keyword3', 'wp-product-builder'); ?>">
                    <p class="description"><?php esc_html_e('Comma-separated keywords to focus on', 'wp-product-builder'); ?></p>
                </div>

                <div class="wpb-option-row">
                    <div class="wpb-option-group">
                        <label for="wpb-tone"><?php esc_html_e('Writing Tone', 'wp-product-builder'); ?></label>
                        <select id="wpb-tone">
                            <option value="professional"><?php esc_html_e('Professional', 'wp-product-builder'); ?></option>
                            <option value="casual"><?php esc_html_e('Casual', 'wp-product-builder'); ?></option>
                            <option value="enthusiastic"><?php esc_html_e('Enthusiastic', 'wp-product-builder'); ?></option>
                            <option value="informative"><?php esc_html_e('Informative', 'wp-product-builder'); ?></option>
                        </select>
                    </div>

                    <div class="wpb-option-group">
                        <label for="wpb-length"><?php esc_html_e('Content Length', 'wp-product-builder'); ?></label>
                        <select id="wpb-length">
                            <option value="short"><?php esc_html_e('Short (500-800 words)', 'wp-product-builder'); ?></option>
                            <option value="medium" selected><?php esc_html_e('Medium (1000-1500 words)', 'wp-product-builder'); ?></option>
                            <option value="long"><?php esc_html_e('Long (2000+ words)', 'wp-product-builder'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="wpb-option-group wpb-checkboxes">
                    <label><?php esc_html_e('Include Sections', 'wp-product-builder'); ?></label>
                    <div class="wpb-checkbox-list">
                        <label>
                            <input type="checkbox" id="wpb-include-pros-cons" checked>
                            <?php esc_html_e('Pros & Cons', 'wp-product-builder'); ?>
                        </label>
                        <label>
                            <input type="checkbox" id="wpb-include-faq" checked>
                            <?php esc_html_e('FAQ Section', 'wp-product-builder'); ?>
                        </label>
                        <label>
                            <input type="checkbox" id="wpb-include-verdict" checked>
                            <?php esc_html_e('Final Verdict', 'wp-product-builder'); ?>
                        </label>
                        <label>
                            <input type="checkbox" id="wpb-include-buying-guide">
                            <?php esc_html_e('Buying Guide', 'wp-product-builder'); ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: Generate -->
        <div class="wpb-wizard-content" data-step="4" style="display: none;">
            <h2><?php esc_html_e('Generate Content', 'wp-product-builder'); ?></h2>

            <div class="wpb-generation-summary">
                <h3><?php esc_html_e('Summary', 'wp-product-builder'); ?></h3>
                <table class="wpb-summary-table">
                    <tr>
                        <th><?php esc_html_e('Content Type', 'wp-product-builder'); ?></th>
                        <td id="summary-type">-</td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Products', 'wp-product-builder'); ?></th>
                        <td id="summary-products">-</td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Title', 'wp-product-builder'); ?></th>
                        <td id="summary-title">-</td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Estimated Tokens', 'wp-product-builder'); ?></th>
                        <td id="summary-tokens">-</td>
                    </tr>
                </table>
            </div>

            <div class="wpb-generation-controls">
                <button type="button" class="button button-primary button-hero" id="wpb-generate-btn">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e('Generate Content', 'wp-product-builder'); ?>
                </button>
            </div>

            <div class="wpb-generation-progress" id="wpb-generation-progress" style="display: none;">
                <div class="wpb-progress-bar">
                    <div class="wpb-progress-fill"></div>
                </div>
                <p class="wpb-progress-text"><?php esc_html_e('Generating content...', 'wp-product-builder'); ?></p>
            </div>
        </div>

        <!-- Step 5: Review & Publish -->
        <div class="wpb-wizard-content" data-step="5" style="display: none;">
            <h2><?php esc_html_e('Review & Publish', 'wp-product-builder'); ?></h2>

            <div class="wpb-content-preview">
                <div class="wpb-preview-header">
                    <h3 id="wpb-preview-title"></h3>
                </div>
                <div class="wpb-preview-content" id="wpb-preview-content"></div>
            </div>

            <div class="wpb-publish-options">
                <div class="wpb-option-row">
                    <div class="wpb-option-group">
                        <label for="wpb-post-status"><?php esc_html_e('Post Status', 'wp-product-builder'); ?></label>
                        <select id="wpb-post-status">
                            <option value="draft"><?php esc_html_e('Draft', 'wp-product-builder'); ?></option>
                            <option value="publish"><?php esc_html_e('Publish', 'wp-product-builder'); ?></option>
                        </select>
                    </div>

                    <div class="wpb-option-group">
                        <label for="wpb-post-category"><?php esc_html_e('Category', 'wp-product-builder'); ?></label>
                        <?php
                        wp_dropdown_categories([
                            'id' => 'wpb-post-category',
                            'name' => 'wpb-post-category',
                            'show_option_none' => __('Select Category', 'wp-product-builder'),
                            'option_none_value' => '',
                            'hierarchical' => true,
                            'hide_empty' => false,
                        ]);
                        ?>
                    </div>
                </div>

                <div class="wpb-publish-actions">
                    <button type="button" class="button button-primary button-hero" id="wpb-create-post-btn">
                        <span class="dashicons dashicons-admin-post"></span>
                        <?php esc_html_e('Create Post', 'wp-product-builder'); ?>
                    </button>
                    <button type="button" class="button" id="wpb-edit-in-gutenberg-btn">
                        <span class="dashicons dashicons-edit-page"></span>
                        <?php esc_html_e('Edit in Gutenberg', 'wp-product-builder'); ?>
                    </button>
                    <button type="button" class="button" id="wpb-copy-content-btn">
                        <span class="dashicons dashicons-clipboard"></span>
                        <?php esc_html_e('Copy to Clipboard', 'wp-product-builder'); ?>
                    </button>
                    <button type="button" class="button" id="wpb-regenerate-btn">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Regenerate', 'wp-product-builder'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="wpb-wizard-navigation">
            <button type="button" class="button" id="wpb-prev-btn" style="display: none;">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e('Previous', 'wp-product-builder'); ?>
            </button>
            <button type="button" class="button button-primary" id="wpb-next-btn">
                <?php esc_html_e('Next', 'wp-product-builder'); ?>
                <span class="dashicons dashicons-arrow-right-alt"></span>
            </button>
        </div>
    </div>

    <?php endif; ?>
</div>
