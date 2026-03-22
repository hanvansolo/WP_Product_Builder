<?php
/**
 * CJ Affiliate (Commission Junction) API Client
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\API;

use WPProductBuilder\Encryption\EncryptionService;
use WPProductBuilder\Database\Repositories\ProductRepository;

/**
 * CJ Affiliate product search API client
 */
class CJClient implements ProductNetworkInterface {
    /**
     * API base URL
     */
    private const API_URL = 'https://product-search.api.cj.com/v2/product-search';

    /**
     * Max requests per minute
     */
    private const RATE_LIMIT = 25;

    /**
     * API key
     */
    private string $apiKey;

    /**
     * Website ID
     */
    private string $websiteId;

    /**
     * Cache duration in seconds
     */
    private int $cacheDuration;

    /**
     * Product repository
     */
    private ProductRepository $productRepo;

    /**
     * Constructor
     *
     * @param string|null $apiKey Optional API key (for testing before save)
     * @param string|null $websiteId Optional website ID (for testing before save)
     */
    public function __construct(?string $apiKey = null, ?string $websiteId = null) {
        $settings = get_option('wpb_settings', []);

        if ($apiKey !== null) {
            $this->apiKey = $apiKey;
            $this->websiteId = $websiteId ?? '';
        } else {
            $encryption = new EncryptionService();
            $credentials = get_option('wpb_credentials_encrypted', []);

            $this->apiKey = !empty($credentials['cj_api_key'])
                ? $encryption->decrypt($credentials['cj_api_key'])
                : '';

            $this->websiteId = $credentials['cj_website_id'] ?? '';
        }

        $this->cacheDuration = ($settings['cache_duration_hours'] ?? 24) * HOUR_IN_SECONDS;
        $this->productRepo = new ProductRepository();
    }

    /**
     * {@inheritdoc}
     */
    public function getNetworkName(): string {
        return 'cj';
    }

