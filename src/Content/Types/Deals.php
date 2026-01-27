<?php
/**
 * Deals Content Type
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Content\Types;

use WPProductBuilder\Content\ContentTypeInterface;

/**
 * Deals/promotional content generator
 */
class Deals implements ContentTypeInterface {
    /**
     * @inheritDoc
     */
    public function getType(): string {
        return 'deals';
    }

    /**
     * @inheritDoc
     */
    public function getName(): string {
        return __('Deals', 'wp-product-builder');
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string {
        return __('Promotional content highlighting deals and discounts.', 'wp-product-builder');
    }

    /**
     * @inheritDoc
     */
    public function buildPrompt(array $products, array $options): string {
        $count = count($products);
        $tone = $options['tone'] ?? 'enthusiastic';

        $category = $products[0]['category'] ?? 'products';

        $prompt = "Write an exciting deals article highlighting these limited-time offers:\n\n";
        $prompt .= "**Products on Sale:**\n\n";

        foreach ($products as $index => $product) {
            $prompt .= "**Deal " . ($index + 1) . ":** {$product['title']}\n";
            if (!empty($product['price'])) {
                $prompt .= "  Current Price: {$product['price']}\n";
            }
            if (!empty($product['brand'])) {
                $prompt .= "  Brand: {$product['brand']}\n";
            }
            if (!empty($product['features'])) {
                $prompt .= "  Key Feature: {$product['features'][0]}\n";
            }
            $prompt .= "\n";
        }

        if (!empty($options['focus_keywords'])) {
            $keywords = implode(', ', $options['focus_keywords']);
            $prompt .= "**Focus Keywords:** {$keywords}\n\n";
        }

        $prompt .= "**Writing Instructions:**\n";
        $prompt .= "- Write in an {$tone}, urgent tone - create excitement about these deals\n";
        $prompt .= "- Format output in HTML with proper heading tags\n";
        $prompt .= "- Start with an attention-grabbing introduction about saving money\n";
        $prompt .= "- Create a sense of urgency (limited time, while supplies last, etc.)\n";
        $prompt .= "- For each deal, include:\n";
        $prompt .= "  - Eye-catching heading\n";
        $prompt .= "  - Why this is a great deal\n";
        $prompt .= "  - Key benefits of the product\n";
        $prompt .= "  - A prominent [BUY_BUTTON_X] placeholder (X = index from 0)\n";
        $prompt .= "- Use phrases like \"Don't miss out\", \"Limited time\", \"Best price\"\n";
        $prompt .= "- Keep descriptions brief and action-oriented\n";
        $prompt .= "- End with a call to action to shop now\n";
        $prompt .= "\nWrite the deals article now:";

        return $prompt;
    }

    /**
     * @inheritDoc
     */
    public function generateTitle(array $products, array $options): string {
        $count = count($products);
        $category = $products[0]['category'] ?? 'Products';

        $templates = [
            "Best {$category} Deals: Save Big on Top Picks!",
            "{$count} Amazing {$category} Deals You Can't Miss",
            "Hot {$category} Deals - Limited Time Offers!",
            "Don't Miss These {$count} {$category} Deals",
        ];

        return $templates[array_rand($templates)];
    }
}
