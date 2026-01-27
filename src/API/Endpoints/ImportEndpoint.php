<?php
/**
 * Import REST API Endpoint
 *
 * Handles WooCommerce product imports and auto-import jobs
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
use WPProductBuilder\WooCommerce\ProductImporter;
use WPProductBuilder\WooCommerce\AutoImporter;
use WPProductBuilder\Services\ProductDataService;

/**
 * REST API endpoints for product importing
 */
class ImportEndpoint extends WP_REST_Controller {
    /**
     * Namespace
     */
    protected $namespace = 'wp-product-builder/v1';

    /**
     * Resource name
     */
    protected $rest_base = 'import';

    /**
     * Register routes
     */
    public function register_routes(): void {
        // Import single product to WooCommerce
        register_rest_route($this->namespace, '/' . $this->rest_base . '/product', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'import_product'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'asin' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'category_id' => [
                        'type' => 'integer',
                    ],
                    'options' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ]);

        // Import multiple products
        register_rest_route($this->namespace, '/' . $this->rest_base . '/products', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'import_products'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'asins' => [
                        'required' => true,
                        'type' => 'array',
                    ],
                    'category_id' => [
                        'type' => 'integer',
                    ],
                    'options' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ]);

        // Get/Create/Delete import jobs
        register_rest_route($this->namespace, '/' . $this->rest_base . '/jobs', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_jobs'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_job'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'name' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'type' => [
                        'required' => true,
                        'type' => 'string',
                        'enum' => ['keyword', 'asin_list', 'category'],
                    ],
                    'config' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ]);

        // Single job operations
        register_rest_route($this->namespace, '/' . $this->rest_base . '/jobs/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_job'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_job'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        // Run a job
        register_rest_route($this->namespace, '/' . $this->rest_base . '/jobs/(?P<id>\d+)/run', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'run_job'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        // Queue status
        register_rest_route($this->namespace, '/' . $this->rest_base . '/queue', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_queue_status'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        // Process queue manually
        register_rest_route($this->namespace, '/' . $this->rest_base . '/queue/process', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'process_queue'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'batch_size' => [
                        'type' => 'integer',
                        'default' => 5,
                    ],
                ],
            ],
        ]);

        // Sync product
        register_rest_route($this->namespace, '/' . $this->rest_base . '/sync/(?P<product_id>\d+)', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'sync_product'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }

    /**
     * Check permission
     */
    public function check_permission(): bool {
        return current_user_can('manage_options');
    }

    /**
     * Import single product to WooCommerce
     */
    public function import_product(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $asin = $request->get_param('asin');
        $options = $request->get_param('options') ?? [];

        if ($category_id = $request->get_param('category_id')) {
            $options['woo_category_id'] = $category_id;
        }

        $importer = new ProductImporter();
        $result = $importer->importProduct($asin, $options);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response([
            'success' => true,
            'product_id' => $result,
            'message' => __('Product imported successfully', 'wp-product-builder'),
            'edit_url' => get_edit_post_link($result, 'raw'),
        ], 200);
    }

    /**
     * Import multiple products
     */
    public function import_products(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $asins = $request->get_param('asins');
        $options = $request->get_param('options') ?? [];

        if ($category_id = $request->get_param('category_id')) {
            $options['woo_category_id'] = $category_id;
        }

        $importer = new ProductImporter();
        $results = [
            'imported' => [],
            'failed' => [],
        ];

        foreach ($asins as $asin) {
            $result = $importer->importProduct($asin, $options);

            if (is_wp_error($result)) {
                $results['failed'][] = [
                    'asin' => $asin,
                    'error' => $result->get_error_message(),
                ];
            } else {
                $results['imported'][] = [
                    'asin' => $asin,
                    'product_id' => $result,
                ];
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'imported_count' => count($results['imported']),
            'failed_count' => count($results['failed']),
            'results' => $results,
        ], 200);
    }

    /**
     * Get all import jobs
     */
    public function get_jobs(WP_REST_Request $request): WP_REST_Response {
        $autoImporter = new AutoImporter();
        $jobs = $autoImporter->getJobs();

        return new WP_REST_Response([
            'success' => true,
            'jobs' => $jobs,
        ], 200);
    }

    /**
     * Get single job
     */
    public function get_job(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $job_id = (int) $request->get_param('id');

        $autoImporter = new AutoImporter();
        $job = $autoImporter->getJob($job_id);

        if (!$job) {
            return new WP_Error(
                'job_not_found',
                __('Import job not found', 'wp-product-builder'),
                ['status' => 404]
            );
        }

        return new WP_REST_Response([
            'success' => true,
            'job' => $job,
        ], 200);
    }

    /**
     * Create import job
     */
    public function create_job(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $config = [
            'name' => $request->get_param('name'),
            'type' => $request->get_param('type'),
        ];

        // Merge additional config
        $extra_config = $request->get_param('config');
        if (is_array($extra_config)) {
            $config = array_merge($config, $extra_config);
        }

        $autoImporter = new AutoImporter();
        $job_id = $autoImporter->createJob($config);

        if (!$job_id) {
            return new WP_Error(
                'job_creation_failed',
                __('Failed to create import job', 'wp-product-builder'),
                ['status' => 500]
            );
        }

        return new WP_REST_Response([
            'success' => true,
            'job_id' => $job_id,
            'message' => __('Import job created successfully', 'wp-product-builder'),
        ], 201);
    }

    /**
     * Delete job
     */
    public function delete_job(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $job_id = (int) $request->get_param('id');

        $autoImporter = new AutoImporter();
        $result = $autoImporter->deleteJob($job_id);

        if (!$result) {
            return new WP_Error(
                'job_deletion_failed',
                __('Failed to delete import job', 'wp-product-builder'),
                ['status' => 500]
            );
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Import job deleted successfully', 'wp-product-builder'),
        ], 200);
    }

    /**
     * Run job manually
     */
    public function run_job(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $job_id = (int) $request->get_param('id');

        $autoImporter = new AutoImporter();
        $result = $autoImporter->runJob($job_id);

        if (!$result['success']) {
            return new WP_Error(
                'job_run_failed',
                $result['error'] ?? __('Failed to run import job', 'wp-product-builder'),
                ['status' => 500]
            );
        }

        return new WP_REST_Response([
            'success' => true,
            'products_found' => $result['products_found'],
            'products_queued' => $result['products_queued'],
            'message' => sprintf(
                __('Job executed: %d products found, %d queued for import', 'wp-product-builder'),
                $result['products_found'],
                $result['products_queued']
            ),
        ], 200);
    }

    /**
     * Get queue status
     */
    public function get_queue_status(WP_REST_Request $request): WP_REST_Response {
        $autoImporter = new AutoImporter();
        $status = $autoImporter->getQueueStatus();

        return new WP_REST_Response([
            'success' => true,
            'queue' => $status,
        ], 200);
    }

    /**
     * Process queue manually
     */
    public function process_queue(WP_REST_Request $request): WP_REST_Response {
        $batch_size = $request->get_param('batch_size');

        $autoImporter = new AutoImporter();
        $result = $autoImporter->processQueue($batch_size);

        return new WP_REST_Response([
            'success' => true,
            'processed' => $result['processed'],
            'succeeded' => $result['succeeded'],
            'failed' => $result['failed'],
            'details' => $result['details'],
        ], 200);
    }

    /**
     * Sync product with Amazon
     */
    public function sync_product(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $product_id = (int) $request->get_param('product_id');

        // Get ASIN from product meta
        $asin = get_post_meta($product_id, '_wpb_asin', true);

        if (empty($asin)) {
            return new WP_Error(
                'no_asin',
                __('Product has no associated ASIN', 'wp-product-builder'),
                ['status' => 400]
            );
        }

        $importer = new ProductImporter();
        $result = $importer->syncProduct($product_id, $asin);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Product synced successfully', 'wp-product-builder'),
            'updated_fields' => $result,
        ], 200);
    }
}
