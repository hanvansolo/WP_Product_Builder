<?php
/**
 * Product Data Service
 *
 * Unified service for fetching product data from multiple affiliate networks
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Services;

use WPProductBuilder\API\AmazonClient;
use WPProductBuilder\API\CJClient;
use WPProductBuilder\API\AwinClient;
use WPProductBuilder\API\ProductNetworkInterface;
use WPProductBuilder\Scraper\AmazonScraper;
use WPProductBuilder\Database\Repositories\ProductRepository;

/**
 * Handles product data retrieval with fallback support across networks
 */
class ProductDataService {
    /**
     * Data source modes (Amazon-specific)
     */
    public const MODE_API_ONLY = 'api';
    public const MODE_SCRAPER_ONLY = 'scraper';
    public const MODE_AUTO = 'auto';

    /**
     * Supported networks
     */
    public const NETWORKS = ['amazon', 'cj', 'awin'];

    /**
     * Amazon API client
     */
    private ?AmazonClient $apiClient = null;

    /**
     * Amazon scraper
     */
    private ?AmazonScraper $scraper = null;

    /**
     * CJ Affiliate client
     */
    private ?CJClient $cjClient = null;

    /**
     * Awin client
     */
    private ?AwinClient $awinClient = null;

    /**
     * Product repository
     */
    private ProductRepository $productRepo;

    /**
     * Current mode (Amazon data source mode)
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
     * @param string $productId Product identifier (ASIN for Amazon, network-specific ID for others)
     * @param string $network Network name (amazon, cj, awin)
     * @return array|null Product data or null
     */
    public function getProduct(string $productId, string $network = 'amazon'): ?array {
        if ($network === 'amazon') {
            return $this->getAmazonProduct($productId);
        }

        $client = $this->getNetworkClient($network);
        if (!$client || !$client->isConfigured()) {
            return null;
        }

        return $client->getProduct($productId);
    }

    /**
     * Get multiple products
     *
     * @param array $productIds Array of product identifiers
     * @param string $network Network name
     * @return array Products data keyed by product ID
     */
    public function getMultipleProducts(array $productIds, string $network = 'amazon'): array {
        $productIds = array_map('trim', $productIds);
        $productIds = array_filter($productIds);

        if ($network === 'amazon') {
            return $this->getMultipleAmazonProducts($productIds);
        }

        $client = $this->getNetworkClient($network);
        if (!$client || !$client->isConfigured()) {
            return [];
        }

        $result = $client->getMultipleProducts($productIds);
        return $result['products'] ?? [];
    }

    /**
     * Search products
     *
     * @param string $query Search query
     * @param int $maxResults Maximum results
     * @param string $network Network to search
     * @return array Search results
     */
    public function searchProducts(string $query, int $maxResults = 10, string $network = 'amazon'): array {
        if ($network === 'amazon') {
            return $this->searchAmazonProducts($query, $maxResults);
        }

        $client = $this->getNetworkClient($network);
        if (!$client || !$client->isConfigured()) {
            return [];
        }

        $result = $client->searchProducts($query, ['item_count' => $maxResults]);
        return $result['products'] ?? [];
    }

    /**
     * Get Amazon product with mode-based fallback
     */
    private function getAmazonProduct(string $asin): ?array {
        $asin = strtoupper(trim($asin));

        switch ($this->mode) {
            case self::MODE_API_ONLY:
                return $this->getFromApi($asin);

            case self::MODE_SCRAPER_ONLY:
                return $this->getFromScraper($asin);

            case self::MODE_AUTO:
            default:
                if ($this->isApiConfigured()) {
                    $product = $this->getFromApi($asin);
                    if ($product) {
                        return $product;
                    }
                }
                return $this->getFromScraper($asin);
        }
    }

