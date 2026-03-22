<?php
/**
 * Database Migrator
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Database;

/**
 * Handles database table creation and migrations
 */
class Migrator {
    /**
     * Current database schema version
     */
    public const DB_VERSION = '2.0';

    /**
     * Run all migrations
     */
    public static function migrate(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Content History Table
        $content_history_table = $wpdb->prefix . 'wpb_content_history';
        $content_history_sql = "CREATE TABLE {$content_history_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED DEFAULT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            content_type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            prompt_used LONGTEXT NOT NULL,
            products_json LONGTEXT NOT NULL,
            generated_content LONGTEXT NOT NULL,
            template_id BIGINT(20) UNSIGNED DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'draft',
            tokens_used INT(11) DEFAULT 0,
            generation_cost DECIMAL(10,6) DEFAULT 0,
            model_used VARCHAR(50) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_user_id (user_id),
            KEY idx_content_type (content_type),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        // Product Cache Table
        $product_cache_table = $wpdb->prefix . 'wpb_product_cache';
        $product_cache_sql = "CREATE TABLE {$product_cache_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            asin VARCHAR(100) NOT NULL,
            network VARCHAR(20) NOT NULL DEFAULT 'amazon',
            marketplace VARCHAR(10) NOT NULL DEFAULT 'US',
            product_data LONGTEXT NOT NULL,
            title VARCHAR(500) DEFAULT NULL,
            price VARCHAR(50) DEFAULT NULL,
            currency VARCHAR(10) DEFAULT NULL,
            availability VARCHAR(100) DEFAULT NULL,
            image_url VARCHAR(2083) DEFAULT NULL,
            affiliate_url VARCHAR(2083) DEFAULT NULL,
            rating DECIMAL(3,2) DEFAULT NULL,
            review_count INT(11) DEFAULT NULL,
            last_fetched DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_asin_network_marketplace (asin, network, marketplace),
            KEY idx_network (network),
            KEY idx_expires_at (expires_at),
            KEY idx_last_fetched (last_fetched)
        ) {$charset_collate};";

