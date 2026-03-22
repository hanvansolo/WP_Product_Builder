<?php
/**
 * Product Review Content Type
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Content\Types;

use WPProductBuilder\Content\ContentTypeInterface;

/**
 * Product review content generator
 */
class ProductReview implements ContentTypeInterface {
    /**
     * @inheritDoc
     */
    public function getType(): string {
        return 'product_review';
    }

    /**
     * @inheritDoc
     */
    public function getName(): string {
        return __('Product Review', 'wp-product-builder');
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string {
        return __('Detailed review of a single product with pros, cons, and verdict.', 'wp-product-builder');
    }

    /**
     * @inheritDoc
     */
    public function buildPrompt(array $products, array $options): string {
        $product = $products[0];

        $tone = $options['tone'] ?? 'professional';
        $length = $this->getLengthGuidance($options['length'] ?? 'medium');

        $prompt = "Write a comprehensive product review for the following product:\n\n";
        $prompt .= "**Product:** {$product['title']}\n";

        if (!empty($product['brand'])) {
            $prompt .= "**Brand:** {$product['brand']}\n";
        }

        if (!empty($product['price'])) {
            $prompt .= "**Price:** {$product['price']}\n";
        }

        if (!empty($product['features'])) {
            $prompt .= "**Key Features:**\n";
            foreach ($product['features'] as $feature) {
                $prompt .= "- {$feature}\n";
            }
        }

        if (!empty($options['focus_keywords'])) {
            $keywords = implode(', ', $options['focus_keywords']);
            $prompt .= "\n**Focus Keywords:** {$keywords}\n";
        }

        $prompt .= "\n**Writing Instructions:**\n";
        $prompt .= "- Write in a {$tone} tone\n";
        $prompt .= "- {$length}\n";
        $prompt .= "- Format output in HTML with proper heading tags (h2, h3)\n";
        $prompt .= "- Include an engaging introduction\n";
        $prompt .= "- Based on your knowledge, include what real reviewers and publications have said about this product or similar products from the same brand\n";
        $prompt .= "- Attribute opinions to specific sources naturally throughout the article, for example:\n";
        $prompt .= "  - 'According to TechRadar, this product excels at...'\n";
        $prompt .= "  - 'Wirecutter noted that buyers should consider...'\n";
        $prompt .= "  - 'CNET reviewers highlighted the...'\n";
        $prompt .= "  - 'Tom's Guide praised the build quality...'\n";
        $prompt .= "  - 'Many users on Amazon report that...'\n";
        $prompt .= "  - 'Reddit users in r/BuyItForLife recommend...'\n";
        $prompt .= "- Reference at least 3-4 different review sources throughout the article\n";
        $prompt .= "- If you don't have specific review data for this exact product, reference reviews of the brand's product line or the product category from known publications\n";
        $prompt .= "- Make the citations feel natural and integrated, not forced\n";

        if ($options['include_pros_cons'] ?? true) {
            $prompt .= "- Include a Pros and Cons section with bullet points\n";
        }

        if ($options['include_faq'] ?? true) {
            $prompt .= "- Include an FAQ section with 3-5 common questions\n";
        }

        if ($options['include_verdict'] ?? true) {
            $prompt .= "- Include a Final Verdict section with a recommendation\n";
        }

        if ($options['include_buying_guide'] ?? false) {
            $prompt .= "- Include a brief buying guide section\n";
        }

        $prompt .= "- Place [PRODUCT_BOX_0] near the top of the review to display the product image, price, and buy button\n";
        $prompt .= "- Place [BUY_BUTTON_0] at the end of the review for a final call-to-action\n";
        $prompt .= "- IMPORTANT: Use only standard square brackets [ ] for placeholders. Never use 【】 or ［］\n";
        $prompt .= "\nWrite the review now:";

        return $prompt;
    }

    /**
     * @inheritDoc
     */
    public function generateTitle(array $products, array $options): string {
        $product = $products[0];
        $title = $product['title'] ?? 'Product';

        // Shorten title if too long
        if (strlen($title) > 50) {
            $title = substr($title, 0, 47) . '...';
        }

        $templates = [
            "{$title} Review: Is It Worth Buying?",
            "Honest {$title} Review - What You Need to Know",
            "{$title} Review: Our Complete Analysis",
        ];

        return $templates[array_rand($templates)];
    }

    /**
     * Get length guidance
     *
     * @param string $length Length setting
     * @return string Guidance text
     */
    private function getLengthGuidance(string $length): string {
        return match($length) {
            'short' => 'Write a concise review of 500-800 words',
            'medium' => 'Write a comprehensive review of 1000-1500 words',
            'long' => 'Write an in-depth, detailed review of 2000+ words',
            default => 'Write a comprehensive review of 1000-1500 words',
        };
    }
}
