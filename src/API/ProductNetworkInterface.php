<?php
/**
 * Product Network Interface
 *
 * Contract for affiliate network API clients
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\API;

/**
 * Interface that all affiliate network clients must implement
 */
interface ProductNetworkInterface {
    /**
     * Get the network identifier
     *
     * @return string e.g. 'amazon', 'cj', 'awin'
     */
    public function getNetworkName(): string;

    /**
     * Get the human-readable network label
     *
     * @return string e.g. 'Amazon', 'CJ Affiliate', 'Awin'
     */
    public function getNetworkLabel(): string;

    /**
     * Check if the network client has valid credentials configured
     *
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * Search for products by keyword
     *
     * @param string $keywords Search query
     * @param array $options Optional search parameters (e.g. item_count)
     * @return array ['success' => bool, 'products' => array, 'total_results' => int]
     */
    public function searchProducts(string $keywords, array $options = []): array;

    /**
     * Get a single product by its network-specific ID
     *
     * @param string $productId Network-specific product identifier
     * @return array|null Normalized product data or null if not found
     */
    public function getProduct(string $productId): ?array;

    /**
     * Get multiple products by their network-specific IDs
     *
     * @param array $productIds Array of network-specific product identifiers
     * @return array ['success' => bool, 'products' => array keyed by product ID]
     */
    public function getMultipleProducts(array $productIds): array;

    /**
     * Test the API connection with current credentials
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(): array;
}