        // Templates Table
        $templates_table = $wpdb->prefix . 'wpb_templates';
        $templates_sql = "CREATE TABLE {$templates_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            content_type VARCHAR(50) NOT NULL,
            template_content LONGTEXT NOT NULL,
            prompt_template LONGTEXT DEFAULT NULL,
            settings_json TEXT DEFAULT NULL,
            is_default TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_slug (slug),
            KEY idx_content_type (content_type),
            KEY idx_is_active (is_active)
        ) {$charset_collate};";

        // API Usage Table
        $api_usage_table = $wpdb->prefix . 'wpb_api_usage';
        $api_usage_sql = "CREATE TABLE {$api_usage_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            api_type VARCHAR(20) NOT NULL,
            endpoint VARCHAR(100) NOT NULL,
            request_count INT(11) DEFAULT 1,
            tokens_input INT(11) DEFAULT 0,
            tokens_output INT(11) DEFAULT 0,
            cost_estimate DECIMAL(10,6) DEFAULT 0,
            date_recorded DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_user_api_date (user_id, api_type, date_recorded),
            KEY idx_date_recorded (date_recorded)
        ) {$charset_collate};";

        // Import Jobs Table (AutoImporter)
        $import_jobs_table = $wpdb->prefix . 'wpb_import_jobs';
        $import_jobs_sql = "CREATE TABLE {$import_jobs_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            config LONGTEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            schedule VARCHAR(20) DEFAULT 'manual',
            last_run DATETIME DEFAULT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_schedule (schedule)
        ) {$charset_collate};";

        // Import Queue Table (AutoImporter)
        $import_queue_table = $wpdb->prefix . 'wpb_import_queue';
        $import_queue_sql = "CREATE TABLE {$import_queue_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            asin VARCHAR(100) NOT NULL,
            network VARCHAR(20) NOT NULL DEFAULT 'amazon',
            job_id BIGINT(20) UNSIGNED DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            options LONGTEXT DEFAULT NULL,
            product_id BIGINT(20) UNSIGNED DEFAULT NULL,
            error TEXT DEFAULT NULL,
            attempts INT(11) DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_asin (asin),
            KEY idx_network (network),
            KEY idx_job_id (job_id),
            KEY idx_status (status)
        ) {$charset_collate};";

        // Import Log Table (ProductImporter)
        $import_log_table = $wpdb->prefix . 'wpb_import_log';
        $import_log_sql = "CREATE TABLE {$import_log_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            asin VARCHAR(100) NOT NULL,
            network VARCHAR(20) NOT NULL DEFAULT 'amazon',
            product_id BIGINT(20) UNSIGNED DEFAULT NULL,
            action VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL,
            message TEXT DEFAULT NULL,
            details LONGTEXT DEFAULT NULL,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_asin (asin),
            KEY idx_network (network),
            KEY idx_product_id (product_id),
            KEY idx_action (action),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        // Content Queue Jobs Table (BulkGenerator)
        $content_jobs_table = $wpdb->prefix . 'wpb_content_jobs';
        $content_jobs_sql = "CREATE TABLE {$content_jobs_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            content_type VARCHAR(50) NOT NULL,
            config LONGTEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            schedule_type VARCHAR(20) DEFAULT 'immediate',
            schedule_interval VARCHAR(20) DEFAULT NULL,
            total_items INT(11) DEFAULT 0,
            completed_items INT(11) DEFAULT 0,
            failed_items INT(11) DEFAULT 0,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_content_type (content_type)
        ) {$charset_collate};";

        // Content Queue Table (BulkGenerator)
        $content_queue_table = $wpdb->prefix . 'wpb_content_queue';
        $content_queue_sql = "CREATE TABLE {$content_queue_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id BIGINT(20) UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            asins LONGTEXT NOT NULL,
            network VARCHAR(20) NOT NULL DEFAULT 'amazon',
            options LONGTEXT DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            post_id BIGINT(20) UNSIGNED DEFAULT NULL,
            history_id BIGINT(20) UNSIGNED DEFAULT NULL,
            error TEXT DEFAULT NULL,
            attempts INT(11) DEFAULT 0,
            scheduled_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_job_id (job_id),
            KEY idx_network (network),
            KEY idx_status (status),
            KEY idx_scheduled_at (scheduled_at)
        ) {$charset_collate};";

        // Product Stats Table (StatisticsTracker)
        $product_stats_table = $wpdb->prefix . 'wpb_product_stats';
        $product_stats_sql = "CREATE TABLE {$product_stats_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            asin VARCHAR(100) NOT NULL,
            network VARCHAR(20) NOT NULL DEFAULT 'amazon',
            post_id BIGINT(20) UNSIGNED DEFAULT NULL,
            views INT(11) DEFAULT 0,
            clicks INT(11) DEFAULT 0,
            conversions INT(11) DEFAULT 0,
            revenue DECIMAL(10,2) DEFAULT 0,
            date_recorded DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_asin_network_post_date (asin, network, post_id, date_recorded),
            KEY idx_asin (asin),
            KEY idx_network (network),
            KEY idx_post_id (post_id),
            KEY idx_date_recorded (date_recorded)
        ) {$charset_collate};";

        // Click Tracking Table (StatisticsTracker)
        $click_tracking_table = $wpdb->prefix . 'wpb_click_tracking';
        $click_tracking_sql = "CREATE TABLE {$click_tracking_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            asin VARCHAR(100) NOT NULL,
            network VARCHAR(20) NOT NULL DEFAULT 'amazon',
            post_id BIGINT(20) UNSIGNED DEFAULT NULL,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            ip_hash VARCHAR(64) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            referrer VARCHAR(2083) DEFAULT NULL,
            metadata LONGTEXT DEFAULT NULL,
            clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_asin (asin),
            KEY idx_network (network),
            KEY idx_post_id (post_id),
            KEY idx_clicked_at (clicked_at)
        ) {$charset_collate};";

        // WooCommerce Product Sync Table
        $woo_sync_table = $wpdb->prefix . 'wpb_woo_sync';
        $woo_sync_sql = "CREATE TABLE {$woo_sync_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            asin VARCHAR(100) NOT NULL,
            network VARCHAR(20) NOT NULL DEFAULT 'amazon',
            product_id BIGINT(20) UNSIGNED NOT NULL,
            last_synced DATETIME NOT NULL,
            sync_fields LONGTEXT DEFAULT NULL,
            auto_sync TINYINT(1) DEFAULT 1,
            sync_interval VARCHAR(20) DEFAULT 'daily',
            next_sync DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_asin_network (asin, network),
            UNIQUE KEY idx_product_id (product_id),
            KEY idx_next_sync (next_sync),
            KEY idx_auto_sync (auto_sync)
        ) {$charset_collate};";

        // Include WordPress upgrade functions
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Run the table creation
        dbDelta($content_history_sql);
        dbDelta($product_cache_sql);
        dbDelta($templates_sql);
        dbDelta($api_usage_sql);
        dbDelta($import_jobs_sql);
        dbDelta($import_queue_sql);
        dbDelta($import_log_sql);
        dbDelta($content_jobs_sql);
        dbDelta($content_queue_sql);
        dbDelta($product_stats_sql);
        dbDelta($click_tracking_sql);
        dbDelta($woo_sync_sql);

        // Insert default templates
        self::insertDefaultTemplates();

        // Run version-specific migrations for existing installs
        self::runMigrations();

        // Update DB version
        update_option('wpb_db_version', self::DB_VERSION);
    }

    /**
     * Run incremental migrations based on current DB version
     */
    private static function runMigrations(): void {
        $currentVersion = get_option('wpb_db_version', '1.0');

        if (version_compare($currentVersion, '2.0', '<')) {
            self::migrateToV2();
        }
    }

    /**
     * V2 Migration: Add network support columns to existing tables
     *
     * For fresh installs, the CREATE TABLE statements already include these columns.
     * This migration handles upgrades from v1.x where tables already exist.
     */
    private static function migrateToV2(): void {
        global $wpdb;

        // Widen asin columns and add network columns to existing tables
        $tables_to_update = [
            'wpb_product_cache',
            'wpb_import_queue',
            'wpb_import_log',
            'wpb_product_stats',
            'wpb_click_tracking',
            'wpb_woo_sync',
        ];

        foreach ($tables_to_update as $table) {
            $full_table = $wpdb->prefix . $table;

            // Check if network column already exists
            $column_exists = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$full_table} LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    'network'
                )
            );

            if (empty($column_exists)) {
                // Widen asin column to support longer product IDs
                $wpdb->query("ALTER TABLE {$full_table} MODIFY asin VARCHAR(100) NOT NULL"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                // Add network column
                $wpdb->query("ALTER TABLE {$full_table} ADD COLUMN network VARCHAR(20) NOT NULL DEFAULT 'amazon' AFTER asin"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                // Add network index
                $wpdb->query("ALTER TABLE {$full_table} ADD KEY idx_network (network)"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
        }

        // Update product_cache unique key: (asin, marketplace) -> (asin, network, marketplace)
        $product_cache = $wpdb->prefix . 'wpb_product_cache';
        $existing_keys = $wpdb->get_results("SHOW INDEX FROM {$product_cache} WHERE Key_name = 'idx_asin_marketplace'"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (!empty($existing_keys)) {
            $wpdb->query("ALTER TABLE {$product_cache} DROP KEY idx_asin_marketplace"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("ALTER TABLE {$product_cache} ADD UNIQUE KEY idx_asin_network_marketplace (asin, network, marketplace)"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        // Update product_stats unique key
        $product_stats = $wpdb->prefix . 'wpb_product_stats';
        $existing_keys = $wpdb->get_results("SHOW INDEX FROM {$product_stats} WHERE Key_name = 'idx_asin_post_date'"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (!empty($existing_keys)) {
            $wpdb->query("ALTER TABLE {$product_stats} DROP KEY idx_asin_post_date"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("ALTER TABLE {$product_stats} ADD UNIQUE KEY idx_asin_network_post_date (asin, network, post_id, date_recorded)"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        // Update woo_sync unique key
        $woo_sync = $wpdb->prefix . 'wpb_woo_sync';
        $existing_keys = $wpdb->get_results("SHOW INDEX FROM {$woo_sync} WHERE Key_name = 'idx_asin'"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (!empty($existing_keys)) {
            $wpdb->query("ALTER TABLE {$woo_sync} DROP KEY idx_asin"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("ALTER TABLE {$woo_sync} ADD UNIQUE KEY idx_asin_network (asin, network)"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        // Add network column to content_queue
        $content_queue = $wpdb->prefix . 'wpb_content_queue';
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$content_queue} LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                'network'
            )
        );
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$content_queue} ADD COLUMN network VARCHAR(20) NOT NULL DEFAULT 'amazon' AFTER asins"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }

    /**
     * Insert default content templates
     */
    private static function insertDefaultTemplates(): void {
        global $wpdb;

        $templates_table = $wpdb->prefix . 'wpb_templates';

        // Check if defaults already exist
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM {$templates_table} WHERE is_default = 1");
        if ($existing > 0) {
            return;
        }

        $default_templates = [
            [
                'name' => 'Standard Product Review',
                'slug' => 'standard-product-review',
                'content_type' => 'product_review',
                'is_default' => 1,
            ],
            [
                'name' => 'Standard Products Roundup',
                'slug' => 'standard-products-roundup',
                'content_type' => 'products_roundup',
                'is_default' => 1,
            ],
            [
                'name' => 'Standard Products Comparison',
                'slug' => 'standard-products-comparison',
                'content_type' => 'products_comparison',
                'is_default' => 1,
            ],
            [
                'name' => 'Standard Listicle',
                'slug' => 'standard-listicle',
                'content_type' => 'listicle',
                'is_default' => 1,
            ],
            [
                'name' => 'Standard Deals',
                'slug' => 'standard-deals',
                'content_type' => 'deals',
                'is_default' => 1,
            ],
        ];

        foreach ($default_templates as $template) {
            $wpdb->insert(
                $templates_table,
                [
                    'name' => $template['name'],
                    'slug' => $template['slug'],
                    'content_type' => $template['content_type'],
                    'template_content' => '',
                    'prompt_template' => '',
                    'is_default' => $template['is_default'],
                    'is_active' => 1,
                    'created_by' => get_current_user_id() ?: 1,
                ],
                ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d']
            );
        }
    }

    /**
     * Drop all plugin tables (for uninstall)
     */
    public static function dropTables(): void {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'wpb_click_tracking',
            $wpdb->prefix . 'wpb_product_stats',
            $wpdb->prefix . 'wpb_content_queue',
            $wpdb->prefix . 'wpb_content_jobs',
            $wpdb->prefix . 'wpb_import_log',
            $wpdb->prefix . 'wpb_import_queue',
            $wpdb->prefix . 'wpb_import_jobs',
            $wpdb->prefix . 'wpb_woo_sync',
            $wpdb->prefix . 'wpb_content_history',
            $wpdb->prefix . 'wpb_product_cache',
            $wpdb->prefix . 'wpb_templates',
            $wpdb->prefix . 'wpb_api_usage',
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }

    /**
     * Get table names
     */
    public static function getTableNames(): array {
        global $wpdb;

        return [
            'content_history' => $wpdb->prefix . 'wpb_content_history',
            'product_cache' => $wpdb->prefix . 'wpb_product_cache',
            'templates' => $wpdb->prefix . 'wpb_templates',
            'api_usage' => $wpdb->prefix . 'wpb_api_usage',
            'import_jobs' => $wpdb->prefix . 'wpb_import_jobs',
            'import_queue' => $wpdb->prefix . 'wpb_import_queue',
            'import_log' => $wpdb->prefix . 'wpb_import_log',
            'content_jobs' => $wpdb->prefix . 'wpb_content_jobs',
            'content_queue' => $wpdb->prefix . 'wpb_content_queue',
            'product_stats' => $wpdb->prefix . 'wpb_product_stats',
            'click_tracking' => $wpdb->prefix . 'wpb_click_tracking',
            'woo_sync' => $wpdb->prefix . 'wpb_woo_sync',
        ];
    }
}
