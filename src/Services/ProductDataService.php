<?php
/**
 * Product Data Service
 *
 * Unified service for fetching product data from API or Scraper
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Services;

use WPProductBuilder\API\AmazonClient;
use WPProductBuilder\Scraper\AmazonScraper;
use WPProductBuilder\Database\Repositories\ProductRepository;

/**
 * Handles product data retrieval with fallback support
 */
class ProductDataService {
    /**
     * Data source modes
     */
    public const MODE_API_ONLY = 'api';
    public const MODE_SCRAPER_ONLY = 'scraper';
    public const MODE_AUTO = 'auto'; // Try API first, fallback to scraper

    /**
     * Amazon API client
     */
    private ?AmazonClient $apiClient = null;

    /**
     * Amazon scraper
     */
    private ?AmazonScraper $scraper = null;

    /**
     * Product repository
     */
    private ProductRepository $productRepo;

    /**
     * Current mode
     */
    private string $mode;

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option('wpb_settings', []);
        $this->mode = $settings['data_source_mode'] ?? self::MODE_AUTO;
        $this->productRepo = new ProductRepository();
    }

    /**
     * Get single product
     *
     * @param string $asin Product ASIN
     * @return array|null Product data or null
     */
    public function getProduct(string $asin): ?array {
        $asin = strtoupper(trim($asin));

        switch ($this->mode) {
            case self::MODE_API_ONLY:
                return $this->getFromApi($asin);

            case self::MODE_SCRAPER_ONLY:
                return $this->getFromScraper($asin);

            case self::MODE_AUTO:
            default:
                // Try API first if configured
                if ($this->isApiConfigured()) {
                    $product = $this->getFromApi($asin);
                    if ($product) {
                        return $product;
                    }
                }
                // Fallback to scraper
                return $this->getFromScraper($asin);
        }
    }

    /**
     * Get multiple products
     *
     * @param array $asins Array of ASINs
     * @return array Products data keyed by ASIN
     */
    public function getMultipleProducts(array $asins): array {
        $asins = array_map(fn($a) => strtoupper(trim($a)), $asins);
        $asins = array_filter($asins);

        switch ($this->mode) {
            case self::MODE_API_ONLY:
                return $this->getMultipleFromApi($asins);

            case self::MODE_SCRAPER_ONLY:
                return $this->getMultipleFromScraper($asins);

            case self::MODE_AUTO:
            default:
                $products = [];
                $remaining = $asins;

                // Try API first if configured
                if ($this->isApiConfigured()) {
                    $apiProducts = $this->getMultipleFromApi($asins);
                    $products = $apiProducts;
                    $remaining = array_diff($asins, array_keys($apiProducts));
                }

                // Scrape any missing products
                if (!empty($remaining)) {
                    $scraped = $this->getMultipleFromScraper($remaining);
                    $products = array_merge($products, $scraped);
                }

                return $products;
        }
    }

    /**
     * Search products
     *
     * @param string $query Search query
     * @param int $maxResults Maximum results
     * @return array Search results
     */
    public function searchProducts(string $query, int $maxResults = 10): array {
        switch ($this->mode) {
            case self::MODE_API_ONLY:
                return $this->searchFromApi($query, $maxResults);

            case self::MODE_SCRAPER_ONLY:
                return $this->searchFromScraper($query, $maxResults);

            case self::MODE_AUTO:
            default:
                // Try API first if configured
                if ($this->isApiConfigured()) {
                    $results = $this->searchFromApi($query, $maxResults);
                    if (!empty($results)) {
                        return $results;
                    }
                }
                // Fallback to scraper
                return $this->searchFromScraper($query, $maxResults);
        }
    }

    /**
     * Get product from API
     */
    private function getFromApi(string $asin): ?array {
        $client = $this->getApiClient();
        if (!$client || !$client->isConfigured()) {
            return null;
        }

        return $client->getProduct($asin);
    }

    /**
     * Get product from scraper
     */
    private function getFromScraper(string $asin): ?array {
        return $this->getScraper()->scrapeProduct($asin);
    }

    /**
     * Get multiple products from API
     */
    private function getMultipleFromApi(array $asins): array {
        $client = $this->getApiClient();
        if (!$client || !$client->isConfigured()) {
            return [];
        }

        $result = $client->getMultipleProducts($asins);
        return $result['products'] ?? [];
    }

    /**
     * Get multiple products from scraper
     */
    private function getMultipleFromScraper(array $asins): array {
        return $this->getScraper()->scrapeMultipleProducts($asins);
    }

    /**
     * Search from API
     */
    private function searchFromApi(string $query, int $maxResults): array {
        $client = $this->getApiClient();
        if (!$client || !$client->isConfigured()) {
            return [];
        }

        $result = $client->searchProducts($query, ['item_count' => $maxResults]);
        return $result['products'] ?? [];
    }

    /**
     * Search from scraper
     */
    private function searchFromScraper(string $query, int $maxResults): array {
        return $this->getScraper()->searchProducts($query, $maxResults);
    }

    /**
     * Check if API is configured
     */
    private function isApiConfigured(): bool {
        $credentials = get_option('wpb_credentials_encrypted', []);
        return !empty($credentials['amazon_access_key']) && !empty($credentials['amazon_secret_key']);
    }

    /**
     * Get API client (lazy load)
     */
    private function getApiClient(): ?AmazonClient {
        if ($this->apiClient === null) {
            $this->apiClient = new AmazonClient();
        }
        return $this->apiClient;
    }

    /**
     * Get scraper (lazy load)
     */
    private function getScraper(): AmazonScraper {
        if ($this->scraper === null) {
            $this->scraper = new AmazonScraper();
        }
        return $this->scraper;
    }

    /**
     * Test data sources
     */
    public function testConnections(): array {
        $results = [];

        // Test API
        if ($this->isApiConfigured()) {
            $client = $this->getApiClient();
            $results['api'] = $client->testConnection();
        } else {
            $results['api'] = [
                'success' => false,
                'message' => __('API not configured', 'wp-product-builder'),
            ];
        }

        // Test Scraper
        $results['scraper'] = $this->getScraper()->testConnection();

        return $results;
    }

    /**
     * Get current mode
     */
    public function getMode(): string {
        return $this->mode;
    }

    /**
     * Set mode
     */
    public function setMode(string $mode): void {
        if (in_array($mode, [self::MODE_API_ONLY, self::MODE_SCRAPER_ONLY, self::MODE_AUTO])) {
            $this->mode = $mode;
        }
    }

    /**
     * Get status info
     */
    public function getStatus(): array {
        return [
            'mode' => $this->mode,
            'api_configured' => $this->isApiConfigured(),
            'scraper_available' => true,
            'recommended_mode' => $this->isApiConfigured() ? self::MODE_AUTO : self::MODE_SCRAPER_ONLY,
        ];
    }
}
