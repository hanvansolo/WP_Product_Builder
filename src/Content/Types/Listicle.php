<?php
/**
 * Listicle Content Type
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Content\Types;

use WPProductBuilder\Content\ContentTypeInterface;

/**
 * Listicle content generator
 */
class Listicle implements ContentTypeInterface {
    /**
     * @inheritDoc
     */
    public function getType(): string {
        return 'listicle';
    }

    /**
     * @inheritDoc
     */
    public function getName(): string {
        return __('Listicle', 'wp-product-builder');
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string {
        return __('Numbered list content with products or tips.', 'wp-product-builder');
    }

    /**
     * @inheritDoc
     */
    public function buildPrompt(array $products, array $options): string {
        $count = count($products);
        $tone = $options['tone'] ?? 'enthusiastic';
        $length = $this->getLengthGuidance($options['length'] ?? 'medium', $count);

        $category = $products[0]['category'] ?? 'products';

        $prompt = "Write an engaging listicle article: **{$count} {$category} You Need to Know About**\n\n";
        $prompt .= "**Products to Feature:**\n\n";

        foreach ($products as $index => $product) {
            $prompt .= ($index + 1) . ". **{$product['title']}**\n";
            if (!empty($product['price'])) {
                $prompt .= "   Price: {$product['price']}\n";
            }
            if (!empty($product['features'])) {
                $prompt .= "   Notable: " . ($product['features'][0] ?? '') . "\n";
            }
            $prompt .= "\n";
        }

        if (!empty($options['focus_keywords'])) {
            $keywords = implode(', ', $options['focus_keywords']);
            $prompt .= "**Focus Keywords:** {$keywords}\n\n";
        }

        $prompt .= "**Writing Instructions:**\n";
        $prompt .= "- Write in an {$tone} tone - listicles should be engaging and scannable\n";
        $prompt .= "- {$length}\n";
        $prompt .= "- Format output in HTML with proper heading tags\n";
        $prompt .= "- Use numbered headings (1. Product Name, 2. Product Name, etc.)\n";
        $prompt .= "- Start with a catchy introduction that hooks the reader\n";
        $prompt .= "- For each item, include:\n";
        $prompt .= "  - A brief, punchy description (2-3 sentences)\n";
        $prompt .= "  - What makes it special or unique\n";
        $prompt .= "  - A [BUY_BUTTON_X] placeholder (X = index from 0)\n";
        $prompt .= "- Use engaging subheadings and bullet points for scannability\n";
        $prompt .= "- End with a brief conclusion\n";

        if ($options['include_faq'] ?? false) {
            $prompt .= "- Include a short FAQ section at the end\n";
        }

        $prompt .= "\nWrite the listicle now:";

        return $prompt;
    }

    /**
     * @inheritDoc
     */
    public function generateTitle(array $products, array $options): string {
        $count = count($products);
        $category = $products[0]['category'] ?? 'Things';
        $year = date('Y');

        $templates = [
            "{$count} {$category} You'll Wish You Knew About Sooner",
            "{$count} Best {$category} That Are Actually Worth It",
            "{$count} Amazing {$category} You Need in {$year}",
            "Top {$count} {$category} That Will Change Your Life",
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
        $wordsPerItem = match($length) {
            'short' => 75,
            'medium' => 150,
            'long' => 250,
            default => 150,
        };

        $totalWords = $wordsPerItem * $productCount + 300;

        return "Write approximately {$totalWords} words total";
    }
}
