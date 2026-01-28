<?php
/**
 * Content Generator
 *
 * Main orchestrator for content generation
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Content;

use WPProductBuilder\API\ClaudeClient;
use WPProductBuilder\Services\ProductDataService;
use WPProductBuilder\Database\Repositories\ContentRepository;
use WPProductBuilder\Content\Types\ProductReview;
use WPProductBuilder\Content\Types\ProductsRoundup;
use WPProductBuilder\Content\Types\ProductsComparison;
use WPProductBuilder\Content\Types\Listicle;
use WPProductBuilder\Content\Types\Deals;
use WP_Error;

/**
 * Orchestrates content generation using AI and product data
 */
class ContentGenerator {
    /**
     * Claude API client
     */
    private ClaudeClient $claude;

    /**
     * Product data service
     */
    private ProductDataService $productService;

    /**
     * Content repository
     */
    private ContentRepository $contentRepo;

    /**
     * Available content types
     */
    private array $contentTypes;

    /**
     * Constructor
     */
    public function __construct() {
        $this->claude = new ClaudeClient();
        $this->productService = new ProductDataService();
        $this->contentRepo = new ContentRepository();

        $this->contentTypes = [
            'product_review' => new ProductReview(),
            'products_roundup' => new ProductsRoundup(),
            'products_comparison' => new ProductsComparison(),
            'listicle' => new Listicle(),
            'deals' => new Deals(),
        ];
    }

    /**
     * Generate content
     *
     * @param string $type Content type
     * @param array $productAsins Array of product ASINs
     * @param array $options Generation options
     * @return array Result with content and metadata
     */
    public function generate(string $type, array $productAsins, array $options = []): array {
        // Validate content type
        if (!isset($this->contentTypes[$type])) {
            return [
                'success' => false,
                'error' => __('Invalid content type.', 'wp-product-builder'),
            ];
        }

        $contentType = $this->contentTypes[$type];

        // Fetch product data using service (with scraper fallback)
        $products = $this->productService->getMultipleProducts($productAsins);

        if (empty($products)) {
            return [
                'success' => false,
                'error' => __('Could not fetch product data. Please try again.', 'wp-product-builder'),
            ];
        }

        $products = array_values($products);

        // Build prompt
        $prompt = $contentType->buildPrompt($products, $options);

        // Get generation options
        $claudeOptions = [
            'max_tokens' => $this->getMaxTokens($type, $options),
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        // Generate with Claude
        $result = $this->claude->generateContent($prompt, $claudeOptions);

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? __('Content generation failed.', 'wp-product-builder'),
            ];
        }

        // Process and format content
        $content = $this->processContent($result['content'], $products);

        // Generate title
        $title = $options['title'] ?? $contentType->generateTitle($products, $options);

        // Save to history
        $historyId = $this->contentRepo->save([
            'content_type' => $type,
            'title' => $title,
            'prompt_used' => $prompt,
            'products_json' => json_encode($products),
            'generated_content' => $content,
            'tokens_used' => ($result['usage']['input_tokens'] ?? 0) + ($result['usage']['output_tokens'] ?? 0),
            'model_used' => $result['model'] ?? 'unknown',
        ]);

