<?php
/**
 * Products Roundup Content Type
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Content\Types;

use WPProductBuilder\Content\ContentTypeInterface;

/**
 * Products roundup content generator
 */
class ProductsRoundup implements ContentTypeInterface {
    /**
     * @inheritDoc
     */
    public function getType(): string {
        return 'products_roundup';
    }

    /**
     * @inheritDoc
     */
    public function getName(): string {
        return __('Products Roundup', 'wp-product-builder');
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string {
        return __('Best X products in a category with brief descriptions.', 'wp-product-builder');
    }

    /**
     * @inheritDoc
     */
    public function buildPrompt(array $products, array $options): string {
        $count = count($products);
        $tone = $options['tone'] ?? 'professional';
        $length = $this->getLengthGuidance($options['length'] ?? 'medium', $count);

        $category = $products[0]['category'] ?? 'products';

        $prompt = "Write a \"Best {$count} {$category}\" roundup article.\n\n";
        $prompt .= "**Products to Review:**\n\n";

        foreach ($products as $index => $product) {
            $prompt .= "**Product " . ($index + 1) . ":** {$product['title']}\n";
            if (!empty($product['brand'])) {
                $prompt .= "  Brand: {$product['brand']}\n";
            }
            if (!empty($product['price'])) {
                $prompt .= "  Price: {$product['price']}\n";
            }
            if (!empty($product['features'])) {
                $prompt .= "  Features: " . implode('; ', array_slice($product['features'], 0, 3)) . "\n";
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
        $prompt .= "- Start with an engaging introduction explaining why these are the best options\n";
        $prompt .= "- For each product, include:\n";
        $prompt .= "  - A brief description (2-3 sentences)\n";
        $prompt .= "  - Key highlights or best features\n";
        $prompt .= "  - Who it's best for\n";
        $prompt .= "  - A [BUY_BUTTON_X] placeholder (where X is the product index starting from 0)\n";

        if ($options['include_buying_guide'] ?? false) {
            $prompt .= "- Include a buying guide section at the end with tips for choosing\n";
        }

        if ($options['include_faq'] ?? true) {
            $prompt .= "- Include an FAQ section with 3-5 common questions about {$category}\n";
        }

        $prompt .= "- End with a brief conclusion summarizing the top picks\n";
        $prompt .= "\nWrite the roundup article now:";

        return $prompt;
    }

    /**
     * @inheritDoc
     */
    public function generateTitle(array $products, array $options): string {
        $count = count($products);
        $category = $products[0]['category'] ?? 'Products';
        $year = date('Y');

        $templates = [
            "Best {$count} {$category} in {$year}: Top Picks Reviewed",
            "{$count} Best {$category} - Our Top Recommendations",
            "Top {$count} {$category} Worth Buying in {$year}",
        ];

        return $templates[array_rand($templates)];
    }

    /**
     * Get length guidance
     *
     * @param string $length Length setting
     * @param int $productCount Number of products
     * @return string Guidance text
     */
    private function getLengthGuidance(string $length, int $productCount): string {
        $wordsPerProduct = match($length) {
            'short' => 100,
            'medium' => 200,
            'long' => 400,
            default => 200,
        };

        $totalWords = $wordsPerProduct * $productCount + 500; // Extra for intro/conclusion

        return "Write approximately {$totalWords} words total";
    }
}
