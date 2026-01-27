<?php
/**
 * Content Repository
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Database\Repositories;

/**
 * Repository for content history operations
 */
class ContentRepository {
    /**
     * Table name
     */
    private string $table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wpb_content_history';
    }

    /**
     * Get content by ID
     *
     * @param int $id Content ID
     * @return array|null
     */
    public function get(int $id): ?array {
        global $wpdb;

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);

        return $result ?: null;
    }

    /**
     * Get all content with pagination
     *
     * @param int $page Page number
     * @param int $per_page Items per page
     * @return array
     */
    public function getAll(int $page = 1, int $per_page = 20): array {
        global $wpdb;

        $offset = ($page - 1) * $per_page;

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT h.*, u.display_name as author_name
             FROM {$this->table} h
             LEFT JOIN {$wpdb->users} u ON h.user_id = u.ID
             ORDER BY h.created_at DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ), ARRAY_A);

        return [
            'items' => $items,
            'total' => (int) $total,
            'pages' => ceil($total / $per_page),
        ];
    }

    /**
     * Save content
     *
     * @param array $data Content data
     * @return int|false Insert ID or false on failure
     */
    public function save(array $data): int|false {
        global $wpdb;

        $insert_data = [
            'user_id' => get_current_user_id(),
            'content_type' => $data['content_type'],
            'title' => $data['title'],
            'prompt_used' => $data['prompt_used'],
            'products_json' => $data['products_json'],
            'generated_content' => $data['generated_content'],
            'template_id' => $data['template_id'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'tokens_used' => $data['tokens_used'] ?? 0,
            'generation_cost' => $data['generation_cost'] ?? 0,
            'model_used' => $data['model_used'],
        ];

        $result = $wpdb->insert($this->table, $insert_data);

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update content
     *
     * @param int $id Content ID
     * @param array $data Data to update
     * @return bool
     */
    public function update(int $id, array $data): bool {
        global $wpdb;

        $allowed_fields = [
            'post_id', 'title', 'generated_content', 'status', 'template_id'
        ];

        $update_data = array_intersect_key($data, array_flip($allowed_fields));

        if (empty($update_data)) {
            return false;
        }

        $result = $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id]
        );

        return $result !== false;
    }

    /**
     * Delete content
     *
     * @param int $id Content ID
     * @return bool
     */
    public function delete(int $id): bool {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get content by post ID
     *
     * @param int $post_id WordPress post ID
     * @return array|null
     */
    public function getByPostId(int $post_id): ?array {
        global $wpdb;

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE post_id = %d",
            $post_id
        ), ARRAY_A);

        return $result ?: null;
    }

    /**
     * Get user's content
     *
     * @param int $user_id User ID
     * @param int $limit Limit
     * @return array
     */
    public function getByUser(int $user_id, int $limit = 10): array {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE user_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A);
    }

    /**
     * Get statistics
     *
     * @return array
     */
    public function getStats(): array {
        global $wpdb;

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
        $published = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status = 'published'");
        $draft = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status = 'draft'");
        $total_tokens = $wpdb->get_var("SELECT SUM(tokens_used) FROM {$this->table}");

        $by_type = $wpdb->get_results(
            "SELECT content_type, COUNT(*) as count FROM {$this->table} GROUP BY content_type",
            ARRAY_A
        );

        return [
            'total' => (int) $total,
            'published' => (int) $published,
            'draft' => (int) $draft,
            'total_tokens' => (int) ($total_tokens ?? 0),
            'by_type' => $by_type,
        ];
    }
}
