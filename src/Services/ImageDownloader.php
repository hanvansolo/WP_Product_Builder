<?php
/**
 * Image Downloader Service
 *
 * Downloads and stores Amazon product images locally
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Services;

/**
 * Handles downloading and storing images locally
 */
class ImageDownloader {
    /**
     * Download image and attach to post
     *
     * @param string $url Image URL
     * @param int $postId Post to attach to
     * @param string $title Image title
     * @return int|null Attachment ID or null on failure
     */
    public function downloadAndAttach(string $url, int $postId, string $title = ''): ?int {
        if (empty($url)) {
            return null;
        }

        // Check if already downloaded
        $existingId = $this->getExistingAttachment($url);
        if ($existingId) {
            return $existingId;
        }

        // Include WordPress media functions
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Download file
        $tempFile = download_url($url, 30);

        if (is_wp_error($tempFile)) {
            error_log('WPB Image Download Error: ' . $tempFile->get_error_message());
            return null;
        }

        // Prepare file array
        $fileName = $this->generateFileName($url, $title);

        $file = [
            'name' => $fileName,
            'tmp_name' => $tempFile,
        ];

        // Upload to WordPress media library
        $attachmentId = media_handle_sideload($file, $postId, $title);

        // Clean up temp file
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }

        if (is_wp_error($attachmentId)) {
            error_log('WPB Media Upload Error: ' . $attachmentId->get_error_message());
            return null;
        }

        // Store original URL for reference
        update_post_meta($attachmentId, '_wpb_source_url', $url);

        return $attachmentId;
    }

    /**
     * Bulk download images
     *
     * @param array $images Array of ['url' => '', 'post_id' => 0, 'title' => '']
     * @return array Results
     */
    public function downloadBulk(array $images): array {
        $results = [];

        foreach ($images as $image) {
            $attachmentId = $this->downloadAndAttach(
                $image['url'],
                $image['post_id'] ?? 0,
                $image['title'] ?? ''
            );

            $results[] = [
                'url' => $image['url'],
                'attachment_id' => $attachmentId,
                'success' => $attachmentId !== null,
            ];
        }

        return $results;
    }

    /**
     * Check if image already downloaded
     */
    private function getExistingAttachment(string $url): ?int {
        global $wpdb;

        $attachmentId = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_wpb_source_url' AND meta_value = %s
             LIMIT 1",
            $url
        ));

        return $attachmentId ? (int) $attachmentId : null;
    }

    /**
     * Generate filename from URL and title
     */
    private function generateFileName(string $url, string $title): string {
        // Try to get extension from URL
        $pathInfo = pathinfo(parse_url($url, PHP_URL_PATH));
        $extension = $pathInfo['extension'] ?? 'jpg';

        // Clean extension
        $extension = preg_replace('/[^a-z]/', '', strtolower($extension));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $extension = 'jpg';
        }

        // Generate slug from title
        if (!empty($title)) {
            $slug = sanitize_title($title);
            $slug = substr($slug, 0, 50);
        } else {
            $slug = 'product-image-' . uniqid();
        }

        return $slug . '.' . $extension;
    }

    /**
     * Get optimal image URL from Amazon
     *
     * Amazon images can be resized by changing the URL
     * E.g., ._SL500_ for 500px, ._SL1500_ for 1500px
     *
     * @param string $url Original Amazon image URL
     * @param int $size Desired size
     * @return string Optimized URL
     */
    public function getOptimizedImageUrl(string $url, int $size = 1000): string {
        // Pattern to match Amazon image size parameters
        $pattern = '/\._[A-Z]{2}\d+_\./';

        if (preg_match($pattern, $url)) {
            return preg_replace($pattern, "._SL{$size}_.", $url);
        }

        // Try adding size parameter
        if (str_contains($url, 'images-amazon.com') || str_contains($url, 'media-amazon.com')) {
            $url = preg_replace('/\.(jpg|jpeg|png|gif)$/i', "._SL{$size}_.$1", $url);
        }

        return $url;
    }

    /**
     * Clean up orphaned downloaded images
     */
    public function cleanupOrphanedImages(): int {
        global $wpdb;

        // Find attachments with _wpb_source_url that have no parent
        $orphaned = $wpdb->get_col(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key = '_wpb_source_url'
             AND p.post_parent = 0
             AND p.post_type = 'attachment'"
        );

        $deleted = 0;

        foreach ($orphaned as $attachmentId) {
            if (wp_delete_attachment($attachmentId, true)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
