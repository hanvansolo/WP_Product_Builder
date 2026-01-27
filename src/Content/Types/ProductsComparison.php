<?php
/**
 * Products Comparison Content Type
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Content\Types;

use WPProductBuilder\Content\ContentTypeInterface;

/**
 * Products comparison content generator
 */
class ProductsComparison implements ContentTypeInterface {
    /**
     * @inheritDoc
     */
    public function getType(): string {
        return 'products_comparison';
    }

    /**
     * @inheritDoc
     */
    public function getName(): string {
        return __('Products Comparison', 'wp-product-builder');
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string {
        return __('Compare 2-4 products side by side with feature comparison.', 'wp-product-builder');
    }

    /**
     * @inheritDoc
     */
    public function buildPrompt(array $products, array $options): string {
        $count = count($products);
        $tone = $options['tone'] ?? 'professional';
        $length = $this->getLengthGuidance($options['length'] ?? 'medium');

        // Build product names for title
        $productNames = array_map(fn($p) => $this->shortenTitle($p['title']), $products);
        $vsTitle = implode(' vs ', $productNames);

        $prompt = "Write a detailed comparison article: **{$vsTitle}**\n\n";
        $prompt .= "**Products to Compare:**\n\n";

        foreach ($products as $index => $product) {
            $prompt .= "**Product " . ($index + 1) . ":** {$product['title']}\n";
            if (!empty($product['brand'])) {
                $prompt .= "  Brand: {$product['brand']}\n";
            }
            if (!empty($product['price'])) {
                $prompt .= "  Price: {$product['price']}\n";
            }
            if (!empty($product['features'])) {
                $prompt .= "  Features:\n";
                foreach (array_slice($product['features'], 0, 5) as $feature) {
                    $prompt .= "    - {$feature}\n";
                }
            }
            $prompt .= "\n";
        }

        if (!empty($options['focus_keywords'])) {
            $keywords = implode(', ', $options['focus_keywords']);
            $prompt .= "**Focus Keywords:** {$keywords}\n\n";
        }

        $prompt .= "**Writing Instructions:**\n";
        $prompt .= "- Write in a {$tone} tone\n";
        $prompt .= "- {$length}\n";
        $prompt .= "- Format output in HTML with proper heading tags (h2, h3)\n";
        $prompt .= "- Structure the article as follows:\n\n";

        $prompt .= "1. **Introduction** - Brief overview of what we're comparing and why\n";
        $prompt .= "2. **Quick Comparison Table** - Create an HTML table comparing key specs/features\n";
        $prompt .= "3. **Individual Product Overviews** - Brief section for each product\n";
        $prompt .= "4. **Head-to-Head Comparison** - Compare them on key aspects:\n";
        $prompt .= "   - Design and Build Quality\n";
        $prompt .= "   - Features and Performance\n";
        $prompt .= "   - Value for Money\n";
        $prompt .= "5. **Who Should Buy Which?** - Recommendations based on use case\n";
        $prompt .= "6. **Verdict** - Clear winner (if any) with reasoning\n\n";

        if ($options['include_faq'] ?? true) {
            $prompt .= "7. **FAQ Section** - 3-5 questions comparing these specific products\n\n";
        }

        $prompt .= "- Include [BUY_BUTTON_X] placeholder for each product (X = product index from 0)\n";
        $prompt .= "- Be objective and balanced in the comparison\n";
        $prompt .= "\nWrite the comparison article now:";

        return $prompt;
    }

    /**
     * @inheritDoc
     */
    public function generateTitle(array $products, array $options): string {
        $names = array_map(fn($p) => $this->shortenTitle($p['title']), $products);

        if (count($names) === 2) {
            return "{$names[0]} vs {$names[1]}: Which Is Better?";
        }

        $last = array_pop($names);
        return implode(', ', $names) . " vs {$last}: Complete Comparison";
    }

    /**
     * Shorten product title for comparison
     *
     * @param string $title Full title
     * @return string Shortened title
     */
    private function shortenTitle(string $title): string {
        // Remove common suffixes and keep it short
        $title = preg_replace('/\s*\([^)]+\)\s*$/', '', $title);
        $title = preg_replace('/\s*-\s*[^-]+$/', '', $title);

        if (strlen($title) > 30) {
            $words = explode(' ', $title);
            $title = implode(' ', array_slice($words, 0, 4));
        }

        return trim($title);
    }

    /**
     * Get length guidance
     *
     * @param string $length Length setting
     * @return string Guidance text
     */
    private function getLengthGuidance(string $length): string {
        return match($length) {
            'short' => 'Write approximately 1000-1500 words',
            'medium' => 'Write approximately 2000-2500 words',
            'long' => 'Write approximately 3000-4000 words',
            default => 'Write approximately 2000-2500 words',
        };
    }
}
