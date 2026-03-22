<?php
/**
 * Product Search REST API Endpoint
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\API\Endpoints;

use WPProductBuilder\Services\ProductDataService;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API controller for product operations
 */
class ProductSearchEndpoint extends WP_REST_Controller {
    /**
     * Namespace
     */
    protected $namespace = 'wp-product-builder/v1';

    /**
     * Resource name
     */
    protected $rest_base = 'products';

    /**
     * Register routes
     */
    public function register_routes(): void {
        $network_arg = [
            'required' => false,
            'type' => 'string',
            'default' => 'amazon',
            'enum' => ['amazon', 'cj', 'awin'],
            'sanitize_callback' => 'sanitize_text_field',
        ];

        // Search products
        register_rest_route($this->namespace, '/' . $this->rest_base . '/search', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'search_products'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => [
                    'q' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'description' => __('Search query', 'wp-product-builder'),
                    ],
                    'count' => [
                        'required' => false,
                        'type' => 'integer',
                        'default' => 10,
                        'minimum' => 1,
                        'maximum' => 10,
                    ],
                    'network' => $network_arg,
                ],
            ],
        ]);

        // Get single product (ASIN route kept for backward compat)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<asin>[A-Z0-9]{10})', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_product'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => [
                    'asin' => [
                        'required' => true,
                        'type' => 'string',
                        'validate_callback' => function($param) {
                            return preg_match('/^[A-Z0-9]{10}$/', strtoupper($param));
                        },
                    ],
                    'network' => $network_arg,
                ],
            ],
        ]);

        // Get single product by generic ID (for CJ/Awin)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/get', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_product_by_id'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => [
                    'product_id' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'network' => $network_arg,
                ],
            ],
        ]);

        // Get multiple products
        register_rest_route($this->namespace, '/' . $this->rest_base . '/batch', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'get_multiple_products'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => [
                    'asins' => [
                        'required' => false,
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'product_ids' => [
                        'required' => false,
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'network' => $network_arg,
                ],
            ],
        ]);
    }

    /**
     * Check permissions
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_permissions(WP_REST_Request $request) {
        if (!current_user_can('wpb_generate_content')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access this resource.', 'wp-product-builder'),
                ['status' => 403]
            );
        }
        return true;
    }

    /**
     * Search products
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function search_products(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $query = $request->get_param('q');
        $count = $request->get_param('count') ?? 10;
        $network = $request->get_param('network') ?? 'amazon';

        try {
            $service = new ProductDataService();
            $products = $service->searchProducts($query, $count, $network);

            $networkLabels = ['amazon' => 'Amazon', 'cj' => 'CJ Affiliate', 'awin' => 'Awin'];
            $networkLabel = $networkLabels[$network] ?? $network;

            $message = null;
            if (empty($products)) {
                if ($network === 'amazon') {
                    $service2 = new ProductDataService();
                    $status = $service2->getStatus();
                    if (!$status['api_configured']) {
                        $message = __('No products found. Amazon may be blocking requests from your server. Try entering an ASIN directly, or configure your Amazon PA-API keys in Settings for reliable results.', 'wp-product-builder');
                    } else {
                        $message = __('No products found on Amazon. Try a different search term.', 'wp-product-builder');
                    }
                } else {
                    $message = sprintf(
                        __('No products found on %s. Try a different search term.', 'wp-product-builder'),
                        $networkLabel
                    );
                }
            }

            return new WP_REST_Response([
                'success' => true,
                'products' => $products,
                'total' => count($products),
                'network' => $network,
                'message' => $message,
            ], 200);

        } catch (\Exception $e) {
            error_log('WPB Search Error: ' . $e->getMessage());
            return new WP_Error(
                'search_failed',
                __('Search failed: ', 'wp-product-builder') . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get single product by ASIN (backward compat route)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_product(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $asin = strtoupper($request->get_param('asin'));
        $network = $request->get_param('network') ?? 'amazon';

        $service = new ProductDataService();
        $product = $service->getProduct($asin, $network);

        if (!$product) {
            return new WP_Error(
                'not_found',
                __('Failed to fetch product.', 'wp-product-builder'),
                ['status' => 404]
            );
        }

        return new WP_REST_Response([
            'success' => true,
            'product' => $product,
        ], 200);
    }

    /**
     * Get single product by generic product ID
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_product_by_id(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $productId = $request->get_param('product_id');
        $network = $request->get_param('network') ?? 'amazon';

        $service = new ProductDataService();
        $product = $service->getProduct($productId, $network);

        if (!$product) {
            return new WP_Error(
                'not_found',
                __('Failed to fetch product.', 'wp-product-builder'),
                ['status' => 404]
            );
        }

        return new WP_REST_Response([
            'success' => true,
            'product' => $product,
        ], 200);
    }

    /**
     * Get multiple products
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_multiple_products(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $network = $request->get_param('network') ?? 'amazon';

        // Accept either 'asins' (backward compat) or 'product_ids'
        $ids = $request->get_param('product_ids') ?? $request->get_param('asins') ?? [];

        if ($network === 'amazon') {
            $ids = array_map('strtoupper', $ids);
        }
        $ids = array_unique(array_filter(array_map('trim', $ids)));

        if (empty($ids)) {
            return new WP_Error(
                'invalid_products',
                __('No valid product identifiers provided.', 'wp-product-builder'),
                ['status' => 400]
            );
        }

        $service = new ProductDataService();
        $products = $service->getMultipleProducts($ids, $network);

        return new WP_REST_Response([
            'success' => true,
            'products' => $products,
        ], 200);
    }
}
