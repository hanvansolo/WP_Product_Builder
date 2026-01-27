<?php
/**
 * Auto Product Importer
 *
 * Automated product import based on keywords, categories, or bestsellers
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\WooCommerce;

use WPProductBuilder\Services\ProductDataService;

/**
 * Handles automated product importing
 */
class AutoImporter {
    /**
     * Import queue table
     */
    private string $queueTable;

    /**
     * Import jobs table
     */
    private string $jobsTable;

    /**
     * Product data service
     */
    private ProductDataService $productService;

    /**
     * Product importer
     */
    private ProductImporter $importer;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->queueTable = $wpdb->prefix . 'wpb_import_queue';
        $this->jobsTable = $wpdb->prefix . 'wpb_import_jobs';
        $this->productService = new ProductDataService();
        $this->importer = new ProductImporter();
    }

    /**
     * Create an import job
     *
     * @param array $config Job configuration
     * @return int|false Job ID or false on failure
     */
    public function createJob(array $config): int|false {
        global $wpdb;

        $defaults = [
            'name' => __('Unnamed Job', 'wp-product-builder'),
            'type' => 'keyword', // keyword, category, asin_list
            'keywords' => [],
            'asins' => [],
            'category' => '',
            'max_products' => 10,
            'woo_category_id' => null,
            'status' => 'active',
            'schedule' => 'manual', // manual, daily, weekly
            'last_run' => null,
            'import_options' => [],
        ];

        $config = array_merge($defaults, $config);

        $result = $wpdb->insert($this->jobsTable, [
            'name' => sanitize_text_field($config['name']),
            'type' => $config['type'],
            'config' => json_encode($config),
            'status' => $config['status'],
            'schedule' => $config['schedule'],
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id(),
        ]);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Run an import job
     *
     * @param int $jobId Job ID
     * @return array Results
     */
    public function runJob(int $jobId): array {
        global $wpdb;

        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->jobsTable} WHERE id = %d",
            $jobId
        ), ARRAY_A);

        if (!$job) {
            return ['success' => false, 'error' => __('Job not found', 'wp-product-builder')];
        }

        $config = json_decode($job['config'], true);

        // Find products based on job type
        $products = [];

        switch ($job['type']) {
            case 'keyword':
                foreach ($config['keywords'] as $keyword) {
                    $results = $this->productService->searchProducts($keyword, $config['max_products']);
                    $products = array_merge($products, $results);
                }
                break;

            case 'asin_list':
                $asins = $config['asins'] ?? [];
                $productData = $this->productService->getMultipleProducts($asins);
                $products = array_values($productData);
                break;

            case 'category':
                // Search by category name
                $results = $this->productService->searchProducts($config['category'], $config['max_products']);
                $products = $results;
                break;
        }

        // Limit products
        $products = array_slice($products, 0, $config['max_products']);

        // Queue products for import
        $queued = 0;
        foreach ($products as $product) {
            if (!empty($product['asin'])) {
                $this->queueProduct($product['asin'], $jobId, $config);
                $queued++;
            }
        }

        // Update job last run
        $wpdb->update(
            $this->jobsTable,
            ['last_run' => current_time('mysql')],
            ['id' => $jobId]
        );

        return [
            'success' => true,
            'products_found' => count($products),
            'products_queued' => $queued,
        ];
    }

    /**
     * Queue a product for import
     */
    public function queueProduct(string $asin, int $jobId, array $options = []): bool {
        global $wpdb;

        // Check if already in queue
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->queueTable}
             WHERE asin = %s AND status IN ('pending', 'processing')",
            $asin
        ));

        if ($existing) {
            return false;
        }

        return (bool) $wpdb->insert($this->queueTable, [
            'asin' => strtoupper($asin),
            'job_id' => $jobId,
            'status' => 'pending',
            'options' => json_encode($options),
            'created_at' => current_time('mysql'),
            'attempts' => 0,
        ]);
    }

    /**
     * Process import queue
     *
     * @param int $batchSize Number of products to process
     * @return array Processing results
     */
    public function processQueue(int $batchSize = 5): array {
        global $wpdb;

        // Get pending items
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->queueTable}
             WHERE status = 'pending' AND attempts < 3
             ORDER BY created_at ASC
             LIMIT %d",
            $batchSize
        ), ARRAY_A);

        $results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($items as $item) {
            // Mark as processing
            $wpdb->update(
                $this->queueTable,
                ['status' => 'processing', 'attempts' => $item['attempts'] + 1],
                ['id' => $item['id']]
            );

            $options = json_decode($item['options'], true) ?? [];

            // Import the product
            $productId = $this->importer->importProduct($item['asin'], $options);

            if (is_wp_error($productId)) {
                // Mark as failed
                $wpdb->update(
                    $this->queueTable,
                    [
                        'status' => $item['attempts'] >= 2 ? 'failed' : 'pending',
                        'error' => $productId->get_error_message(),
                    ],
                    ['id' => $item['id']]
                );

                $results['failed']++;
                $results['details'][$item['asin']] = [
                    'success' => false,
                    'error' => $productId->get_error_message(),
                ];
            } else {
                // Mark as completed
                $wpdb->update(
                    $this->queueTable,
                    [
                        'status' => 'completed',
                        'product_id' => $productId,
                        'completed_at' => current_time('mysql'),
                    ],
                    ['id' => $item['id']]
                );

                $results['succeeded']++;
                $results['details'][$item['asin']] = [
                    'success' => true,
                    'product_id' => $productId,
                ];
            }

            $results['processed']++;
        }

        return $results;
    }

    /**
     * Get all jobs
     */
    public function getJobs(): array {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT j.*,
                    (SELECT COUNT(*) FROM {$this->queueTable} q WHERE q.job_id = j.id AND q.status = 'completed') as products_imported
             FROM {$this->jobsTable} j
             ORDER BY j.created_at DESC",
            ARRAY_A
        );
    }

    /**
     * Get job by ID
     */
    public function getJob(int $jobId): ?array {
        global $wpdb;

        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->jobsTable} WHERE id = %d",
            $jobId
        ), ARRAY_A);

        if ($job) {
            $job['config'] = json_decode($job['config'], true);
        }

        return $job;
    }

    /**
     * Delete job
     */
    public function deleteJob(int $jobId): bool {
        global $wpdb;

        // Delete queue items
        $wpdb->delete($this->queueTable, ['job_id' => $jobId]);

        // Delete job
        return (bool) $wpdb->delete($this->jobsTable, ['id' => $jobId]);
    }

    /**
     * Get queue status
     */
    public function getQueueStatus(): array {
        global $wpdb;

        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
             FROM {$this->queueTable}",
            ARRAY_A
        );

        return [
            'total' => (int) $stats['total'],
            'pending' => (int) $stats['pending'],
            'processing' => (int) $stats['processing'],
            'completed' => (int) $stats['completed'],
            'failed' => (int) $stats['failed'],
        ];
    }

    /**
     * Clear completed queue items
     */
    public function clearCompletedQueue(): int {
        global $wpdb;

        return $wpdb->query(
            "DELETE FROM {$this->queueTable} WHERE status = 'completed'"
        );
    }

    /**
     * Register cron hooks
     */
    public static function registerCronHooks(): void {
        add_action('wpb_process_import_queue', [self::class, 'cronProcessQueue']);
        add_action('wpb_run_scheduled_jobs', [self::class, 'cronRunScheduledJobs']);
    }

    /**
     * Cron: Process import queue
     */
    public static function cronProcessQueue(): void {
        $importer = new self();
        $importer->processQueue(10);
    }

    /**
     * Cron: Run scheduled jobs
     */
    public static function cronRunScheduledJobs(): void {
        global $wpdb;

        $importer = new self();
        $jobsTable = $wpdb->prefix . 'wpb_import_jobs';

        // Get jobs that need to run
        $jobs = $wpdb->get_results(
            "SELECT id, schedule, last_run FROM {$jobsTable}
             WHERE status = 'active' AND schedule != 'manual'",
            ARRAY_A
        );

        foreach ($jobs as $job) {
            $shouldRun = false;

            if ($job['last_run'] === null) {
                $shouldRun = true;
            } else {
                $lastRun = strtotime($job['last_run']);

                switch ($job['schedule']) {
                    case 'hourly':
                        $shouldRun = $lastRun < strtotime('-1 hour');
                        break;
                    case 'daily':
                        $shouldRun = $lastRun < strtotime('-1 day');
                        break;
                    case 'weekly':
                        $shouldRun = $lastRun < strtotime('-1 week');
                        break;
                }
            }

            if ($shouldRun) {
                $importer->runJob($job['id']);
            }
        }
    }
}
