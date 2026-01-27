<?php
/**
 * Bulk Content REST API Endpoint
 *
 * Handles bulk content generation
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\API\Endpoints;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPProductBuilder\Content\BulkGenerator;

/**
 * REST API endpoints for bulk content generation
 */
class BulkContentEndpoint extends WP_REST_Controller {
    /**
     * Namespace
     */
    protected $namespace = 'wp-product-builder/v1';

    /**
     * Resource name
     */
    protected $rest_base = 'content';

    /**
     * Bulk generator
     */
    private BulkGenerator $generator;

    /**
     * Constructor
     */
    public function __construct() {
        $this->generator = new BulkGenerator();
    }

    /**
     * Register routes
     */
    public function register_routes(): void {
        // Create bulk job
        register_rest_route($this->namespace, '/' . $this->rest_base . '/bulk', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_bulk_job'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'name' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                    'content_type' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                    'articles' => [
                        'required' => true,
                        'type' => 'array',
                    ],
                ],
            ],
        ]);

        // Get bulk jobs
        register_rest_route($this->namespace, '/' . $this->rest_base . '/bulk', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_jobs'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        // Delete bulk job
        register_rest_route($this->namespace, '/' . $this->rest_base . '/bulk/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_job'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        // Get queue status
        register_rest_route($this->namespace, '/' . $this->rest_base . '/queue/status', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_queue_status'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        // Process queue
        register_rest_route($this->namespace, '/' . $this->rest_base . '/queue/process', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'process_queue'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }

    /**
     * Check permission
     */
    public function check_permission(): bool {
        return current_user_can('edit_posts');
    }

    /**
     * Create bulk job
     */
    public function create_bulk_job(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $articles = $request->get_param('articles');
        $content_type = $request->get_param('content_type');
        $content_options = $request->get_param('content_options') ?? [];

        if (empty($articles)) {
            return new WP_Error('no_articles', __('No articles provided', 'wp-product-builder'), ['status' => 400]);
        }

        // Convert articles array to ASINs and keywords
        $allAsins = [];
        foreach ($articles as $article) {
            if (!empty($article['asins'])) {
                $allAsins = array_merge($allAsins, (array) $article['asins']);
            }
        }

        $config = [
            'name' => $request->get_param('name'),
            'content_type' => $content_type,
            'asins' => array_unique($allAsins),
            'article_count' => count($articles),
            'products_per_article' => $this->getProductsPerArticle($content_type),
            'schedule_type' => $request->get_param('schedule_type') ?? 'immediate',
            'drip_interval' => $request->get_param('schedule_interval') ?? 'daily',
            'options' => array_merge([
                'tone' => 'professional',
                'length' => 'medium',
            ], $content_options),
            'post_status' => $request->get_param('post_status') ?? 'draft',
            'post_category' => $content_options['category_id'] ?? null,
        ];

        $job_id = $this->generator->createBulkJob($config);

        if (!$job_id) {
            return new WP_Error('creation_failed', __('Failed to create bulk job', 'wp-product-builder'), ['status' => 500]);
        }

        return new WP_REST_Response([
            'success' => true,
            'job_id' => $job_id,
            'items_queued' => count($articles),
            'message' => __('Bulk job created successfully', 'wp-product-builder'),
        ], 201);
    }

    /**
     * Get products per article based on content type
     */
    private function getProductsPerArticle(string $contentType): int {
        return match($contentType) {
            'product_review' => 1,
            'products_roundup' => 5,
            'products_comparison' => 3,
            'listicle' => 10,
            'deals' => 5,
            default => 1,
        };
    }

    /**
     * Get all jobs
     */
    public function get_jobs(WP_REST_Request $request): WP_REST_Response {
        $jobs = $this->generator->getJobs();

        return new WP_REST_Response([
            'success' => true,
            'jobs' => $jobs,
        ], 200);
    }

    /**
     * Delete job
     */
    public function delete_job(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $job_id = (int) $request->get_param('id');

        $result = $this->generator->deleteJob($job_id);

        if (!$result) {
            return new WP_Error('delete_failed', __('Failed to delete job', 'wp-product-builder'), ['status' => 500]);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Job deleted successfully', 'wp-product-builder'),
        ], 200);
    }

    /**
     * Get queue status
     */
    public function get_queue_status(WP_REST_Request $request): WP_REST_Response {
        $status = $this->generator->getQueueStatus();

        return new WP_REST_Response([
            'success' => true,
            'queue' => $status,
        ], 200);
    }

    /**
     * Process queue
     */
    public function process_queue(WP_REST_Request $request): WP_REST_Response {
        $batch_size = $request->get_param('batch_size') ?? 3;

        $results = $this->generator->processQueue($batch_size);

        return new WP_REST_Response([
            'success' => true,
            'processed' => $results['processed'],
            'succeeded' => $results['succeeded'],
            'failed' => $results['failed'],
        ], 200);
    }
}
