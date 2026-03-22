<?php
/**
 * Content Generate REST API Endpoint
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\API\Endpoints;

use WPProductBuilder\Content\ContentGenerator;
use WPProductBuilder\Database\Repositories\ContentRepository;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API controller for content generation
 */
class ContentGenerateEndpoint extends WP_REST_Controller {
    /**
     * Namespace
     */
    protected $namespace = 'wp-product-builder/v1';

    /**
     * Resource name
     */
    protected $rest_base = 'content';

    /**
     * Register routes
     */
    public function register_routes(): void {
        // Generate content
        register_rest_route($this->namespace, '/' . $this->rest_base . '/generate', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generate_content'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => [
                    'type' => [
                        'required' => true,
                        'type' => 'string',
                        'enum' => ['product_review', 'products_roundup', 'products_comparison', 'listicle', 'deals'],
                    ],
                    'products' => [
                        'required' => true,
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                    ],
                    'network' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => 'amazon',
                        'enum' => ['amazon', 'cj', 'awin'],
                    ],
                    'options' => [
                        'required' => false,
                        'type' => 'object',
                    ],
                ],
            ],
        ]);

        // Create post from generated content
        register_rest_route($this->namespace, '/' . $this->rest_base . '/create-post', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_post'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => [
                    'history_id' => [
                        'required' => true,
                        'type' => 'integer',
                    ],
                    'status' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => 'draft',
                        'enum' => ['draft', 'publish'],
                    ],
                    'category' => [
                        'required' => false,
                        'type' => 'integer',
                    ],
                ],
            ],
        ]);

        // Get content history
        register_rest_route($this->namespace, '/' . $this->rest_base . '/history', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_history'],
                'permission_callback' => [$this, 'check_view_permissions'],
                'args' => [
                    'page' => [
                        'required' => false,
                        'type' => 'integer',
                        'default' => 1,
                    ],
                    'per_page' => [
                        'required' => false,
                        'type' => 'integer',
                        'default' => 20,
                        'maximum' => 100,
                    ],
                ],
            ],
        ]);

        // Get single history item
        register_rest_route($this->namespace, '/' . $this->rest_base . '/history/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_history_item'],
                'permission_callback' => [$this, 'check_view_permissions'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_history_item'],
                'permission_callback' => [$this, 'check_permissions'],
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
                __('You do not have permission to generate content.', 'wp-product-builder'),
                ['status' => 403]
            );
        }
        return true;
    }

    /**
     * Check view permissions
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_view_permissions(WP_REST_Request $request) {
        if (!current_user_can('wpb_view_history')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to view content history.', 'wp-product-builder'),
                ['status' => 403]
            );
        }
        return true;
    }

    /**
     * Generate content
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function generate_content(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $type = $request->get_param('type');
        $products = $request->get_param('products');
        $network = $request->get_param('network') ?? 'amazon';
        $options = $request->get_param('options') ?? [];

        // Sanitize product IDs based on network
        if ($network === 'amazon') {
            $products = array_map(function($asin) {
                return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $asin));
            }, $products);

            $products = array_filter($products, function($asin) {
                return strlen($asin) === 10;
            });
        } else {
            // For CJ/Awin, just sanitize as text
            $products = array_map('sanitize_text_field', $products);
            $products = array_filter($products);
        }

        if (empty($products)) {
            return new WP_Error(
                'invalid_products',
                __('No valid product identifiers provided.', 'wp-product-builder'),
                ['status' => 400]
            );
        }

        // Validate product count for content type
        $type_limits = [
            'product_review' => ['min' => 1, 'max' => 1],
            'products_roundup' => ['min' => 2, 'max' => 20],
            'products_comparison' => ['min' => 2, 'max' => 4],
            'listicle' => ['min' => 2, 'max' => 15],
            'deals' => ['min' => 1, 'max' => 10],
        ];

        $limits = $type_limits[$type] ?? ['min' => 1, 'max' => 10];

        if (count($products) < $limits['min'] || count($products) > $limits['max']) {
            return new WP_Error(
                'invalid_product_count',
                sprintf(
                    __('This content type requires between %d and %d products.', 'wp-product-builder'),
                    $limits['min'],
                    $limits['max']
                ),
                ['status' => 400]
            );
        }

        $generator = new ContentGenerator();
        $result = $generator->generate($type, $products, $options, $network);

        if (!$result['success']) {
            return new WP_Error(
                'generation_failed',
                $result['error'] ?? __('Content generation failed.', 'wp-product-builder'),
                ['status' => 500]
            );
        }

        return new WP_REST_Response([
            'success' => true,
            'content' => $result['content'],
            'title' => $result['title'],
            'history_id' => $result['history_id'],
            'products' => $result['products'],
            'usage' => $result['usage'] ?? null,
        ], 200);
    }

    /**
     * Create WordPress post from generated content
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_post(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $history_id = $request->get_param('history_id');
        $status = $request->get_param('status') ?? 'draft';
        $category = $request->get_param('category');

        $generator = new ContentGenerator();

        $post_options = [
            'status' => $status,
        ];

        if ($category) {
            $post_options['categories'] = [(int) $category];
        }

        $result = $generator->createPost($history_id, $post_options);

        if (is_wp_error($result)) {
            return new WP_Error(
                'post_creation_failed',
                $result->get_error_message(),
                ['status' => 500]
            );
        }

        return new WP_REST_Response([
            'success' => true,
            'post_id' => $result,
            'edit_url' => get_edit_post_link($result, 'raw'),
            'view_url' => get_permalink($result),
        ], 200);
    }

    /**
     * Get content history
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_history(WP_REST_Request $request): WP_REST_Response {
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');

        $repo = new ContentRepository();
        $result = $repo->getAll($page, $per_page);

        return new WP_REST_Response([
            'success' => true,
            'items' => $result['items'],
            'total' => $result['total'],
            'pages' => $result['pages'],
        ], 200);
    }

    /**
     * Get single history item
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_history_item(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $id = (int) $request->get_param('id');

        $repo = new ContentRepository();
        $item = $repo->get($id);

        if (!$item) {
            return new WP_Error(
                'not_found',
                __('Content not found.', 'wp-product-builder'),
                ['status' => 404]
            );
        }

        return new WP_REST_Response([
            'success' => true,
            'item' => $item,
        ], 200);
    }

    /**
     * Delete history item
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function delete_history_item(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $id = (int) $request->get_param('id');

        $repo = new ContentRepository();
        $deleted = $repo->delete($id);

        if (!$deleted) {
            return new WP_Error(
                'delete_failed',
                __('Failed to delete content.', 'wp-product-builder'),
                ['status' => 500]
            );
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Content deleted.', 'wp-product-builder'),
        ], 200);
    }
}
