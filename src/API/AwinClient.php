<?php
/**
 * Awin API Client
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\API;

use WPProductBuilder\Encryption\EncryptionService;
use WPProductBuilder\Database\Repositories\ProductRepository;

/**
 * Awin product data feed and publisher API client
 */
class AwinClient implements ProductNetworkInterface {
    /**
     * Product data feed base URL
     */
    private const DATAFEED_URL = 'https://productdata.awin.com/datafeed/list/apikey';

    /**
     * Publisher API base URL
     */
    private const PUBLISHER_API_URL = 'https://api.awin.com/publishers';

    /**
     * Max requests per minute
     */
    private const RATE_LIMIT = 20;

    /**
     * API key
     */
    private string $apiKey;

    /**
     * Publisher ID
     */
    private string $publisherId;

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
     * @param string|null $publisherId Optional publisher ID (for testing before save)
     */
    public function __construct(?string $apiKey = null, ?string $publisherId = null) {
        $settings = get_option('wpb_settings', []);
        $encryption = new EncryptionService();
        $credentials = get_option('wpb_credentials_encrypted', []);

        // Use provided values or fall back to saved credentials (independently)
        $this->apiKey = $apiKey ?? (
            !empty($credentials['awin_api_key'])
                ? $encryption->decrypt($credentials['awin_api_key'])
                : ''
        );

        $this->publisherId = $publisherId ?? ($credentials['awin_publisher_id'] ?? '');
        }

        $this->cacheDuration = ($settings['cache_duration_hours'] ?? 24) * HOUR_IN_SECONDS;
        $this->productRepo = new ProductRepository();
    }

    /**
     * {@inheritdoc}
     */
    public function getNetworkName(): string {
        return 'awin';
    }

    /**
     * {@inheritdoc}
     */
    public function getNetworkLabel(): string {
        return 'Awin';
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured(): bool {
        return !empty($this->apiKey) && !empty($this->publisherId);
    }

    /**
     * {@inheritdoc}
     */
    public function searchProducts(string $keywords, array $options = []): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => __('Awin API is not configured.', 'wp-product-builder'),
            ];
        }

        $this->enforceRateLimit();

        $count = min($options['item_count'] ?? 10, 50);

        // Build product data feed URL with search filter
        $url = self::DATAFEED_URL . '/' . $this->apiKey
            . '/language/en'
            . '/fq/product_name:' . rawurlencode($keywords)
            . '/columns/aw_product_id,product_name,merchant_name,search_price,currency,'
            . 'aw_deep_link,aw_image_url,merchant_image_url,description,category_name,'
            . 'brand_name,in_stock,data_feed_id'
            . '/format/json'
            . '/noresults/' . $count;

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
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
                    __('Awin API request failed with status %d', 'wp-product-builder'),
                    $statusCode
                ),
            ];
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return [
                'success' => false,
                'error' => __('Invalid response from Awin API.', 'wp-product-builder'),
            ];
        }

        $products = [];
        foreach ($data as $item) {
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
            'total_results' => count($products),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getProduct(string $productId): ?array {
        // Check cache first
        $cached = $this->productRepo->getByProductId($productId, 'awin');
        if ($cached && !$this->isExpired($cached['expires_at'])) {
            return json_decode($cached['product_data'], true);
        }

        // Try searching by product ID via data feed
        if ($this->isConfigured()) {
            $this->enforceRateLimit();

            $url = self::DATAFEED_URL . '/' . $this->apiKey
                . '/language/en'
                . '/fq/aw_product_id:' . rawurlencode($productId)
                . '/columns/aw_product_id,product_name,merchant_name,search_price,currency,'
                . 'aw_deep_link,aw_image_url,merchant_image_url,description,category_name,'
                . 'brand_name,in_stock,data_feed_id'
                . '/format/json'
                . '/noresults/1';

            $response = wp_remote_get($url, [
                'timeout' => 30,
                'headers' => ['Accept' => 'application/json'],
            ]);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (is_array($data) && !empty($data[0])) {
                    $product = $this->parseProductData($data[0]);
                    if ($product) {
                        $this->productRepo->cacheProduct($product, $this->cacheDuration);
                        $this->trackUsage('GetProduct');
                        return $product;
                    }
                }
            }
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
                'message' => __('Awin API credentials are not configured.', 'wp-product-builder'),
            ];
        }

        // Test via publisher API — check if we can access programmes
        $url = self::PUBLISHER_API_URL . '/' . $this->publisherId . '/programmes'
            . '?relationship=joined&page=1&pageSize=1';

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);

        if ($statusCode < 400) {
            return [
                'success' => true,
                'message' => __('Awin connection successful!', 'wp-product-builder'),
            ];
        }

        // Fallback: try product data feed
        $result = $this->searchProducts('test', ['item_count' => 1]);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => __('Awin product feed connection successful!', 'wp-product-builder'),
            ];
        }

        return [
            'success' => false,
            'message' => $result['error'] ?? __('Awin connection failed.', 'wp-product-builder'),
        ];
    }

    /**
     * Parse Awin product data into normalized array
     *
     * @param array $item Raw product data from Awin
     * @return array|null Normalized product data
     */
    private function parseProductData(array $item): ?array {
        $productId = (string) ($item['aw_product_id'] ?? '');
        $title = $item['product_name'] ?? '';

        if (empty($productId) || empty($title)) {
            return null;
        }

        // Price handling
        $price = $item['search_price'] ?? '';
        $currency = $item['currency'] ?? 'GBP';
        $displayPrice = '';
        if ($price !== '') {
            $symbol = match ($currency) {
                'GBP' => '£',
                'EUR' => '€',
                'JPY' => '¥',
                default => '$',
            };
            $displayPrice = $symbol . number_format((float) $price, 2);
        }

        // Image: prefer Awin-hosted, fallback to merchant
        $imageUrl = $item['aw_image_url'] ?? ($item['merchant_image_url'] ?? '');

        // Features from description
        $description = $item['description'] ?? '';
        $features = [];
        if ($description) {
            $sentences = preg_split('/[.!]\s+/', strip_tags($description));
            $features = array_filter(array_map('trim', array_slice($sentences, 0, 5)));
        }

        // Availability
        $inStock = $item['in_stock'] ?? null;
        $availability = 'Unknown';
        if ($inStock === 1 || $inStock === '1' || $inStock === true) {
            $availability = 'In Stock';
        } elseif ($inStock === 0 || $inStock === '0' || $inStock === false) {
            $availability = 'Out of Stock';
        }

        return [
            'product_id' => $productId,
            'asin' => null,
            'network' => 'awin',
            'title' => $title,
            'brand' => $item['brand_name'] ?? ($item['merchant_name'] ?? ''),
            'price' => $displayPrice ?: null,
            'currency' => $currency,
            'availability' => $availability,
            'image_url' => $imageUrl,
            'affiliate_url' => $item['aw_deep_link'] ?? '',
            'rating' => null,
            'review_count' => null,
            'features' => array_values($features),
            'description' => mb_substr($description, 0, 2000),
            'category' => $item['category_name'] ?? '',
            'marketplace' => '',
            'merchant_name' => $item['merchant_name'] ?? '',
            'source' => 'awin_api',
            'fetched_at' => current_time('mysql'),
        ];
    }

    /**
     * Enforce rate limiting
     */
    private function enforceRateLimit(): void {
        $transient_key = 'wpb_rate_awin';
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
             VALUES (%d, 'awin', %s, 1, %s)
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
