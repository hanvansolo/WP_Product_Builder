<?php
/**
 * CJ Affiliate (Commission Junction) API Client
 *
 * Uses the CJ Advertiser Product Search GraphQL API
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\API;

use WPProductBuilder\Encryption\EncryptionService;
use WPProductBuilder\Database\Repositories\ProductRepository;

/**
 * CJ Affiliate product search API client (GraphQL)
 */
class CJClient implements ProductNetworkInterface {
    /**
     * CJ GraphQL API endpoint
     */
    private const API_URL = 'https://ads.api.cj.com/query';

    /**
     * Max requests per minute
     */
    private const RATE_LIMIT = 25;

    /**
     * API key (Personal Access Token)
     */
    private string $apiKey;

    /**
     * Company ID (formerly Website ID)
     */
    private string $companyId;

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
     * @param string|null $companyId Optional company/website ID (for testing before save)
     */
    public function __construct(?string $apiKey = null, ?string $companyId = null) {
        $settings = get_option('wpb_settings', []);

        if ($apiKey !== null) {
            $this->apiKey = $apiKey;
            $this->companyId = $companyId ?? '';
        } else {
            $encryption = new EncryptionService();
            $credentials = get_option('wpb_credentials_encrypted', []);

            $this->apiKey = !empty($credentials['cj_api_key'])
                ? $encryption->decrypt($credentials['cj_api_key'])
                : '';

            $this->companyId = $credentials['cj_website_id'] ?? '';
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
        return !empty($this->apiKey) && !empty($this->companyId);
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

        $query = <<<GRAPHQL
        {
            products(companyId: "{$this->companyId}", keywords: "{$this->escapeGraphQL($keywords)}", limit: {$count}) {
                totalCount
                records {
                    catalogId
                    adId
                    advertiserId
                    advertiserName
                    title
                    description
                    price {
                        amount
                        currency
                    }
                    salePrice {
                        amount
                        currency
                    }
                    imageUrl
                    buyUrl
                    impressionUrl
                    sku
                    upc
                    manufacturerName
                    inStock
                    catalogName
                }
            }
        }
        GRAPHQL;

        $response = wp_remote_post(self::API_URL, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode(['query' => $query]),
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
            $decoded = json_decode($body, true);
            $errorMsg = $decoded['errors'][0]['message'] ?? '';
            return [
                'success' => false,
                'error' => sprintf(
                    __('CJ API error (HTTP %d): %s', 'wp-product-builder'),
                    $statusCode,
                    $errorMsg ?: $body
                ),
            ];
        }

        $data = json_decode($body, true);

        // Check for GraphQL errors
        if (!empty($data['errors'])) {
            return [
                'success' => false,
                'error' => $data['errors'][0]['message'] ?? __('CJ API returned an error.', 'wp-product-builder'),
            ];
        }

        $records = $data['data']['products']['records'] ?? [];
        $products = [];

        foreach ($records as $item) {
            $product = $this->parseProductData($item);
            if ($product) {
                $products[] = $product;
                $this->productRepo->cacheProduct($product, $this->cacheDuration);
            }
        }

        // Track API usage
        $this->trackUsage('ProductSearch');

        return [
            'success' => true,
            'products' => $products,
            'total_results' => $data['data']['products']['totalCount'] ?? count($products),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getProduct(string $productId): ?array {
        // Check cache first (CJ GraphQL doesn't support lookup by single product ID easily)
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

        // Simple query to test authentication
        $query = <<<GRAPHQL
        {
            products(companyId: "{$this->companyId}", keywords: "test", limit: 1) {
                totalCount
                records {
                    title
                }
            }
        }
        GRAPHQL;

        $response = wp_remote_post(self::API_URL, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode(['query' => $query]),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode === 401 || $statusCode === 403) {
            return [
                'success' => false,
                'message' => __('Authentication failed. Check your API key.', 'wp-product-builder'),
            ];
        }

        if ($statusCode >= 400) {
            $errorMsg = $body['errors'][0]['message'] ?? '';
            return [
                'success' => false,
                'message' => sprintf(
                    __('CJ API error (HTTP %d): %s', 'wp-product-builder'),
                    $statusCode,
                    $errorMsg ?: __('Unknown error', 'wp-product-builder')
                ),
            ];
        }

        // Check for GraphQL-level errors
        if (!empty($body['errors'])) {
            return [
                'success' => false,
                'message' => $body['errors'][0]['message'] ?? __('CJ API returned an error.', 'wp-product-builder'),
            ];
        }

        $totalCount = $body['data']['products']['totalCount'] ?? 0;

        return [
            'success' => true,
            'message' => sprintf(
                __('CJ Affiliate connection successful! %d products available.', 'wp-product-builder'),
                $totalCount
            ),
        ];
    }

    /**
     * Parse a CJ product record into normalized array
     *
     * @param array $item Product record from GraphQL response
     * @return array|null Normalized product data
     */
    private function parseProductData(array $item): ?array {
        $adId = (string) ($item['adId'] ?? '');
        $catalogId = (string) ($item['catalogId'] ?? '');
        $sku = (string) ($item['sku'] ?? '');

        // Build product ID from available identifiers
        $productId = '';
        if ($adId && $catalogId) {
            $productId = $adId . '_' . $catalogId;
        } elseif ($sku) {
            $productId = 'sku_' . $sku;
        } else {
            return null;
        }

        $title = $item['title'] ?? '';
        if (empty($title)) {
            return null;
        }

        // Price handling
        $priceData = $item['salePrice'] ?? $item['price'] ?? null;
        $displayPrice = null;
        $currency = 'USD';
        if ($priceData && !empty($priceData['amount'])) {
            $currency = $priceData['currency'] ?? 'USD';
            $symbol = match ($currency) {
                'GBP' => '£',
                'EUR' => '€',
                'JPY' => '¥',
                default => '$',
            };
            $displayPrice = $symbol . number_format((float) $priceData['amount'], 2);
        }

        // Features from description
        $description = $item['description'] ?? '';
        $features = [];
        if ($description) {
            $sentences = preg_split('/[.!]\s+/', strip_tags($description));
            $features = array_filter(array_map('trim', array_slice($sentences, 0, 5)));
        }

        $advertiserName = $item['advertiserName'] ?? $item['manufacturerName'] ?? '';

        return [
            'product_id' => $productId,
            'asin' => null,
            'network' => 'cj',
            'title' => $title,
            'brand' => $advertiserName,
            'price' => $displayPrice,
            'currency' => $currency,
            'availability' => ($item['inStock'] ?? true) ? 'In Stock' : 'Out of Stock',
            'image_url' => $item['imageUrl'] ?? '',
            'affiliate_url' => $item['buyUrl'] ?? '',
            'rating' => null,
            'review_count' => null,
            'features' => array_values($features),
            'description' => mb_substr($description, 0, 2000),
            'category' => $item['catalogName'] ?? '',
            'marketplace' => '',
            'merchant_name' => $advertiserName,
            'source' => 'cj_api',
            'fetched_at' => current_time('mysql'),
        ];
    }

    /**
     * Escape string for use in GraphQL query
     */
    private function escapeGraphQL(string $value): string {
        return str_replace(['"', '\\', "\n", "\r"], ['\\"', '\\\\', '\\n', '\\r'], $value);
    }

    /**
     * Enforce rate limiting
     */
    private function enforceRateLimit(): void {
        $transient_key = 'wpb_rate_cj';
        $timestamps = get_transient($transient_key) ?: [];

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
     */
    private function isExpired(string $expiresAt): bool {
        return strtotime($expiresAt) < time();
    }
}