        return [
            'success' => true,
            'content' => $content,
            'title' => $title,
            'products' => $products,
            'history_id' => $historyId,
            'usage' => $result['usage'] ?? null,
        ];
    }

    /**
     * Create WordPress post from history
     *
     * @param int $historyId History ID
     * @param array $postOptions Post options
     * @return int|WP_Error Post ID or error
     */
    public function createPost(int $historyId, array $postOptions = []): int|WP_Error {
        $history = $this->contentRepo->get($historyId);

        if (!$history) {
            return new WP_Error('not_found', __('Content history not found.', 'wp-product-builder'));
        }

        $settings = get_option('wpb_settings', []);

        // Prepare post data
        $postData = [
            'post_title' => $history['title'],
            'post_content' => $history['generated_content'],
            'post_status' => $postOptions['status'] ?? ($settings['default_post_status'] ?? 'draft'),
            'post_author' => $postOptions['author'] ?? get_current_user_id(),
            'post_type' => 'post',
        ];

        if (!empty($postOptions['categories'])) {
            $postData['post_category'] = $postOptions['categories'];
        }

        // Insert post
        $postId = wp_insert_post($postData);

        if (is_wp_error($postId)) {
            return $postId;
        }

        // Save post meta
        update_post_meta($postId, '_wpb_content_type', $history['content_type']);
        update_post_meta($postId, '_wpb_products', json_decode($history['products_json'], true));
        update_post_meta($postId, '_wpb_generation_id', $historyId);
        update_post_meta($postId, '_wpb_last_price_check', current_time('timestamp'));

        // Add affiliate disclosure if enabled
        if (!empty($settings['affiliate_disclosure'])) {
            $disclosure = '<p class="wpb-affiliate-disclosure"><em>' . esc_html($settings['affiliate_disclosure']) . '</em></p>';
            $updated_content = $disclosure . "\n\n" . $history['generated_content'];
            wp_update_post([
                'ID' => $postId,
                'post_content' => $updated_content,
            ]);
        }

        // Update history with post ID
        $this->contentRepo->update($historyId, [
            'post_id' => $postId,
            'status' => 'published',
        ]);

        return $postId;
    }

    /**
     * Process content - replace placeholders and format
     *
     * @param string $content Raw content
     * @param array $products Products data
     * @return string Processed content
     */
    private function processContent(string $content, array $products): string {
        // Convert markdown to HTML if needed
        if (!str_contains($content, '<h2>') && !str_contains($content, '<p>')) {
            $content = $this->markdownToHtml($content);
        }

        // Replace product box placeholders (handle various bracket styles Claude might use)
        // Matches: [PRODUCT_BOX_0], 【PRODUCT_BOX_0】, ［PRODUCT_BOX_0］, etc.
        $content = preg_replace_callback(
            '/[\[【［\(]\s*PRODUCT[_\s-]*BOX[_\s-]*(\d+)\s*[\]】］\)]/iu',
            function($matches) use ($products) {
                $index = (int) $matches[1];
                if (isset($products[$index])) {
                    return $this->renderProductBox($products[$index]);
                }
                return '';
            },
            $content
        );

        // Replace buy button placeholders (handle various bracket styles)
        $content = preg_replace_callback(
            '/[\[【［\(]\s*BUY[_\s-]*BUTTON[_\s-]*(\d+)\s*[\]】］\)]/iu',
            function($matches) use ($products) {
                $index = (int) $matches[1];
                if (isset($products[$index])) {
                    return $this->renderBuyButton($products[$index]);
                }
                return '';
            },
            $content
        );

        // Replace price placeholders
        $content = preg_replace_callback(
            '/[\[【［\(]\s*PRICE[_\s-]*(\d+)\s*[\]】］\)]/iu',
            function($matches) use ($products) {
                $index = (int) $matches[1];
                if (isset($products[$index])) {
                    return $products[$index]['price'] ?? 'Check price';
                }
                return 'Check price';
            },
            $content
        );

        // If no product boxes were inserted, add them at the beginning for each product
        if (!str_contains($content, 'wpb-product-box')) {
            $productBoxes = '';
            foreach ($products as $product) {
                $productBoxes .= $this->renderProductBox($product);
            }
            $content = $productBoxes . $content;
        }

        return $content;
    }

    /**
     * Render product box HTML
     *
     * @param array $product Product data
     * @return string HTML
     */
    private function renderProductBox(array $product): string {
        $affiliateUrl = $product['affiliate_url'] ?? $this->buildAffiliateUrl($product['asin'] ?? '');

        $html = '<div class="wpb-product-box">';

        // Product Image (linked to Amazon)
        $html .= '<div class="wpb-product-image">';
        $html .= '<a href="' . esc_url($affiliateUrl) . '" rel="nofollow sponsored" target="_blank">';
        if (!empty($product['image_url'])) {
            $html .= '<img src="' . esc_url($product['image_url']) . '" alt="' . esc_attr($product['title'] ?? '') . '">';
        } else {
            $html .= '<div style="width:200px;height:200px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;color:#999;">No Image</div>';
        }
        $html .= '</a>';
        $html .= '</div>';

        // Product Details
        $html .= '<div class="wpb-product-details">';

        // Title (linked)
        $html .= '<h3 class="wpb-product-title">';
        $html .= '<a href="' . esc_url($affiliateUrl) . '" rel="nofollow sponsored" target="_blank">';
        $html .= esc_html($product['title'] ?? 'Product');
        $html .= '</a></h3>';

        // Rating
        if (!empty($product['rating'])) {
            $rating = (float) $product['rating'];
            $fullStars = (int) floor($rating);
            $emptyStars = 5 - $fullStars;

            $html .= '<div class="wpb-product-rating">';
            $html .= '<span class="stars">' . str_repeat('★', $fullStars) . str_repeat('☆', $emptyStars) . '</span>';
            $html .= ' <span class="rating-value">' . number_format($rating, 1) . '</span>';
            if (!empty($product['review_count'])) {
                $html .= ' <span class="count">(' . number_format((int) $product['review_count']) . ')</span>';
            }
            $html .= '</div>';
        }

        // Price
        if (!empty($product['price'])) {
            $html .= '<p class="wpb-product-price">' . esc_html($product['price']) . '</p>';
        }

        // Features (first 3)
        if (!empty($product['features']) && is_array($product['features'])) {
            $html .= '<ul class="wpb-product-features">';
            foreach (array_slice($product['features'], 0, 3) as $feature) {
                $html .= '<li>' . esc_html($feature) . '</li>';
            }
            $html .= '</ul>';
        }

        // Buy Button
        $html .= $this->renderBuyButton($product);
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Render buy button
     *
     * @param array $product Product data
     * @return string HTML
     */
    private function renderBuyButton(array $product): string {
        $url = $product['affiliate_url'] ?? $this->buildAffiliateUrl($product['asin']);
        return sprintf(
            '<a href="%s" class="wpb-buy-button" rel="nofollow sponsored" target="_blank">%s</a>',
            esc_url($url),
            esc_html__('Check Price on Amazon', 'wp-product-builder')
        );
    }

    /**
     * Basic markdown to HTML conversion
     *
     * @param string $markdown Markdown text
     * @return string HTML
     */
    private function markdownToHtml(string $markdown): string {
        // Headers
        $markdown = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $markdown);
        $markdown = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $markdown);
        $markdown = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $markdown);

        // Bold and italic
        $markdown = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $markdown);
        $markdown = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $markdown);

        // Lists
        $markdown = preg_replace('/^- (.+)$/m', '<li>$1</li>', $markdown);
        $markdown = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $markdown);

        // Paragraphs
        $markdown = preg_replace('/^(?!<[hul])(.*[^\n])$/m', '<p>$1</p>', $markdown);

        // Clean up
        $markdown = preg_replace('/\n+/', "\n", $markdown);

        return $markdown;
    }

    /**
     * Get max tokens based on content type and length
     *
     * @param string $type Content type
     * @param array $options Options
     * @return int Max tokens
     */
    private function getMaxTokens(string $type, array $options): int {
        $length = $options['length'] ?? 'medium';

        $base = match($length) {
            'short' => 2048,
            'medium' => 4096,
            'long' => 8192,
            default => 4096,
        };

        // Adjust for content types that need more tokens
        $multiplier = match($type) {
            'products_comparison', 'products_roundup' => 1.5,
            'listicle' => 1.3,
            default => 1.0,
        };

        return min((int) ($base * $multiplier), 16384);
    }

    /**
     * Get content type instance
     *
     * @param string $type Content type key
     * @return ContentTypeInterface|null
     */
    public function getContentType(string $type): ?ContentTypeInterface {
        return $this->contentTypes[$type] ?? null;
    }

    /**
     * Get all content types
     *
     * @return array
     */
    public function getAllContentTypes(): array {
        $types = [];
        foreach ($this->contentTypes as $key => $type) {
            $types[$key] = [
                'type' => $type->getType(),
                'name' => $type->getName(),
                'description' => $type->getDescription(),
            ];
        }
        return $types;
    }

    /**
     * Build Amazon affiliate URL
     *
     * @param string $asin Product ASIN
     * @return string Affiliate URL
     */
    private function buildAffiliateUrl(string $asin): string {
        $settings = get_option('wpb_settings', []);
        $credentials = get_option('wpb_credentials_encrypted', []);

        $marketplace = $settings['amazon_marketplace'] ?? 'US';
        $partnerTag = $credentials['amazon_partner_tag'] ?? '';

        $domains = [
            'US' => 'amazon.com', 'UK' => 'amazon.co.uk', 'DE' => 'amazon.de',
            'FR' => 'amazon.fr', 'CA' => 'amazon.ca', 'JP' => 'amazon.co.jp',
            'IT' => 'amazon.it', 'ES' => 'amazon.es', 'AU' => 'amazon.com.au',
        ];

        $domain = $domains[$marketplace] ?? 'amazon.com';
        $url = "https://www.{$domain}/dp/{$asin}";

        if (!empty($partnerTag)) {
            $url .= "?tag={$partnerTag}";
        }

        return $url;
    }
}
