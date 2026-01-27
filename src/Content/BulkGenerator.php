<?php
/**
 * Bulk Content Generator
 *
 * Handles bulk article generation with scheduling (AIWiseMind-like feature)
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Content;

use WPProductBuilder\Services\ProductDataService;

/**
 * Generates multiple articles with scheduling support
 */
class BulkGenerator {
    /**
     * Queue table
     */
    private string $queueTable;

    /**
     * Jobs table
     */
    private string $jobsTable;

    /**
     * Product data service
     */
    private ProductDataService $productService;

    /**
     * Content generator
     */
    private ContentGenerator $generator;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->queueTable = $wpdb->prefix . 'wpb_content_queue';
        $this->jobsTable = $wpdb->prefix . 'wpb_content_jobs';
        $this->productService = new ProductDataService();
        $this->generator = new ContentGenerator();
    }

    /**
     * Create a bulk generation job
     *
     * @param array $config Job configuration
     * @return int|false Job ID or false on failure
     */
    public function createBulkJob(array $config): int|false {
        global $wpdb;

        $defaults = [
            'name' => __('Bulk Job', 'wp-product-builder'),
            'content_type' => 'product_review',
            'keywords' => [],
            'asins' => [],
            'products_per_article' => 1,
            'article_count' => 10,
            'schedule_type' => 'immediate', // immediate, drip
            'drip_interval' => 'daily', // hourly, daily, weekly
            'start_date' => current_time('mysql'),
            'options' => [
                'tone' => 'professional',
                'length' => 'medium',
                'include_pros_cons' => true,
                'include_faq' => true,
            ],
            'post_status' => 'draft',
            'post_category' => null,
        ];

        $config = array_merge($defaults, $config);

        $result = $wpdb->insert($this->jobsTable, [
            'name' => sanitize_text_field($config['name']),
            'content_type' => $config['content_type'],
            'config' => json_encode($config),
            'status' => 'active',
            'schedule_type' => $config['schedule_type'],
            'schedule_interval' => $config['drip_interval'] ?? null,
            'total_items' => $config['article_count'],
            'completed_items' => 0,
            'failed_items' => 0,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id(),
        ]);

        if (!$result) {
            return false;
        }

        $jobId = $wpdb->insert_id;

        // Queue individual articles
        $this->queueArticlesForJob($jobId, $config);

        return $jobId;
    }

    /**
     * Queue articles for a job
     */
    private function queueArticlesForJob(int $jobId, array $config): void {
        global $wpdb;

        // Get products to write about
        $products = $this->gatherProductsForJob($config);

        // Group products based on content type requirements
        $articleGroups = $this->groupProductsForArticles($products, $config);

        $scheduledTime = strtotime($config['start_date']);

        foreach ($articleGroups as $index => $group) {
            // Calculate scheduled time for drip
            if ($config['schedule_type'] === 'drip') {
                switch ($config['drip_interval']) {
                    case 'hourly':
                        $scheduledTime = strtotime("+{$index} hours", strtotime($config['start_date']));
                        break;
                    case 'daily':
                        $scheduledTime = strtotime("+{$index} days", strtotime($config['start_date']));
                        break;
                    case 'weekly':
                        $scheduledTime = strtotime("+{$index} weeks", strtotime($config['start_date']));
                        break;
                }
            }

            // Generate a title from products
            $title = $this->generateArticleTitle($group, $config['content_type']);

            $wpdb->insert($this->queueTable, [
                'job_id' => $jobId,
                'title' => $title,
                'asins' => json_encode(array_column($group, 'asin')),
                'options' => json_encode(array_merge($config['options'], [
                    'products' => $group,
                    'post_status' => $config['post_status'],
                    'post_category' => $config['post_category'],
                ])),
                'status' => 'pending',
                'scheduled_at' => date('Y-m-d H:i:s', $scheduledTime),
                'created_at' => current_time('mysql'),
            ]);
        }
    }

    /**
     * Gather products for a job
     */
    private function gatherProductsForJob(array $config): array {
        $products = [];

        // From ASINs
        if (!empty($config['asins'])) {
            $productData = $this->productService->getMultipleProducts($config['asins']);
            $products = array_merge($products, array_values($productData));
        }

        // From keywords
        if (!empty($config['keywords'])) {
            foreach ($config['keywords'] as $keyword) {
                $searchResults = $this->productService->searchProducts($keyword, 20);
                $products = array_merge($products, $searchResults);
            }
        }

        // Remove duplicates by ASIN
        $unique = [];
        foreach ($products as $product) {
            $unique[$product['asin']] = $product;
        }

        return array_values($unique);
    }

    /**
     * Generate article title from products
     */
    private function generateArticleTitle(array $products, string $contentType): string {
        $category = $products[0]['category'] ?? 'Products';
        $count = count($products);
        $year = date('Y');

        $templates = [
            'product_review' => $products[0]['title'] ?? 'Product Review',
            'products_roundup' => "Best {$count} {$category} in {$year}",
            'products_comparison' => "{$category} Comparison: " . implode(' vs ', array_slice(array_column($products, 'title'), 0, 2)),
            'listicle' => "{$count} {$category} You Need to Know About",
            'deals' => "Best {$category} Deals: Save Big Today!",
        ];

        return $templates[$contentType] ?? "Article about {$category}";
    }

    /**
     * Group products into article sets
     */
    private function groupProductsForArticles(array $products, array $config): array {
        $groups = [];
        $perArticle = $config['products_per_article'];
        $articleCount = $config['article_count'];

        // Determine products needed per article based on content type
        $productRequirements = [
            'product_review' => 1,
            'products_roundup' => min($perArticle, 10),
            'products_comparison' => min($perArticle, 4),
            'listicle' => min($perArticle, 15),
            'deals' => min($perArticle, 10),
        ];

        $needed = $productRequirements[$config['content_type']] ?? 1;

        // Shuffle products for variety
        shuffle($products);

        for ($i = 0; $i < $articleCount && count($products) >= $needed; $i++) {
            $group = array_splice($products, 0, $needed);
            if (count($group) >= $needed) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * Process pending articles
     *
     * @param int $batchSize Number of articles to process
     * @return array Results
     */
    public function processQueue(int $batchSize = 3): array {
        global $wpdb;

        // Get ready-to-process articles
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->queueTable}
             WHERE status = 'pending'
             AND scheduled_at <= %s
             ORDER BY scheduled_at ASC
             LIMIT %d",
            current_time('mysql'),
            $batchSize
        ), ARRAY_A);

        $results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
        ];

        foreach ($items as $item) {
            // Mark as processing
            $wpdb->update(
                $this->queueTable,
                ['status' => 'processing'],
                ['id' => $item['id']]
            );

            $products = json_decode($item['products_json'], true);
            $options = json_decode($item['options_json'], true);
            $asins = array_column($products, 'asin');

            // Generate content
            $result = $this->generator->generate(
                $item['content_type'],
                $asins,
                $options
            );

            if ($result['success']) {
                // Create post
                $postOptions = [
                    'status' => $item['post_status'],
                ];

                if ($item['post_category']) {
                    $postOptions['categories'] = [(int) $item['post_category']];
                }

                $postId = $this->generator->createPost($result['history_id'], $postOptions);

                if (!is_wp_error($postId)) {
                    // Success
                    $wpdb->update(
                        $this->queueTable,
                        [
                            'status' => 'completed',
                            'post_id' => $postId,
                            'history_id' => $result['history_id'],
                            'completed_at' => current_time('mysql'),
                        ],
                        ['id' => $item['id']]
                    );

                    // Update job counter
                    $this->updateJobProgress($item['job_id']);

                    $results['succeeded']++;
                } else {
                    $this->markAsFailed($item['id'], $postId->get_error_message());
                    $results['failed']++;
                }
            } else {
                $this->markAsFailed($item['id'], $result['error'] ?? 'Generation failed');
                $results['failed']++;
            }

            $results['processed']++;
        }

        return $results;
    }

    /**
     * Mark queue item as failed
     */
    private function markAsFailed(int $itemId, string $error): void {
        global $wpdb;

        $wpdb->update(
            $this->queueTable,
            [
                'status' => 'failed',
                'error' => $error,
            ],
            ['id' => $itemId]
        );
    }

    /**
     * Update job progress
     */
    private function updateJobProgress(int $jobId): void {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->jobsTable}
             SET completed_items = completed_items + 1,
                 status = CASE
                     WHEN completed_items + 1 >= total_items THEN 'completed'
                     ELSE 'active'
                 END
             WHERE id = %d",
            $jobId
        ));
    }

    /**
     * Get job status
     */
    public function getJobStatus(int $jobId): ?array {
        global $wpdb;

        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->jobsTable} WHERE id = %d",
            $jobId
        ), ARRAY_A);

        if (!$job) {
            return null;
        }

        $job['config'] = json_decode($job['config'], true);

        // Get queue items stats
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
             FROM {$this->queueTable}
             WHERE job_id = %d",
            $jobId
        ), ARRAY_A);

        $job['stats'] = $stats;

        return $job;
    }

    /**
     * Get all jobs
     */
    public function getJobs(): array {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT j.*
             FROM {$this->jobsTable} j
             ORDER BY j.created_at DESC",
            ARRAY_A
        );
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
            'total' => (int) ($stats['total'] ?? 0),
            'pending' => (int) ($stats['pending'] ?? 0),
            'processing' => (int) ($stats['processing'] ?? 0),
            'completed' => (int) ($stats['completed'] ?? 0),
            'failed' => (int) ($stats['failed'] ?? 0),
        ];
    }

    /**
     * Cancel a job
     */
    public function cancelJob(int $jobId): bool {
        global $wpdb;

        // Cancel pending queue items
        $wpdb->update(
            $this->queueTable,
            ['status' => 'cancelled'],
            ['job_id' => $jobId, 'status' => 'pending']
        );

        // Update job status
        return (bool) $wpdb->update(
            $this->jobsTable,
            ['status' => 'cancelled'],
            ['id' => $jobId]
        );
    }

    /**
     * Delete a job and its queue items
     */
    public function deleteJob(int $jobId): bool {
        global $wpdb;

        $wpdb->delete($this->queueTable, ['job_id' => $jobId]);
        return (bool) $wpdb->delete($this->jobsTable, ['id' => $jobId]);
    }

    /**
     * Register cron hooks
     */
    public static function registerCronHooks(): void {
        add_action('wpb_process_content_queue', [self::class, 'cronProcessQueue']);
    }

    /**
     * Cron handler
     */
    public static function cronProcessQueue(): void {
        $generator = new self();
        $generator->processQueue(5);
    }
}
