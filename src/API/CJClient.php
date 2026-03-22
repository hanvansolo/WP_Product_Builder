<?php
/**
 * CJ Affiliate (Commission Junction) API Client
 *
 * Uses the CJ GraphQL API at https://ads.api.cj.com/query
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
     * Company ID (CID)
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

    /** {@inheritdoc} */
    public function getNetworkName(): string {
        return 'cj';
    }

    /** {@inheritdoc} */
    public function getNetworkLabel(): string {
        return 'CJ Affiliate';
    }

    /** {@inheritdoc} */
    public function isConfigured(): bool {
        return !empty($this->apiKey) && !empty($this->companyId);
    }

    /** {@inheritdoc} */
    public function searchProducts(string $keywords, array $options = []): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => __('CJ Affiliate API is not configured.', 'wp-product-builder'),
            ];
        }

        $this->enforceRateLimit();

        $count = min($options['item_count'] ?? 10, 50);
        $escapedKeywords = $this->escapeGraphQL($keywords);

        $query = <<<GRAPHQL
        {
            products(companyId: "{$this->companyId}", keywords: ["{$escapedKeywords}"], limit: {$count}) {
                totalCount
                resultList {
                    id
                    adId
                    catalogId
                    advertiserId
                    advertiserName
                    title
                    description
                    price { amount currency }
                    salePrice { amount currency }
                    imageLink
                    link
                    brand
                    catalogName
                }
            }
        }
        GRAPHQL;

        $response = $this->makeRequest($query);

        if (!$response['success']) {
            return $response;
        }

        $data = $response['data'];
        $records = $data['data']['products']['resultList'] ?? [];
        $products = [];

        foreach ($records as $item) {
            $product = $this->parseProductData($item);
            if ($product) {
                $products[] = $product;
                $this->productRepo->cacheProduct($product, $this->cacheDuration);
            }
        }

        $this->trackUsage('ProductSearch');

        return [
            'success' => true,
            'products' => $products,
            'total_results' => $data['data']['products']['totalCount'] ?? count($products),
        ];
    }

    /** {@inheritdoc} */
    public function getProduct(string $productId): ?array {
        $cached = $this->productRepo->getByProductId($productId, 'cj');
        if ($cached && !$this->isExpired($cached['expires_at'])) {
            return json_decode($cached['product_data'], true);
        }

        if ($cached) {
            return json_decode($cached['product_data'], true);
        }

        return null;
    }

    /** {@inheritdoc} */
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

    /** {@inheritdoc} */
    public function testConnection(): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => __('CJ Affiliate API credentials are not configured.', 'wp-product-builder'),
            ];
        }

        $query = <<<GRAPHQL
        {
            products(companyId: "{$this->companyId}", keywords: ["test"], limit: 1) {
                totalCount
            }
        }
        GRAPHQL;

        $response = $this->makeRequest($query);

        if (!$response['success']) {
            return [
                'success' => false,
                'message' => $response['error'],
            ];
        }

        $data = $response['data'];

        if (!empty($data['errors'])) {
            $errorMsg = $data['errors'][0]['message'] ?? __('Unknown error', 'wp-product-builder');
            return [
                'success' => false,
                'message' => sprintf(__('CJ API error: %s', 'wp-product-builder'), $errorMsg),
            ];
        }

        $totalCount = $data['data']['products']['totalCount'] ?? 0;

        return [
            'success' => true,
            'message' => sprintf(
                __('CJ Affiliate connection successful! %d products available.', 'wp-product-builder'),
                $totalCount
            ),
        ];
    }

    /**
     * Make a GraphQL request to the CJ API
     */
    private function makeRequest(string $query): array {
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
        $decoded = json_decode($body, true);

        // Handle HTTP errors
        if ($statusCode === 401) {
            return [
                'success' => false,
                'error' => __('CJ authentication failed. Check your API key (Personal Access Token).', 'wp-product-builder'),
            ];
        }

        if ($statusCode === 403) {
            // CJ returns 403 when companyId is wrong
            if (is_string($body) && str_contains($body, 'not authorized')) {
                return [
                    'success' => false,
                    'error' => __('Not authorized for this Company ID. Make sure you enter your CID (not Website ID) from Account > Company Info in CJ.', 'wp-product-builder'),
                ];
            }
            return [
                'success' => false,
                'error' => __('CJ access denied. Check your API key and Company ID.', 'wp-product-builder'),
            ];
        }

        if ($statusCode >= 400) {
            // CJ returns validation errors as plain text for 400s
            $errorMsg = '';
            if (!empty($decoded['errors'][0]['message'])) {
                $errorMsg = $decoded['errors'][0]['message'];
            } elseif (is_string($body) && !empty($body)) {
                $errorMsg = wp_strip_all_tags($body);
            }

            // Check for common auth error
            if (str_contains($body, 'not authorized')) {
                $errorMsg = __('Not authorized for this Company ID. Check your CID in CJ account settings (Account > Company Info).', 'wp-product-builder');
            }

            return [
                'success' => false,
                'error' => sprintf(
                    __('CJ API error (HTTP %d): %s', 'wp-product-builder'),
                    $statusCode,
                    $errorMsg ?: __('Unknown error', 'wp-product-builder')
                ),
            ];
        }

        // Handle GraphQL-level errors
        if (!empty($decoded['errors'])) {
            return [
                'success' => false,
                'error' => $decoded['errors'][0]['message'] ?? __('CJ API returned an error.', 'wp-product-builder'),
            ];
        }

        return [
            'success' => true,
            'data' => $decoded,
        ];
    }

    /**
     * Parse a CJ product record into normalized array
     */
    private function parseProductData(array $item): ?array {
        $adId = (string) ($item['adId'] ?? '');
        $catalogId = (string) ($item['catalogId'] ?? '');
        $id = (string) ($item['id'] ?? '');

        $productId = '';
        if ($adId && $catalogId) {
            $productId = $adId . '_' . $catalogId;
        } elseif ($id) {
            $productId = $id;
        } else {
            return null;
        }

        $title = $item['title'] ?? '';
        if (empty($title)) {
            return null;
        }

        // Price
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

        $advertiserName = $item['advertiserName'] ?? '';

        return [
            'product_id' => $productId,
            'asin' => null,
            'network' => 'cj',
            'title' => $title,
            'brand' => $item['brand'] ?? $advertiserName,
            'price' => $displayPrice,
            'currency' => $currency,
            'availability' => 'In Stock',
            'image_url' => $item['imageLink'] ?? '',
            'affiliate_url' => $item['link'] ?? '',
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

    private function escapeGraphQL(string $value): string {
        return str_replace(['"', '\\', "\n", "\r"], ['\\"', '\\\\', '\\n', '\\r'], $value);
    }

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

    private function isExpired(string $expiresAt): bool {
        return strtotime($expiresAt) < time();
    }
}