    /**
     * Get multiple Amazon products with mode-based fallback
     */
    private function getMultipleAmazonProducts(array $asins): array {
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

                if ($this->isApiConfigured()) {
                    $apiProducts = $this->getMultipleFromApi($asins);
                    $products = $apiProducts;
                    $remaining = array_diff($asins, array_keys($apiProducts));
                }

                if (!empty($remaining)) {
                    $scraped = $this->getMultipleFromScraper($remaining);
                    $products = array_merge($products, $scraped);
                }

                return $products;
        }
    }

    /**
     * Search Amazon products with mode-based fallback
     */
    private function searchAmazonProducts(string $query, int $maxResults): array {
        switch ($this->mode) {
            case self::MODE_API_ONLY:
                return $this->searchFromApi($query, $maxResults);

            case self::MODE_SCRAPER_ONLY:
                return $this->searchFromScraper($query, $maxResults);

            case self::MODE_AUTO:
            default:
                if ($this->isApiConfigured()) {
                    $results = $this->searchFromApi($query, $maxResults);
                    if (!empty($results)) {
                        return $results;
                    }
                }
                return $this->searchFromScraper($query, $maxResults);
        }
    }

    /**
     * Get product from Amazon API
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
     * Get multiple products from Amazon API
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
     * Search from Amazon API
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
     * Check if Amazon API is configured
     */
    private function isApiConfigured(): bool {
        $credentials = get_option('wpb_credentials_encrypted', []);
        return !empty($credentials['amazon_access_key']) && !empty($credentials['amazon_secret_key']);
    }

    /**
     * Get a network client by name
     *
     * @param string $network Network name
     * @return ProductNetworkInterface|null
     */
    private function getNetworkClient(string $network): ?ProductNetworkInterface {
        return match ($network) {
            'amazon' => $this->getApiClient(),
            'cj' => $this->getCJClient(),
            'awin' => $this->getAwinClient(),
            default => null,
        };
    }

    /**
     * Get Amazon API client (lazy load)
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
     * Get CJ client (lazy load)
     */
    private function getCJClient(): ?CJClient {
        if ($this->cjClient === null) {
            $this->cjClient = new CJClient();
        }
        return $this->cjClient;
    }

    /**
     * Get Awin client (lazy load)
     */
    private function getAwinClient(): ?AwinClient {
        if ($this->awinClient === null) {
            $this->awinClient = new AwinClient();
        }
        return $this->awinClient;
    }

    /**
     * Get list of configured networks
     *
     * @return array Network info arrays
     */
    public function getConfiguredNetworks(): array {
        $networks = [];

        // Amazon — always available (scraper doesn't need credentials)
        $networks[] = [
            'name' => 'amazon',
            'label' => 'Amazon',
            'configured' => true,
            'has_api' => $this->isApiConfigured(),
        ];

        $cj = $this->getCJClient();
        if ($cj && $cj->isConfigured()) {
            $networks[] = [
                'name' => 'cj',
                'label' => 'CJ Affiliate',
                'configured' => true,
                'has_api' => true,
            ];
        }

        $awin = $this->getAwinClient();
        if ($awin && $awin->isConfigured()) {
            $networks[] = [
                'name' => 'awin',
                'label' => 'Awin',
                'configured' => true,
                'has_api' => true,
            ];
        }

        return $networks;
    }

    /**
     * Test data sources across all configured networks
     */
    public function testConnections(): array {
        $results = [];

        // Amazon API
        if ($this->isApiConfigured()) {
            $client = $this->getApiClient();
            $results['amazon_api'] = $client->testConnection();
        } else {
            $results['amazon_api'] = [
                'success' => false,
                'message' => __('Amazon API not configured', 'wp-product-builder'),
            ];
        }

        // Amazon Scraper
        $results['amazon_scraper'] = $this->getScraper()->testConnection();

        // CJ
        $cj = $this->getCJClient();
        if ($cj->isConfigured()) {
            $results['cj'] = $cj->testConnection();
        }

        // Awin
        $awin = $this->getAwinClient();
        if ($awin->isConfigured()) {
            $results['awin'] = $awin->testConnection();
        }

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
            'cj_configured' => $this->getCJClient()->isConfigured(),
            'awin_configured' => $this->getAwinClient()->isConfigured(),
            'configured_networks' => $this->getConfiguredNetworks(),
            'recommended_mode' => $this->isApiConfigured() ? self::MODE_AUTO : self::MODE_SCRAPER_ONLY,
        ];
    }
}