    /**
     * {@inheritdoc}
     */
    public function getNetworkLabel(): string {
        return 'CJ Affiliate';
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured(): bool {
        return !empty($this->apiKey) && !empty($this->websiteId);
    }

    /**
     * {@inheritdoc}
     */
    public function searchProducts(string $keywords, array $options = []): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => __('CJ Affiliate API is not configured.', 'wp-product-builder'),
            ];
        }

        $this->enforceRateLimit();

        $count = min($options['item_count'] ?? 10, 50);

        $queryParams = [
            'website-id' => $this->websiteId,
            'keywords' => $keywords,
            'records-per-page' => $count,
        ];

        if (!empty($options['advertiser_ids'])) {
            $queryParams['advertiser-ids'] = $options['advertiser_ids'];
        }

        $url = self::API_URL . '?' . http_build_query($queryParams);

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/xml',
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($statusCode >= 400) {
            return [
                'success' => false,
                'error' => sprintf(
                    __('CJ API request failed with status %d', 'wp-product-builder'),
                    $statusCode
                ),
            ];
        }

        $products = $this->parseSearchResponse($body);

        // Cache all products
        foreach ($products as $product) {
            $this->productRepo->cacheProduct($product, $this->cacheDuration);
        }

        // Track API usage
        $this->trackUsage('ProductSearch');

        return [
            'success' => true,
            'products' => $products,
            'total_results' => count($products),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getProduct(string $productId): ?array {
        // Check cache first (CJ has no get-by-ID endpoint)
        $cached = $this->productRepo->getByProductId($productId, 'cj');
        if ($cached && !$this->isExpired($cached['expires_at'])) {
            return json_decode($cached['product_data'], true);
        }

        // Return expired cache as fallback
        if ($cached) {
            return json_decode($cached['product_data'], true);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultipleProducts(array $productIds): array {
        $products = [];

        foreach ($productIds as $id) {
            $product = $this->getProduct($id);
            if ($product) {
                $products[$id] = $product;
            }
        }

        return [
            'success' => true,
            'products' => $products,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function testConnection(): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => __('CJ Affiliate API credentials are not configured.', 'wp-product-builder'),
            ];
        }

        $result = $this->searchProducts('test', ['item_count' => 1]);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => __('CJ Affiliate connection successful!', 'wp-product-builder'),
            ];
        }

        return [
            'success' => false,
            'message' => $result['error'] ?? __('CJ Affiliate connection failed.', 'wp-product-builder'),
        ];
    }

    /**
     * Parse XML search response from CJ API
     *
     * @param string $xml Raw XML response
     * @return array Normalized product arrays
     */
    private function parseSearchResponse(string $xml): array {
        $products = [];

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            return [];
        }

        // CJ returns <products><product>...</product></products>
        $items = $doc->products->product ?? $doc->product ?? [];

        foreach ($items as $item) {
            $product = $this->parseProductData($item);
            if ($product) {
                $products[] = $product;
            }
        }

        return $products;
    }

    /**
     * Parse a single CJ product XML element into normalized array
     *
     * @param \SimpleXMLElement $item Product XML element
     * @return array|null Normalized product data
     */
    private function parseProductData(\SimpleXMLElement $item): ?array {
        $adId = (string) ($item->{'ad-id'} ?? '');
        $catalogId = (string) ($item->{'catalog-id'} ?? '');
        $sku = (string) ($item->{'sku'} ?? '');

        // Build product ID from available identifiers
        $productId = '';
        if ($adId && $catalogId) {
            $productId = $adId . '_' . $catalogId;
        } elseif ($sku) {
            $productId = 'sku_' . $sku;
        } else {
            return null;
        }

        $title = (string) ($item->{'name'} ?? '');
        if (empty($title)) {
            return null;
        }

        // Price handling
        $price = (string) ($item->{'price'} ?? '');
        $salePrice = (string) ($item->{'sale-price'} ?? '');
        $currency = (string) ($item->{'currency'} ?? 'USD');
        $displayPrice = !empty($salePrice) ? $salePrice : $price;
        if ($displayPrice && !str_starts_with($displayPrice, '$') && !str_starts_with($displayPrice, '£') && !str_starts_with($displayPrice, '€')) {
            $displayPrice = '$' . $displayPrice;
        }

        // Features from description
        $description = (string) ($item->{'description'} ?? '');
        $features = [];
        if ($description) {
            // Extract bullet-point style features from description
            $sentences = preg_split('/[.!]\s+/', strip_tags($description));
            $features = array_filter(array_map('trim', array_slice($sentences, 0, 5)));
        }

        return [
            'product_id' => $productId,
            'asin' => null,
            'network' => 'cj',
            'title' => $title,
            'brand' => (string) ($item->{'advertiser-name'} ?? $item->{'manufacturer-name'} ?? ''),
            'price' => $displayPrice ?: null,
            'currency' => $currency,
            'availability' => 'In Stock',
            'image_url' => (string) ($item->{'image-url'} ?? ''),
            'affiliate_url' => (string) ($item->{'buy-url'} ?? ''),
            'rating' => null,
            'review_count' => null,
            'features' => array_values($features),
            'description' => mb_substr($description, 0, 2000),
            'category' => (string) ($item->{'advertiser-category'} ?? ''),
            'marketplace' => '',
            'merchant_name' => (string) ($item->{'advertiser-name'} ?? ''),
            'source' => 'cj_api',
            'fetched_at' => current_time('mysql'),
        ];
    }

    /**
     * Enforce rate limiting
     */
    private function enforceRateLimit(): void {
        $transient_key = 'wpb_rate_cj';
        $timestamps = get_transient($transient_key) ?: [];

        // Remove timestamps older than 60 seconds
        $cutoff = microtime(true) - 60;
        $timestamps = array_filter($timestamps, fn($ts) => $ts > $cutoff);

        if (count($timestamps) >= self::RATE_LIMIT) {
            $oldest = min($timestamps);
            $wait = (int) ceil(60 - (microtime(true) - $oldest));
            if ($wait > 0) {
                sleep(min($wait, 5));
            }
        }

        $timestamps[] = microtime(true);
        set_transient($transient_key, $timestamps, 120);
    }

    /**
     * Track API usage
     *
     * @param string $endpoint Endpoint name
     */
    private function trackUsage(string $endpoint): void {
        global $wpdb;

        $table = $wpdb->prefix . 'wpb_api_usage';
        $user_id = get_current_user_id();
        $today = current_time('Y-m-d');

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (user_id, api_type, endpoint, request_count, date_recorded)
             VALUES (%d, 'cj', %s, 1, %s)
             ON DUPLICATE KEY UPDATE
             request_count = request_count + 1",
            $user_id,
            $endpoint,
            $today
        ));
    }

    /**
     * Check if cached product is expired
     *
     * @param string $expiresAt Expiration datetime
     * @return bool
     */
    private function isExpired(string $expiresAt): bool {
        return strtotime($expiresAt) < time();
    }
}
