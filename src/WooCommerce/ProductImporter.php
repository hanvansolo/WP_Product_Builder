<?php
/**
 * WooCommerce Product Importer
 *
 * Imports Amazon products into WooCommerce as affiliate products
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\WooCommerce;

use WPProductBuilder\Services\ProductDataService;
use WPProductBuilder\Services\ImageDownloader;

/**
 * Handles importing Amazon products to WooCommerce
 */
class ProductImporter {
    /**
     * Product data service
     */
    private ProductDataService $productService;

    /**
     * Image downloader
     */
    private ImageDownloader $imageDownloader;

    /**
     * Import settings
     */
    private array $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->productService = new ProductDataService();
        $this->imageDownloader = new ImageDownloader();
        $this->settings = get_option('wpb_woo_settings', $this->getDefaultSettings());
    }

    /**
     * Check if WooCommerce is active
     */
    public static function isWooCommerceActive(): bool {
        return class_exists('WooCommerce');
    }

    /**
     * Import single product
     *
     * @param string $asin Product ASIN
     * @param array $options Import options
     * @return int|WP_Error Product ID or error
     */
    public function importProduct(string $asin, array $options = []): int|\WP_Error {
        if (!self::isWooCommerceActive()) {
            return new \WP_Error('woo_not_active', __('WooCommerce is not active.', 'wp-product-builder'));
        }

        // Check if already imported
        $existingId = $this->getExistingProductId($asin);
        if ($existingId && empty($options['force_reimport'])) {
            return $existingId;
        }

        // Fetch product data
        $productData = $this->productService->getProduct($asin);

        if (!$productData) {
            return new \WP_Error('fetch_failed', __('Could not fetch product data.', 'wp-product-builder'));
        }

        // Create or update WooCommerce product
        if ($existingId) {
            return $this->updateProduct($existingId, $productData, $options);
        }

        return $this->createProduct($productData, $options);
    }

    /**
     * Import multiple products
     *
     * @param array $asins Array of ASINs
     * @param array $options Import options
     * @return array Results with product IDs or errors
     */
    public function importMultipleProducts(array $asins, array $options = []): array {
        $results = [];

        foreach ($asins as $asin) {
            $result = $this->importProduct($asin, $options);

            $results[$asin] = [
                'success' => !is_wp_error($result),
                'product_id' => is_wp_error($result) ? null : $result,
                'error' => is_wp_error($result) ? $result->get_error_message() : null,
            ];
        }

        return $results;
    }

    /**
     * Create WooCommerce product
     */
    private function createProduct(array $productData, array $options): int|\WP_Error {
        $product = new \WC_Product_External();

        // Basic info
        $product->set_name($productData['title']);
        $product->set_status($options['status'] ?? $this->settings['default_status']);
        $product->set_catalog_visibility($options['visibility'] ?? 'visible');

        // External/Affiliate settings
        $product->set_product_url($productData['affiliate_url']);
        $product->set_button_text($this->settings['button_text'] ?? __('Buy on Amazon', 'wp-product-builder'));

        // Price
        if (!empty($productData['price'])) {
            $numericPrice = $this->extractNumericPrice($productData['price']);
            if ($numericPrice) {
                $product->set_regular_price($numericPrice);
            }
        }

        // Description
        $description = $this->buildDescription($productData);
        $product->set_description($description);

        // Short description from features
        if (!empty($productData['features'])) {
            $shortDesc = '<ul>';
            foreach (array_slice($productData['features'], 0, 5) as $feature) {
                $shortDesc .= '<li>' . esc_html($feature) . '</li>';
            }
            $shortDesc .= '</ul>';
            $product->set_short_description($shortDesc);
        }

        // SKU (ASIN)
        $product->set_sku('ASIN-' . $productData['asin']);

        // Categories
        if (!empty($options['category_id'])) {
            $product->set_category_ids([(int) $options['category_id']]);
        } elseif (!empty($productData['category']) && $this->settings['auto_create_categories']) {
            $categoryId = $this->getOrCreateCategory($productData['category']);
            if ($categoryId) {
                $product->set_category_ids([$categoryId]);
            }
        }

        // Save product first to get ID
        $productId = $product->save();

        if (!$productId) {
            return new \WP_Error('save_failed', __('Failed to save product.', 'wp-product-builder'));
        }

        // Handle image
        if (!empty($productData['image_url']) && $this->settings['download_images']) {
            $this->setProductImage($productId, $productData['image_url'], $productData['title']);
        }

        // Save Amazon metadata
        $this->saveProductMeta($productId, $productData);

        // Track import
        $this->trackImport($productId, $productData['asin']);

        return $productId;
    }

    /**
     * Update existing WooCommerce product
     */
    private function updateProduct(int $productId, array $productData, array $options): int|\WP_Error {
        $product = wc_get_product($productId);

        if (!$product) {
            return new \WP_Error('product_not_found', __('Product not found.', 'wp-product-builder'));
        }

        // Update price if changed
        if (!empty($productData['price']) && $this->settings['sync_prices']) {
            $numericPrice = $this->extractNumericPrice($productData['price']);
            if ($numericPrice) {
                $product->set_regular_price($numericPrice);
            }
        }

        // Update availability/stock status
        if ($this->settings['sync_availability']) {
            $inStock = $this->isInStock($productData['availability'] ?? '');
            $product->set_stock_status($inStock ? 'instock' : 'outofstock');
        }

        // Update affiliate URL (in case tag changed)
        $product->set_product_url($productData['affiliate_url']);

        $product->save();

        // Update metadata
        $this->saveProductMeta($productId, $productData);

        return $productId;
    }

    /**
     * Build product description
     */
    private function buildDescription(array $productData): string {
        $description = '';

        if (!empty($productData['description'])) {
            $description .= $productData['description'] . "\n\n";
        }

        if (!empty($productData['features'])) {
            $description .= "<h3>" . __('Key Features', 'wp-product-builder') . "</h3>\n<ul>\n";
            foreach ($productData['features'] as $feature) {
                $description .= '<li>' . esc_html($feature) . "</li>\n";
            }
            $description .= "</ul>\n";
        }

        // Add affiliate disclosure
        $settings = get_option('wpb_settings', []);
        if (!empty($settings['affiliate_disclosure'])) {
            $description .= "\n\n<p class='wpb-affiliate-disclosure'><em>" . esc_html($settings['affiliate_disclosure']) . "</em></p>";
        }

        return $description;
    }

    /**
     * Set product featured image
     */
    private function setProductImage(int $productId, string $imageUrl, string $title): void {
        $attachmentId = $this->imageDownloader->downloadAndAttach($imageUrl, $productId, $title);

        if ($attachmentId) {
            set_post_thumbnail($productId, $attachmentId);
        }
    }

    /**
     * Save Amazon-specific metadata
     */
    private function saveProductMeta(int $productId, array $productData): void {
        update_post_meta($productId, '_wpb_asin', $productData['asin']);
        update_post_meta($productId, '_wpb_marketplace', $productData['marketplace']);
        update_post_meta($productId, '_wpb_amazon_price', $productData['price'] ?? '');
        update_post_meta($productId, '_wpb_amazon_rating', $productData['rating'] ?? '');
        update_post_meta($productId, '_wpb_amazon_reviews', $productData['review_count'] ?? '');
        update_post_meta($productId, '_wpb_last_sync', current_time('timestamp'));
        update_post_meta($productId, '_wpb_source', $productData['source'] ?? 'unknown');
    }

    /**
     * Get existing product ID by ASIN
     */
    private function getExistingProductId(string $asin): ?int {
        global $wpdb;

        $productId = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_wpb_asin' AND meta_value = %s
             LIMIT 1",
            $asin
        ));

        return $productId ? (int) $productId : null;
    }

    /**
     * Get or create product category
     */
    private function getOrCreateCategory(string $categoryName): ?int {
        $term = get_term_by('name', $categoryName, 'product_cat');

        if ($term) {
            return $term->term_id;
        }

        $result = wp_insert_term($categoryName, 'product_cat');

        if (is_wp_error($result)) {
            return null;
        }

        return $result['term_id'];
    }

    /**
     * Extract numeric price from string
     */
    private function extractNumericPrice(string $priceString): ?string {
        // Remove currency symbols and extract number
        preg_match('/[\d,]+\.?\d*/', str_replace(',', '', $priceString), $matches);
        return $matches[0] ?? null;
    }

    /**
     * Check if product is in stock based on availability text
     */
    private function isInStock(string $availability): bool {
        $outOfStockPhrases = [
            'out of stock',
            'currently unavailable',
            'not available',
            'sold out',
        ];

        $availability = strtolower($availability);

        foreach ($outOfStockPhrases as $phrase) {
            if (str_contains($availability, $phrase)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Track product import
     */
    private function trackImport(int $productId, string $asin): void {
        global $wpdb;

        $table = $wpdb->prefix . 'wpb_import_log';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $wpdb->insert($table, [
            'product_id' => $productId,
            'asin' => $asin,
            'imported_at' => current_time('mysql'),
            'user_id' => get_current_user_id(),
        ]);
    }

    /**
     * Get default settings
     */
    private function getDefaultSettings(): array {
        return [
            'default_status' => 'publish',
            'download_images' => true,
            'auto_create_categories' => true,
            'sync_prices' => true,
            'sync_availability' => true,
            'button_text' => __('Buy on Amazon', 'wp-product-builder'),
        ];
    }

    /**
     * Sync all imported products
     */
    public function syncAllProducts(): array {
        global $wpdb;

        $asins = $wpdb->get_col(
            "SELECT meta_value FROM {$wpdb->postmeta}
             WHERE meta_key = '_wpb_asin'"
        );

        $results = [
            'total' => count($asins),
            'synced' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($asins as $asin) {
            $result = $this->importProduct($asin, ['force_reimport' => false]);

            if (is_wp_error($result)) {
                $results['failed']++;
                $results['errors'][$asin] = $result->get_error_message();
            } else {
                $results['synced']++;
            }
        }

        return $results;
    }

    /**
     * Sync a single product with latest Amazon data
     *
     * @param int $productId WooCommerce product ID
     * @param string $asin Product ASIN
     * @return array|WP_Error Updated fields or error
     */
    public function syncProduct(int $productId, string $asin): array|\WP_Error {
        if (!self::isWooCommerceActive()) {
            return new \WP_Error('woo_not_active', __('WooCommerce is not active.', 'wp-product-builder'));
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return new \WP_Error('product_not_found', __('Product not found.', 'wp-product-builder'));
        }

        // Fetch fresh product data
        $productData = $this->productService->getProduct($asin);
        if (!$productData) {
            return new \WP_Error('fetch_failed', __('Could not fetch product data from Amazon.', 'wp-product-builder'));
        }

        $updatedFields = [];

        // Sync price
        if (!empty($productData['price']) && $this->settings['sync_prices']) {
            $numericPrice = $this->extractNumericPrice($productData['price']);
            if ($numericPrice) {
                $oldPrice = $product->get_regular_price();
                if ($oldPrice !== $numericPrice) {
                    $product->set_regular_price($numericPrice);
                    $updatedFields['price'] = [
                        'old' => $oldPrice,
                        'new' => $numericPrice,
                    ];
                }
            }
        }

        // Sync availability
        if ($this->settings['sync_availability']) {
            $inStock = $this->isInStock($productData['availability'] ?? '');
            $oldStatus = $product->get_stock_status();
            $newStatus = $inStock ? 'instock' : 'outofstock';

            if ($oldStatus !== $newStatus) {
                $product->set_stock_status($newStatus);
                $updatedFields['stock_status'] = [
                    'old' => $oldStatus,
                    'new' => $newStatus,
                ];
            }
        }

        // Update affiliate URL
        $product->set_product_url($productData['affiliate_url']);

        // Save if any changes
        if (!empty($updatedFields)) {
            $product->save();
        }

        // Update metadata
        $this->saveProductMeta($productId, $productData);

        // Log the sync
        $this->logSync($productId, $asin, $updatedFields);

        return $updatedFields;
    }

    /**
     * Log sync action
     */
    private function logSync(int $productId, string $asin, array $updatedFields): void {
        global $wpdb;

        $table = $wpdb->prefix . 'wpb_import_log';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $wpdb->insert($table, [
            'asin' => $asin,
            'product_id' => $productId,
            'action' => 'sync',
            'status' => empty($updatedFields) ? 'no_changes' : 'updated',
            'message' => empty($updatedFields)
                ? __('No changes detected', 'wp-product-builder')
                : sprintf(__('%d fields updated', 'wp-product-builder'), count($updatedFields)),
            'details' => json_encode($updatedFields),
            'user_id' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Get products that need syncing
     *
     * @param int $limit Maximum products to return
     * @param int $hoursStale Consider stale after this many hours
     * @return array Product IDs and ASINs
     */
    public function getProductsNeedingSync(int $limit = 50, int $hoursStale = 24): array {
        global $wpdb;

        $staleTime = time() - ($hoursStale * 3600);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT pm1.post_id, pm1.meta_value as asin, pm2.meta_value as last_sync
             FROM {$wpdb->postmeta} pm1
             LEFT JOIN {$wpdb->postmeta} pm2
                ON pm1.post_id = pm2.post_id AND pm2.meta_key = '_wpb_last_sync'
             WHERE pm1.meta_key = '_wpb_asin'
                AND (pm2.meta_value IS NULL OR pm2.meta_value < %d)
             LIMIT %d",
            $staleTime,
            $limit
        ), ARRAY_A);
    }
}
