<?php
/**
 * Product Repository
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Database\Repositories;

/**
 * Repository for product cache operations
 */
class ProductRepository {
    /**
     * Table name
     */
    private string $table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wpb_product_cache';
    }

    /**
     * Get product by ASIN
     *
     * @param string $asin Product ASIN
     * @param string $marketplace Marketplace code
     * @return array|null
     */
    public function getByAsin(string $asin, string $marketplace = 'US'): ?array {
        global $wpdb;

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE asin = %s AND marketplace = %s",
            $asin,
            $marketplace
        ), ARRAY_A);

        return $result ?: null;
    }

    /**
     * Cache product data
     *
     * @param array $product Product data
     * @param int $ttl Time to live in seconds
     * @return bool
     */
    public function cacheProduct(array $product, int $ttl = 86400): bool {
        global $wpdb;

        $now = current_time('mysql');
        $expires = date('Y-m-d H:i:s', time() + $ttl);

        $data = [
            'asin' => $product['asin'],
            'marketplace' => $product['marketplace'] ?? 'US',
            'product_data' => json_encode($product),
            'title' => $product['title'] ?? null,
            'price' => $product['price'] ?? null,
            'currency' => $product['currency'] ?? null,
            'availability' => $product['availability'] ?? null,
            'image_url' => $product['image_url'] ?? null,
            'affiliate_url' => $product['affiliate_url'] ?? null,
            'rating' => $product['rating'] ?? null,
            'review_count' => $product['review_count'] ?? null,
            'last_fetched' => $now,
            'expires_at' => $expires,
        ];

        // Check if exists
        $existing = $this->getByAsin($product['asin'], $product['marketplace'] ?? 'US');

        if ($existing) {
            // Update
            $result = $wpdb->update(
                $this->table,
                $data,
                [
                    'asin' => $product['asin'],
                    'marketplace' => $product['marketplace'] ?? 'US',
                ]
            );
        } else {
            // Insert
            $result = $wpdb->insert($this->table, $data);
        }

        return $result !== false;
    }

    /**
     * Get multiple products by ASINs
     *
     * @param array $asins Array of ASINs
     * @param string $marketplace Marketplace code
     * @return array
     */
    public function getMultipleByAsins(array $asins, string $marketplace = 'US'): array {
        global $wpdb;

        if (empty($asins)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($asins), '%s'));
        $params = array_merge($asins, [$marketplace]);

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE asin IN ({$placeholders}) AND marketplace = %s",
            ...$params
        ), ARRAY_A);

        $products = [];
        foreach ($results as $row) {
            $products[$row['asin']] = $row;
        }

        return $products;
    }

    /**
     * Delete expired cache entries
     *
     * @return int Number of deleted rows
     */
    public function deleteExpired(): int {
        global $wpdb;

        return $wpdb->query(
            "DELETE FROM {$this->table} WHERE expires_at < NOW()"
        );
    }

    /**
     * Clear all cache
     *
     * @return int Number of deleted rows
     */
    public function clearAll(): int {
        global $wpdb;

        return $wpdb->query("TRUNCATE TABLE {$this->table}");
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function getStats(): array {
        global $wpdb;

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
        $expired = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE expires_at < NOW()");
        $valid = $total - $expired;

        return [
            'total' => (int) $total,
            'valid' => (int) $valid,
            'expired' => (int) $expired,
        ];
    }

    /**
     * Search cached products
     *
     * @param string $search Search term
     * @param int $limit Limit
     * @return array
     */
    public function search(string $search, int $limit = 20): array {
        global $wpdb;

        $search = '%' . $wpdb->esc_like($search) . '%';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE title LIKE %s AND expires_at > NOW()
             ORDER BY last_fetched DESC
             LIMIT %d",
            $search,
            $limit
        ), ARRAY_A);
    }
}
