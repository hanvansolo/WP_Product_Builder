<?php
/**
 * Content Spinner
 *
 * Creates variations of content to avoid duplicate content issues
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Content;

use WPProductBuilder\API\ClaudeClient;

/**
 * Handles content spinning and variation
 */
class ContentSpinner {
    /**
     * Claude client for AI-powered spinning
     */
    private ClaudeClient $claude;

    /**
     * Synonym database
     */
    private array $synonyms = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->claude = new ClaudeClient();
        $this->loadSynonyms();
    }

    /**
     * Spin content using AI
     *
     * @param string $content Original content
     * @param array $options Spinning options
     * @return string Spun content
     */
    public function spinWithAI(string $content, array $options = []): string {
        $defaults = [
            'variation_level' => 'moderate', // light, moderate, heavy
            'preserve_facts' => true,
            'maintain_tone' => true,
        ];

        $options = array_merge($defaults, $options);

        $prompt = $this->buildSpinPrompt($content, $options);

        $result = $this->claude->generateContent($prompt, [
            'max_tokens' => strlen($content) * 2,
            'temperature' => $this->getTemperature($options['variation_level']),
        ]);

        if ($result['success']) {
            return $result['content'];
        }

        return $content; // Return original on failure
    }

    /**
     * Spin content using simple synonym replacement
     *
     * @param string $content Original content
     * @return string Spun content
     */
    public function spinWithSynonyms(string $content): string {
        foreach ($this->synonyms as $word => $alternatives) {
            // Case-insensitive replacement with random synonym
            $pattern = '/\b' . preg_quote($word, '/') . '\b/i';
            $content = preg_replace_callback($pattern, function($matches) use ($alternatives) {
                $replacement = $alternatives[array_rand($alternatives)];
                // Preserve original capitalization
                if (ctype_upper($matches[0][0])) {
                    $replacement = ucfirst($replacement);
                }
                return $replacement;
            }, $content);
        }

        return $content;
    }

    /**
     * Generate multiple variations
     *
     * @param string $content Original content
     * @param int $count Number of variations
     * @param string $method AI or synonyms
     * @return array Variations
     */
    public function generateVariations(string $content, int $count = 3, string $method = 'ai'): array {
        $variations = [$content]; // Include original

        for ($i = 1; $i < $count; $i++) {
            if ($method === 'ai') {
                $variation = $this->spinWithAI($content, [
                    'variation_level' => $this->getVariationLevel($i, $count),
                ]);
            } else {
                $variation = $this->spinWithSynonyms($content);
            }

            $variations[] = $variation;
        }

        return $variations;
    }

    /**
     * Spin specific sections of content
     *
     * @param string $content Full content
     * @param array $sections Sections to spin (intro, conclusion, etc.)
     * @return string Content with spun sections
     */
    public function spinSections(string $content, array $sections): string {
        foreach ($sections as $section) {
            // Find and spin specific sections
            $pattern = $this->getSectionPattern($section);

            $content = preg_replace_callback($pattern, function($matches) {
                return $this->spinWithAI($matches[0], ['variation_level' => 'light']);
            }, $content);
        }

        return $content;
    }

    /**
     * Rewrite introductions to be unique
     *
     * @param string $content Content with introduction
     * @return string Content with rewritten intro
     */
    public function rewriteIntroduction(string $content): string {
        // Extract first paragraph
        $parts = preg_split('/(<\/p>|<br\s*\/?>|\n\n)/', $content, 2);

        if (count($parts) < 2) {
            return $content;
        }

        $intro = $parts[0];
        $rest = $parts[1];

        // Rewrite intro
        $prompt = "Rewrite this introduction paragraph to be unique while maintaining the same meaning and information:\n\n" . strip_tags($intro);

        $result = $this->claude->generateContent($prompt, [
            'max_tokens' => 500,
            'temperature' => 0.8,
        ]);

        if ($result['success']) {
            return '<p>' . $result['content'] . '</p>' . $rest;
        }

        return $content;
    }

    /**
     * Create unique product descriptions
     *
     * @param array $product Product data
     * @param string $style Description style
     * @return string Unique description
     */
    public function createUniqueDescription(array $product, string $style = 'informative'): string {
        $prompt = "Write a unique, {$style} product description for:\n\n";
        $prompt .= "Product: {$product['title']}\n";

        if (!empty($product['features'])) {
            $prompt .= "Features: " . implode(', ', array_slice($product['features'], 0, 5)) . "\n";
        }

        if (!empty($product['brand'])) {
            $prompt .= "Brand: {$product['brand']}\n";
        }

        $prompt .= "\nWrite 2-3 sentences that highlight the key benefits. Be original and avoid generic phrases.";

        $result = $this->claude->generateContent($prompt, [
            'max_tokens' => 300,
            'temperature' => 0.9,
        ]);

        return $result['success'] ? $result['content'] : '';
    }

    /**
     * Build AI spin prompt
     */
    private function buildSpinPrompt(string $content, array $options): string {
        $instruction = match($options['variation_level']) {
            'light' => 'Slightly reword and rephrase',
            'moderate' => 'Rewrite to be unique while keeping the same meaning',
            'heavy' => 'Completely rewrite with different structure and wording',
            default => 'Rewrite to be unique',
        };

        $prompt = "{$instruction} the following content:\n\n";
        $prompt .= "---\n{$content}\n---\n\n";

        if ($options['preserve_facts']) {
            $prompt .= "Important: Preserve all factual information, numbers, and specific details.\n";
        }

        if ($options['maintain_tone']) {
            $prompt .= "Maintain the same tone and style.\n";
        }

        $prompt .= "\nProvide only the rewritten content without any explanations or notes.";

        return $prompt;
    }

    /**
     * Get temperature based on variation level
     */
    private function getTemperature(string $level): float {
        return match($level) {
            'light' => 0.5,
            'moderate' => 0.7,
            'heavy' => 0.9,
            default => 0.7,
        };
    }

    /**
     * Get variation level based on index
     */
    private function getVariationLevel(int $index, int $total): string {
        $ratio = $index / $total;

        if ($ratio < 0.33) {
            return 'light';
        } elseif ($ratio < 0.66) {
            return 'moderate';
        }

        return 'heavy';
    }

    /**
     * Get regex pattern for section
     */
    private function getSectionPattern(string $section): string {
        return match($section) {
            'intro' => '/<p[^>]*>.*?<\/p>/is',
            'conclusion' => '/<h[23][^>]*>.*?(conclusion|verdict|summary).*?<\/h[23]>.*?(<h[23]|$)/is',
            'faq' => '/<h[23][^>]*>.*?faq.*?<\/h[23]>.*?(<h[23]|$)/is',
            default => '/./s',
        };
    }

    /**
     * Load synonym database
     */
    private function loadSynonyms(): void {
        $this->synonyms = [
            // Common adjectives
            'good' => ['excellent', 'great', 'fantastic', 'superb', 'outstanding'],
            'bad' => ['poor', 'subpar', 'disappointing', 'inadequate'],
            'best' => ['top', 'finest', 'premier', 'leading', 'superior'],
            'great' => ['excellent', 'wonderful', 'fantastic', 'remarkable', 'impressive'],
            'important' => ['essential', 'crucial', 'vital', 'significant', 'key'],
            'easy' => ['simple', 'straightforward', 'effortless', 'uncomplicated'],
            'fast' => ['quick', 'rapid', 'speedy', 'swift'],
            'new' => ['latest', 'modern', 'recent', 'fresh', 'current'],

            // Common verbs
            'buy' => ['purchase', 'get', 'acquire', 'invest in'],
            'use' => ['utilize', 'employ', 'apply', 'make use of'],
            'help' => ['assist', 'aid', 'support', 'benefit'],
            'make' => ['create', 'produce', 'build', 'construct'],
            'get' => ['obtain', 'acquire', 'receive', 'gain'],
            'find' => ['discover', 'locate', 'identify', 'uncover'],
            'see' => ['notice', 'observe', 'view', 'spot'],
            'look' => ['appear', 'seem', 'examine', 'check'],

            // Product-related
            'feature' => ['capability', 'function', 'attribute', 'aspect'],
            'quality' => ['caliber', 'standard', 'grade', 'excellence'],
            'price' => ['cost', 'value', 'rate', 'amount'],
            'product' => ['item', 'merchandise', 'offering', 'solution'],
            'review' => ['assessment', 'evaluation', 'analysis', 'appraisal'],
            'recommend' => ['suggest', 'advise', 'endorse', 'advocate'],

            // Transition words
            'however' => ['nevertheless', 'nonetheless', 'yet', 'still'],
            'also' => ['additionally', 'furthermore', 'moreover', 'besides'],
            'because' => ['since', 'as', 'due to', 'owing to'],
            'but' => ['however', 'yet', 'although', 'though'],
        ];

        // Allow custom synonyms via filter
        $this->synonyms = apply_filters('wpb_content_spinner_synonyms', $this->synonyms);
    }

    /**
     * Add custom synonyms
     */
    public function addSynonyms(array $synonyms): void {
        $this->synonyms = array_merge($this->synonyms, $synonyms);
    }

    /**
     * Check content uniqueness score
     *
     * @param string $content Content to check
     * @param array $compareTo Array of content to compare against
     * @return float Uniqueness score (0-100)
     */
    public function getUniquenessScore(string $content, array $compareTo): float {
        if (empty($compareTo)) {
            return 100.0;
        }

        $contentWords = $this->getWords($content);
        $scores = [];

        foreach ($compareTo as $compare) {
            $compareWords = $this->getWords($compare);
            $similarity = $this->calculateSimilarity($contentWords, $compareWords);
            $scores[] = 100 - ($similarity * 100);
        }

        return round(min($scores), 2);
    }

    /**
     * Get words from text
     */
    private function getWords(string $text): array {
        $text = strip_tags($text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        return array_filter(explode(' ', $text));
    }

    /**
     * Calculate similarity between word arrays
     */
    private function calculateSimilarity(array $words1, array $words2): float {
        $intersection = count(array_intersect($words1, $words2));
        $union = count(array_unique(array_merge($words1, $words2)));

        if ($union === 0) {
            return 0;
        }

        return $intersection / $union;
    }
}
