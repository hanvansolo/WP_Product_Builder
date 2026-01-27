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
                ],
            ],
        ]);

        // Get single product
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
                        'required' => true,
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                    ],
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

        $service = new ProductDataService();
        $products = $service->searchProducts($query, $count);

        if (empty($products)) {
            return new WP_Error(
                'search_failed',
                __('Search failed. Please try again.', 'wp-product-builder'),
                ['status' => 500]
            );
        }

        return new WP_REST_Response([
            'success' => true,
            'products' => $products,
            'total' => count($products),
        ], 200);
    }

    /**
     * Get single product
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_product(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $asin = strtoupper($request->get_param('asin'));

        $service = new ProductDataService();
        $product = $service->getProduct($asin);

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
        $asins = $request->get_param('asins');
        $asins = array_map('strtoupper', $asins);
        $asins = array_unique(array_filter($asins));

        if (empty($asins)) {
            return new WP_Error(
                'invalid_asins',
                __('No valid ASINs provided.', 'wp-product-builder'),
                ['status' => 400]
            );
        }

        $service = new ProductDataService();
        $products = $service->getMultipleProducts($asins);

        return new WP_REST_Response([
            'success' => true,
            'products' => $products,
        ], 200);
    }
}
