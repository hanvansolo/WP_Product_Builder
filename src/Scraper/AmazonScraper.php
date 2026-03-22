<?php
/**
 * Amazon Product Scraper
 *
 * Scrapes product data from Amazon without requiring PA-API access.
 * Useful for new affiliates who haven't yet qualified for API access.
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Scraper;

use WPProductBuilder\Database\Repositories\ProductRepository;

/**
 * Scrapes Amazon product data without API
 */
class AmazonScraper {
    /**
     * Product repository
     */
    private ProductRepository $productRepo;

    /**
     * Current marketplace
     */
    private string $marketplace;

    /**
     * Partner tag
     */
    private string $partnerTag;

    /**
     * Rate limit delay in seconds
     */
    private int $rateLimit;

    /**
     * Last request timestamp
     */
    private static float $lastRequest = 0;

    /**
     * User agents for rotation
     */
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
    ];

    /**
     * Marketplace domains
     */
    private const DOMAINS = [
        'US' => 'amazon.com',
        'UK' => 'amazon.co.uk',
        'DE' => 'amazon.de',
        'FR' => 'amazon.fr',
        'CA' => 'amazon.ca',
        'JP' => 'amazon.co.jp',
        'IT' => 'amazon.it',
        'ES' => 'amazon.es',
        'AU' => 'amazon.com.au',
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option('wpb_settings', []);
        $credentials = get_option('wpb_credentials_encrypted', []);

        $this->marketplace = $settings['amazon_marketplace'] ?? 'US';
        $this->partnerTag = $credentials['amazon_partner_tag'] ?? '';
        $this->rateLimit = $settings['scraper_rate_limit'] ?? 3; // seconds between requests

        $this->productRepo = new ProductRepository();
    }

    /**
     * Scrape product by ASIN
     *
     * @param string $asin Product ASIN
     * @param bool $useCache Whether to check cache first
     * @return array|null Product data or null on failure
     */
    public function scrapeProduct(string $asin, bool $useCache = true): ?array {
        $asin = strtoupper(trim($asin));

        if (!$this->isValidAsin($asin)) {
            return null;
        }

        // Check cache first
        if ($useCache) {
            $cached = $this->productRepo->getByAsin($asin, $this->marketplace);
            if ($cached && strtotime($cached['expires_at']) > time()) {
                return json_decode($cached['product_data'], true);
            }
        }

        // Rate limiting
        $this->enforceRateLimit();

        $url = $this->buildProductUrl($asin);

        try {
            $html = $this->fetchPage($url);

            if (!$html) {
                return null;
            }

            $product = $this->parseProductPage($html, $asin);

            if ($product) {
                // Cache the product
                $cacheDuration = (get_option('wpb_settings')['cache_duration_hours'] ?? 24) * HOUR_IN_SECONDS;
                $this->productRepo->cacheProduct($product, $cacheDuration);
            }

            return $product;

        } catch (\Exception $e) {
            error_log('WPB Scraper Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Scrape multiple products
     *
     * @param array $asins Array of ASINs
     * @return array Products data
     */
    public function scrapeMultipleProducts(array $asins): array {
        $products = [];
        $toScrape = [];

        // Check cache first
        foreach ($asins as $asin) {
            $asin = strtoupper(trim($asin));
            if (!$this->isValidAsin($asin)) {
                continue;
            }

            $cached = $this->productRepo->getByAsin($asin, $this->marketplace);
            if ($cached && strtotime($cached['expires_at']) > time()) {
                $products[$asin] = json_decode($cached['product_data'], true);
            } else {
                $toScrape[] = $asin;
            }
        }

        // Scrape missing products
        foreach ($toScrape as $asin) {
            $product = $this->scrapeProduct($asin, false);
            if ($product) {
                $products[$asin] = $product;
            }
        }

        return $products;
    }

    /**
     * Search products on Amazon
     *
     * @param string $query Search query
     * @param int $maxResults Maximum results to return
     * @return array Search results
     */
    public function searchProducts(string $query, int $maxResults = 10): array {
        $this->enforceRateLimit();

        // Try Amazon direct search first
        $url = $this->buildSearchUrl($query);

        try {
            $html = $this->fetchPage($url);

            if ($html && !str_contains($html, 'captcha') && !str_contains($html, 'robot')) {
                $results = $this->parseSearchResults($html, $maxResults);
                if (!empty($results)) {
                    return $results;
                }
            }

            // Fallback: search via Google to find Amazon product ASINs
            error_log('WPB Scraper: Amazon direct search blocked, trying Google fallback');
            return $this->searchViaGoogle($query, $maxResults);

        } catch (\Exception $e) {
            error_log('WPB Scraper Search Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Search for Amazon products via Google as fallback
     *
     * @param string $query Search query
     * @param int $maxResults Maximum results
     * @return array Products found
     */
    private function searchViaGoogle(string $query, int $maxResults = 10): array {
        $domain = self::DOMAINS[$this->marketplace] ?? 'amazon.com';
        $googleUrl = 'https://www.google.com/search?q=' . urlencode("site:{$domain} {$query}") . '&num=' . min($maxResults * 2, 20);

        $html = $this->fetchPage($googleUrl);
        if (!$html) {
            return [];
        }

        // Extract Amazon ASINs from Google results
        $asins = [];
        preg_match_all('/\/dp\/([A-Z0-9]{10})/', $html, $matches);
        if (!empty($matches[1])) {
            $asins = array_unique($matches[1]);
        }

        // Also try /gp/product/ URLs
        preg_match_all('/\/gp\/product\/([A-Z0-9]{10})/', $html, $matches2);
        if (!empty($matches2[1])) {
            $asins = array_unique(array_merge($asins, $matches2[1]));
        }

        if (empty($asins)) {
            return [];
        }

        // Scrape each product by ASIN (much more reliable than search page)
        $asins = array_slice($asins, 0, $maxResults);
        $products = [];

        foreach ($asins as $asin) {
            $product = $this->scrapeProduct($asin);
            if ($product) {
                $products[] = $product;
            }

            if (count($products) >= $maxResults) {
                break;
            }
        }

        return $products;
    }

    /**
     * Fetch page content using WordPress HTTP API
     *
     * @param string $url URL to fetch
     * @return string|null HTML content or null
     */
    private function fetchPage(string $url): ?string {
        $headers = [
            'User-Agent' => $this->getRandomUserAgent(),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'DNT' => '1',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Cache-Control' => 'max-age=0',
        ];

        $args = [
            'timeout' => 30,
            'headers' => $headers,
            'sslverify' => true,
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            error_log('WPB Fetch Error: ' . $response->get_error_message());
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log("WPB Fetch Error: HTTP {$status_code}");
            return null;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Parse product page HTML
     *
     * @param string $html HTML content
     * @param string $asin Product ASIN
     * @return array|null Parsed product data
     */
    private function parseProductPage(string $html, string $asin): ?array {
        // Suppress DOM errors
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new \DOMXPath($dom);

        // Extract title
        $title = $this->extractText($xpath, [
            '//span[@id="productTitle"]',
            '//h1[@id="title"]//span',
            '//h1[contains(@class, "product-title")]',
        ]);

        if (empty($title)) {
            return null; // Invalid product page
        }

        // Extract price
        $price = $this->extractPrice($xpath);

        // Extract images
        $imageUrl = $this->extractMainImage($xpath, $html);

        // Extract features/bullet points
        $features = $this->extractFeatures($xpath);

        // Extract brand
        $brand = $this->extractBrand($xpath);

        // Extract rating
        $rating = $this->extractRating($xpath);

        // Extract review count
        $reviewCount = $this->extractReviewCount($xpath);

        // Extract availability
        $availability = $this->extractAvailability($xpath);

        // Extract description
        $description = $this->extractDescription($xpath);

        // Extract category
        $category = $this->extractCategory($xpath);

        libxml_clear_errors();

        return [
            'product_id' => $asin,
            'asin' => $asin,
            'network' => 'amazon',
            'title' => trim($title),
            'price' => $price,
            'currency' => $this->getCurrency(),
            'availability' => $availability,
            'image_url' => $imageUrl,
            'affiliate_url' => $this->buildAffiliateUrl($asin),
            'rating' => $rating,
            'review_count' => $reviewCount,
            'features' => $features,
            'brand' => $brand,
            'category' => $category,
            'description' => $description,
            'marketplace' => $this->marketplace,
            'merchant_name' => null,
            'fetched_at' => current_time('mysql'),
            'source' => 'amazon_scraper',
        ];
    }

    /**
     * Parse search results page
     *
     * @param string $html HTML content
     * @param int $maxResults Maximum results
     * @return array Products found
     */
    private function parseSearchResults(string $html, int $maxResults): array {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new \DOMXPath($dom);

        $products = [];

        // Find search result items
        $items = $xpath->query('//div[@data-asin][@data-component-type="s-search-result"]');

        $count = 0;
        foreach ($items as $item) {
            if ($count >= $maxResults) {
                break;
            }

            $asin = $item->getAttribute('data-asin');
            if (empty($asin)) {
                continue;
            }

            // Create mini-xpath for this item
            $itemXpath = new \DOMXPath($dom);

            // Extract title
            $titleNode = $itemXpath->query('.//h2//span', $item)->item(0);
            $title = $titleNode ? trim($titleNode->textContent) : '';

            if (empty($title)) {
                continue;
            }

            // Extract price
            $priceNode = $itemXpath->query('.//span[@class="a-price"]//span[@class="a-offscreen"]', $item)->item(0);
            $price = $priceNode ? trim($priceNode->textContent) : null;

            // Extract image
            $imageNode = $itemXpath->query('.//img[@class="s-image"]', $item)->item(0);
            $imageUrl = $imageNode ? $imageNode->getAttribute('src') : '';

            // Extract rating
            $ratingNode = $itemXpath->query('.//span[contains(@class, "a-icon-alt")]', $item)->item(0);
            $ratingText = $ratingNode ? $ratingNode->textContent : '';
            preg_match('/([0-9.]+)/', $ratingText, $matches);
            $rating = $matches[1] ?? null;

            $products[] = [
                'product_id' => $asin,
                'asin' => $asin,
                'network' => 'amazon',
                'title' => $title,
                'price' => $price,
                'image_url' => $imageUrl,
                'rating' => $rating ? (float) $rating : null,
                'affiliate_url' => $this->buildAffiliateUrl($asin),
                'marketplace' => $this->marketplace,
                'merchant_name' => null,
                'source' => 'amazon_scraper',
            ];

            $count++;
        }

        libxml_clear_errors();

        return $products;
    }

    /**
     * Extract text from multiple possible XPath selectors
     */
    private function extractText(\DOMXPath $xpath, array $selectors): string {
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                return trim($nodes->item(0)->textContent);
            }
        }
        return '';
    }

    /**
     * Extract price from page
     */
    private function extractPrice(\DOMXPath $xpath): ?string {
        $selectors = [
            '//span[@id="priceblock_ourprice"]',
            '//span[@id="priceblock_dealprice"]',
            '//span[@id="priceblock_saleprice"]',
            '//span[contains(@class, "a-price")]//span[@class="a-offscreen"]',
            '//span[@class="a-price-whole"]',
            '//div[@id="corePrice_feature_div"]//span[@class="a-offscreen"]',
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $price = trim($nodes->item(0)->textContent);
                if (!empty($price)) {
                    return $price;
                }
            }
        }

        return null;
    }

    /**
     * Extract main product image
     */
    private function extractMainImage(\DOMXPath $xpath, string $html): string {
        // Try to get from image gallery data
        if (preg_match('/"hiRes":"([^"]+)"/', $html, $matches)) {
            return $matches[1];
        }

        if (preg_match('/"large":"([^"]+)"/', $html, $matches)) {
            return $matches[1];
        }

        // Fallback to DOM
        $selectors = [
            '//img[@id="landingImage"]',
            '//img[@id="imgBlkFront"]',
            '//img[@id="ebooksImgBlkFront"]',
            '//div[@id="imgTagWrapperId"]//img',
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $img = $nodes->item(0);
                // Try data-old-hires first, then src
                $src = $img->getAttribute('data-old-hires') ?: $img->getAttribute('src');
                if (!empty($src) && !str_contains($src, 'data:image')) {
                    return $src;
                }
            }
        }

        return '';
    }

    /**
     * Extract product features/bullet points
     */
    private function extractFeatures(\DOMXPath $xpath): array {
        $features = [];

        $nodes = $xpath->query('//div[@id="feature-bullets"]//li//span[@class="a-list-item"]');

        foreach ($nodes as $node) {
            $text = trim($node->textContent);
            if (!empty($text) && strlen($text) > 5) {
                $features[] = $text;
            }
        }

        return array_slice($features, 0, 10); // Max 10 features
    }

    /**
     * Extract brand name
     */
    private function extractBrand(\DOMXPath $xpath): ?string {
        $selectors = [
            '//a[@id="bylineInfo"]',
            '//div[@id="bylineInfo_feature_div"]//a',
            '//tr[th[contains(text(), "Brand")]]//td',
        ];

        $brand = $this->extractText($xpath, $selectors);

        // Clean up "Visit the X Store" text
        $brand = preg_replace('/^(Visit the |Brand: )/', '', $brand);
        $brand = preg_replace('/ Store$/', '', $brand);

        return $brand ?: null;
    }

    /**
     * Extract rating
     */
    private function extractRating(\DOMXPath $xpath): ?float {
        $nodes = $xpath->query('//span[@id="acrPopover"]/@title');
        if ($nodes->length > 0) {
            preg_match('/([0-9.]+)/', $nodes->item(0)->textContent, $matches);
            return isset($matches[1]) ? (float) $matches[1] : null;
        }

        $nodes = $xpath->query('//span[contains(@class, "a-icon-alt")]');
        foreach ($nodes as $node) {
            $text = $node->textContent;
            if (preg_match('/([0-9.]+) out of/', $text, $matches)) {
                return (float) $matches[1];
            }
        }

        return null;
    }

    /**
     * Extract review count
     */
    private function extractReviewCount(\DOMXPath $xpath): ?int {
        $nodes = $xpath->query('//span[@id="acrCustomerReviewText"]');
        if ($nodes->length > 0) {
            preg_match('/([0-9,]+)/', $nodes->item(0)->textContent, $matches);
            return isset($matches[1]) ? (int) str_replace(',', '', $matches[1]) : null;
        }
        return null;
    }

    /**
     * Extract availability
     */
    private function extractAvailability(\DOMXPath $xpath): string {
        $selectors = [
            '//div[@id="availability"]//span',
            '//span[@class="a-color-success"]',
            '//span[@class="a-color-price"]',
        ];

        $availability = $this->extractText($xpath, $selectors);
        return $availability ?: 'Check Availability';
    }

    /**
     * Extract product description
     */
    private function extractDescription(\DOMXPath $xpath): string {
        $selectors = [
            '//div[@id="productDescription"]//p',
            '//div[@id="aplus"]',
        ];

        $description = $this->extractText($xpath, $selectors);
        return substr($description, 0, 2000); // Limit length
    }

    /**
     * Extract category/breadcrumb
     */
    private function extractCategory(\DOMXPath $xpath): ?string {
        $nodes = $xpath->query('//div[@id="wayfinding-breadcrumbs_feature_div"]//a');
        if ($nodes->length > 0) {
            // Get last category in breadcrumb
            return trim($nodes->item($nodes->length - 1)->textContent);
        }
        return null;
    }

    /**
     * Build product URL
     */
    private function buildProductUrl(string $asin): string {
        $domain = self::DOMAINS[$this->marketplace] ?? 'amazon.com';
        return "https://www.{$domain}/dp/{$asin}";
    }

    /**
     * Build search URL
     */
    private function buildSearchUrl(string $query): string {
        $domain = self::DOMAINS[$this->marketplace] ?? 'amazon.com';
        return "https://www.{$domain}/s?k=" . urlencode($query);
    }

    /**
     * Build affiliate URL
     */
    private function buildAffiliateUrl(string $asin): string {
        $domain = self::DOMAINS[$this->marketplace] ?? 'amazon.com';
        $url = "https://www.{$domain}/dp/{$asin}";

        if (!empty($this->partnerTag)) {
            $url .= "?tag={$this->partnerTag}";
        }

        return $url;
    }

    /**
     * Get currency for marketplace
     */
    private function getCurrency(): string {
        $currencies = [
            'US' => 'USD', 'UK' => 'GBP', 'DE' => 'EUR',
            'FR' => 'EUR', 'CA' => 'CAD', 'JP' => 'JPY',
            'IT' => 'EUR', 'ES' => 'EUR', 'AU' => 'AUD',
        ];
        return $currencies[$this->marketplace] ?? 'USD';
    }

    /**
     * Validate ASIN format
     */
    private function isValidAsin(string $asin): bool {
        return (bool) preg_match('/^[A-Z0-9]{10}$/', $asin);
    }

    /**
     * Get random user agent
     */
    private function getRandomUserAgent(): string {
        return self::USER_AGENTS[array_rand(self::USER_AGENTS)];
    }

    /**
     * Enforce rate limiting
     */
    private function enforceRateLimit(): void {
        $elapsed = microtime(true) - self::$lastRequest;
        $waitTime = $this->rateLimit - $elapsed;

        if ($waitTime > 0) {
            usleep((int) ($waitTime * 1000000));
        }

        self::$lastRequest = microtime(true);
    }

    /**
     * Test scraper functionality
     */
    public function testConnection(): array {
        $testAsin = 'B08N5WRWNW'; // Common test ASIN

        try {
            $product = $this->scrapeProduct($testAsin);

            if ($product && !empty($product['title'])) {
                return [
                    'success' => true,
                    'message' => __('Scraper is working correctly.', 'wp-product-builder'),
                ];
            }

            return [
                'success' => false,
                'message' => __('Could not scrape test product. Amazon may be blocking requests.', 'wp-product-builder'),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
