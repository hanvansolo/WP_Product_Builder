<?php
/**
 * Amazon PA-API Client
 *
 * Wrapper for Amazon Product Advertising API 5.0
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\API;

use WPProductBuilder\Encryption\EncryptionService;
use WPProductBuilder\Database\Repositories\ProductRepository;
use WP_Error;

/**
 * Amazon PA-API client for product data
 */
class AmazonClient {
    /**
     * Marketplace configurations
     */
    private const MARKETPLACES = [
        'US' => [
            'host' => 'webservices.amazon.com',
            'region' => 'us-east-1',
            'domain' => 'amazon.com',
            'currency' => 'USD',
        ],
        'UK' => [
            'host' => 'webservices.amazon.co.uk',
            'region' => 'eu-west-1',
            'domain' => 'amazon.co.uk',
            'currency' => 'GBP',
        ],
        'DE' => [
            'host' => 'webservices.amazon.de',
            'region' => 'eu-west-1',
            'domain' => 'amazon.de',
            'currency' => 'EUR',
        ],
        'FR' => [
            'host' => 'webservices.amazon.fr',
            'region' => 'eu-west-1',
            'domain' => 'amazon.fr',
            'currency' => 'EUR',
        ],
        'CA' => [
            'host' => 'webservices.amazon.ca',
            'region' => 'us-east-1',
            'domain' => 'amazon.ca',
            'currency' => 'CAD',
        ],
        'JP' => [
            'host' => 'webservices.amazon.co.jp',
            'region' => 'us-west-2',
            'domain' => 'amazon.co.jp',
            'currency' => 'JPY',
        ],
    ];

    /**
     * Access key
     */
    private string $accessKey;

    /**
     * Secret key
     */
    private string $secretKey;

    /**
     * Partner tag
     */
    private string $partnerTag;

    /**
     * Marketplace
     */
    private string $marketplace;

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
     * @param string|null $accessKey Optional access key (for testing before save)
     * @param string|null $secretKey Optional secret key (for testing before save)
     * @param string|null $partnerTag Optional partner tag (for testing before save)
     */
    public function __construct(?string $accessKey = null, ?string $secretKey = null, ?string $partnerTag = null) {
        $settings = get_option('wpb_settings', []);

        if ($accessKey !== null || $secretKey !== null) {
            // Use provided credentials (for testing)
            $this->accessKey = $accessKey ?? '';
            $this->secretKey = $secretKey ?? '';
            $this->partnerTag = $partnerTag ?? '';
        } else {
            // Load from saved credentials
            $encryption = new EncryptionService();
            $credentials = get_option('wpb_credentials_encrypted', []);

            $this->accessKey = !empty($credentials['amazon_access_key'])
                ? $encryption->decrypt($credentials['amazon_access_key'])
                : '';

            $this->secretKey = !empty($credentials['amazon_secret_key'])
                ? $encryption->decrypt($credentials['amazon_secret_key'])
                : '';

            $this->partnerTag = $credentials['amazon_partner_tag'] ?? '';
        }

        $this->marketplace = $settings['amazon_marketplace'] ?? 'US';
        $this->cacheDuration = ($settings['cache_duration_hours'] ?? 24) * HOUR_IN_SECONDS;

        $this->productRepo = new ProductRepository();
    }

    /**
     * Search for products
     *
     * @param string $keywords Search keywords
     * @param array $options Search options
     * @return array Search results
     */
    public function searchProducts(string $keywords, array $options = []): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => __('Amazon PA-API is not configured.', 'wp-product-builder'),
            ];
        }

        $defaults = [
            'item_count' => 10,
            'search_index' => 'All',
        ];

        $params = array_merge($defaults, $options);

        $payload = [
            'Keywords' => $keywords,
            'ItemCount' => min($params['item_count'], 10),
            'SearchIndex' => $params['search_index'],
            'PartnerTag' => $this->partnerTag,
            'PartnerType' => 'Associates',
            'Resources' => $this->getDefaultResources(),
        ];

        $response = $this->makeRequest('SearchItems', $payload);

        if (!$response['success']) {
            return $response;
        }

        $products = [];
        if (!empty($response['data']['SearchResult']['Items'])) {
            foreach ($response['data']['SearchResult']['Items'] as $item) {
                $product = $this->parseProductData($item);
                $products[] = $product;

                // Cache the product
                $this->productRepo->cacheProduct($product, $this->cacheDuration);
            }
        }

        return [
            'success' => true,
            'products' => $products,
            'total_results' => $response['data']['SearchResult']['TotalResultCount'] ?? count($products),
        ];
    }

    /**
     * Get product by ASIN
     *
     * @param string $asin Product ASIN
     * @return array|null Product data or null if not found
     */
    public function getProduct(string $asin): ?array {
        // Check cache first
        $cached = $this->productRepo->getByAsin($asin, $this->marketplace);
        if ($cached && !$this->isExpired($cached['expires_at'])) {
            return json_decode($cached['product_data'], true);
        }

        // Fetch from API
        $response = $this->getMultipleProducts([$asin]);

        if (!empty($response['products'][$asin])) {
            return $response['products'][$asin];
        }

        // Return cached even if expired as fallback
        if ($cached) {
            return json_decode($cached['product_data'], true);
        }

        return null;
    }

    /**
     * Get multiple products by ASINs
     *
     * @param array $asins Array of ASINs
     * @return array Products data
     */
    public function getMultipleProducts(array $asins): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => __('Amazon PA-API is not configured.', 'wp-product-builder'),
                'products' => [],
            ];
        }

        $products = [];
        $toFetch = [];

        // Check cache for each
        foreach ($asins as $asin) {
            $cached = $this->productRepo->getByAsin($asin, $this->marketplace);
            if ($cached && !$this->isExpired($cached['expires_at'])) {
                $products[$asin] = json_decode($cached['product_data'], true);
            } else {
                $toFetch[] = $asin;
            }
        }

        // Fetch missing products (max 10 per request)
        if (!empty($toFetch)) {
            $chunks = array_chunk($toFetch, 10);

            foreach ($chunks as $chunk) {
                $payload = [
                    'ItemIds' => $chunk,
                    'PartnerTag' => $this->partnerTag,
                    'PartnerType' => 'Associates',
                    'Resources' => $this->getDefaultResources(),
                ];

                $response = $this->makeRequest('GetItems', $payload);

                if ($response['success'] && !empty($response['data']['ItemsResult']['Items'])) {
                    foreach ($response['data']['ItemsResult']['Items'] as $item) {
                        $product = $this->parseProductData($item);
                        $products[$product['asin']] = $product;

                        // Cache the product
                        $this->productRepo->cacheProduct($product, $this->cacheDuration);
                    }
                }
            }
        }

        return [
            'success' => true,
            'products' => $products,
        ];
    }

    /**
     * Generate affiliate link
     *
     * @param string $asin Product ASIN
     * @return string Affiliate URL
     */
    public function generateAffiliateLink(string $asin): string {
        $config = self::MARKETPLACES[$this->marketplace] ?? self::MARKETPLACES['US'];
        return "https://www.{$config['domain']}/dp/{$asin}?tag={$this->partnerTag}";
    }

    /**
     * Test API connection
     *
     * @return array Result with success status and message
     */
    public function testConnection(): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => __('Amazon PA-API credentials are not configured.', 'wp-product-builder'),
            ];
        }

        $payload = [
            'Keywords' => 'test',
            'ItemCount' => 1,
            'SearchIndex' => 'All',
            'PartnerTag' => $this->partnerTag,
            'PartnerType' => 'Associates',
            'Resources' => ['ItemInfo.Title'],
        ];

        $response = $this->makeRequest('SearchItems', $payload);

        if ($response['success']) {
            return [
                'success' => true,
                'message' => __('Connection successful!', 'wp-product-builder'),
            ];
        }

        return [
            'success' => false,
            'message' => $response['error'] ?? __('Connection failed.', 'wp-product-builder'),
        ];
    }

    /**
     * Make API request using WordPress HTTP API
     *
     * @param string $operation API operation
     * @param array $payload Request payload
     * @return array Response
     */
    private function makeRequest(string $operation, array $payload): array {
        $config = self::MARKETPLACES[$this->marketplace] ?? self::MARKETPLACES['US'];
        $host = $config['host'];
        $region = $config['region'];

        $path = '/paapi5/' . strtolower($operation);
        $target = "com.amazon.paapi5.v1.ProductAdvertisingAPIv1.{$operation}";

        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        $payloadJson = wp_json_encode($payload);

        // Create canonical request
        $canonicalHeaders = [
            'content-encoding' => 'amz-1.0',
            'content-type' => 'application/json; charset=utf-8',
            'host' => $host,
            'x-amz-date' => $timestamp,
            'x-amz-target' => $target,
        ];

        $signedHeaders = implode(';', array_keys($canonicalHeaders));
        $canonicalHeaderString = '';
        foreach ($canonicalHeaders as $key => $value) {
            $canonicalHeaderString .= "{$key}:{$value}\n";
        }

        $payloadHash = hash('sha256', $payloadJson);

        $canonicalRequest = implode("\n", [
            'POST',
            $path,
            '',
            $canonicalHeaderString,
            $signedHeaders,
            $payloadHash,
        ]);

        // Create string to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$date}/{$region}/ProductAdvertisingAPI/aws4_request";
        $stringToSign = implode("\n", [
            $algorithm,
            $timestamp,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        // Calculate signature
        $kSecret = 'AWS4' . $this->secretKey;
        $kDate = hash_hmac('sha256', $date, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 'ProductAdvertisingAPI', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        // Create authorization header
        $authHeader = "{$algorithm} Credential={$this->accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        // Make request using WordPress HTTP API
        $url = "https://{$host}{$path}";

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'content-encoding' => 'amz-1.0',
                'content-type' => 'application/json; charset=utf-8',
                'host' => $host,
                'x-amz-date' => $timestamp,
                'x-amz-target' => $target,
                'Authorization' => $authHeader,
            ],
            'body' => $payloadJson,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status_code >= 400) {
            $error_message = $this->parseErrorResponse($decoded, $status_code);
            return [
                'success' => false,
                'error' => $error_message,
            ];
        }

        // Track API usage
        $this->trackUsage($operation);

        return [
            'success' => true,
            'data' => $decoded,
        ];
    }

    /**
     * Parse product data from API response
     *
     * @param array $item Raw item data
     * @return array Parsed product data
     */
    private function parseProductData(array $item): array {
        $info = $item['ItemInfo'] ?? [];
        $offers = $item['Offers'] ?? [];
        $images = $item['Images'] ?? [];

        // Get price
        $price = null;
        $availability = 'Unknown';
        if (!empty($offers['Listings'])) {
            $listing = $offers['Listings'][0] ?? null;
            if ($listing) {
                $price = $listing['Price']['DisplayAmount'] ?? null;
                $availability = $listing['Availability']['Message'] ?? 'Unknown';
            }
        }

        // Get features
        $features = [];
        if (!empty($info['Features']['DisplayValues'])) {
            $features = $info['Features']['DisplayValues'];
        }

        return [
            'asin' => $item['ASIN'],
            'title' => $info['Title']['DisplayValue'] ?? '',
            'price' => $price,
            'currency' => self::MARKETPLACES[$this->marketplace]['currency'] ?? 'USD',
            'availability' => $availability,
            'image_url' => $images['Primary']['Large']['URL'] ?? ($images['Primary']['Medium']['URL'] ?? ''),
            'affiliate_url' => $item['DetailPageURL'] ?? $this->generateAffiliateLink($item['ASIN']),
            'rating' => null, // PA-API 5.0 doesn't return ratings
            'review_count' => null,
            'features' => $features,
            'brand' => $info['ByLineInfo']['Brand']['DisplayValue'] ?? null,
            'category' => $info['Classifications']['Binding']['DisplayValue'] ?? null,
            'marketplace' => $this->marketplace,
            'fetched_at' => current_time('mysql'),
        ];
    }

    /**
     * Get default resources for API requests
     *
     * @return array Resource list
     */
    private function getDefaultResources(): array {
        return [
            'ItemInfo.Title',
            'ItemInfo.Features',
            'ItemInfo.ByLineInfo',
            'ItemInfo.Classifications',
            'ItemInfo.ProductInfo',
            'Offers.Listings.Price',
            'Offers.Listings.Availability.Message',
            'Images.Primary.Large',
            'Images.Primary.Medium',
        ];
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
             VALUES (%d, 'amazon', %s, 1, %s)
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

    /**
     * Parse error from API response
     *
     * @param array|null $response Decoded response body
     * @param int $statusCode HTTP status code
     * @return string Error message
     */
    private function parseErrorResponse(?array $response, int $statusCode): string {
        if (!empty($response['Errors'])) {
            $error = $response['Errors'][0] ?? [];
            $message = $error['Message'] ?? __('API request failed', 'wp-product-builder');

            if (str_contains($message, 'InvalidSignature')) {
                return __('Invalid API signature. Please check your Amazon credentials.', 'wp-product-builder');
            }

            if (str_contains($message, 'TooManyRequests') || $statusCode === 429) {
                return __('Rate limit exceeded. Please try again later.', 'wp-product-builder');
            }

            return $message;
        }

        return sprintf(__('API request failed with status %d', 'wp-product-builder'), $statusCode);
    }

    /**
     * Check if API is configured
     *
     * @return bool
     */
    public function isConfigured(): bool {
        return !empty($this->accessKey) && !empty($this->secretKey) && !empty($this->partnerTag);
    }

    /**
     * Get available marketplaces
     *
     * @return array
     */
    public static function getMarketplaces(): array {
        return [
            'US' => 'United States (amazon.com)',
            'UK' => 'United Kingdom (amazon.co.uk)',
            'DE' => 'Germany (amazon.de)',
            'FR' => 'France (amazon.fr)',
            'CA' => 'Canada (amazon.ca)',
            'JP' => 'Japan (amazon.co.jp)',
        ];
    }
}
