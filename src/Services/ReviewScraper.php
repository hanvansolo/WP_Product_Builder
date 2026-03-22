<?php
/**
 * Review Scraper Service
 *
 * Scrapes real product reviews from category-relevant sites
 * and provides them to the content generator for citation.
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Services;

/**
 * Scrapes product reviews from trusted review sites based on product category
 */
class ReviewScraper {
    /**
     * Review site configurations by focus category
     */
    private const CATEGORY_SITES = [
        'electronics' => [
            ['name' => 'TechRadar', 'search_url' => 'https://www.techradar.com/search?searchTerm=%s', 'selector' => '.search-result__title, .article-link'],
            ['name' => 'CNET', 'search_url' => 'https://www.cnet.com/search/?q=%s', 'selector' => '.searchResult a'],
            ['name' => 'PCMag', 'search_url' => 'https://www.pcmag.com/search?q=%s', 'selector' => '.search-result a'],
            ['name' => "Tom's Guide", 'search_url' => 'https://www.tomsguide.com/search?searchTerm=%s', 'selector' => '.search-result__title'],
            ['name' => 'Wirecutter', 'search_url' => 'https://www.nytimes.com/wirecutter/search/?s=%s', 'selector' => '.search-results a'],
        ],
        'camping_outdoor' => [
            ['name' => 'OutdoorGearLab', 'search_url' => 'https://www.outdoorgearlab.com/search?q=%s', 'selector' => '.search-result a'],
            ['name' => 'Switchback Travel', 'search_url' => 'https://www.switchbacktravel.com/?s=%s', 'selector' => '.entry-title a'],
            ['name' => 'GearJunkie', 'search_url' => 'https://gearjunkie.com/?s=%s', 'selector' => '.entry-title a'],
            ['name' => 'REI Expert Advice', 'search_url' => 'https://www.rei.com/search?q=%s+review', 'selector' => '.search-results a'],
            ['name' => 'Clever Hiker', 'search_url' => 'https://www.cleverhiker.com/?s=%s', 'selector' => '.entry-title a'],
        ],
        'home_kitchen' => [
            ['name' => 'Good Housekeeping', 'search_url' => 'https://www.goodhousekeeping.com/search/?q=%s', 'selector' => '.result-title a'],
            ['name' => 'Wirecutter', 'search_url' => 'https://www.nytimes.com/wirecutter/search/?s=%s', 'selector' => '.search-results a'],
            ['name' => 'Reviewed', 'search_url' => 'https://reviewed.usatoday.com/search?query=%s', 'selector' => '.search-result a'],
            ['name' => 'The Spruce', 'search_url' => 'https://www.thespruce.com/search?q=%s', 'selector' => '.search-result a'],
            ['name' => 'Consumer Reports', 'search_url' => 'https://www.consumerreports.org/search/?query=%s', 'selector' => '.search-result a'],
        ],
        'beauty_health' => [
            ['name' => 'Allure', 'search_url' => 'https://www.allure.com/search?q=%s', 'selector' => '.search-result a'],
            ['name' => 'Byrdie', 'search_url' => 'https://www.byrdie.com/search?q=%s', 'selector' => '.search-result a'],
            ['name' => 'Healthline', 'search_url' => 'https://www.healthline.com/search?q1=%s', 'selector' => '.search-result a'],
            ['name' => 'Cosmopolitan', 'search_url' => 'https://www.cosmopolitan.com/search/?q=%s', 'selector' => '.result-title a'],
            ['name' => 'Wirecutter', 'search_url' => 'https://www.nytimes.com/wirecutter/search/?s=%s', 'selector' => '.search-results a'],
        ],
        'fitness_sports' => [
            ['name' => 'GarageGymReviews', 'search_url' => 'https://www.garagegymreviews.com/?s=%s', 'selector' => '.entry-title a'],
            ['name' => "Men's Health", 'search_url' => 'https://www.menshealth.com/search/?q=%s', 'selector' => '.result-title a'],
            ['name' => "Runner's World", 'search_url' => 'https://www.runnersworld.com/search/?q=%s', 'selector' => '.result-title a'],
            ['name' => 'Wirecutter', 'search_url' => 'https://www.nytimes.com/wirecutter/search/?s=%s', 'selector' => '.search-results a'],
            ['name' => "Tom's Guide", 'search_url' => 'https://www.tomsguide.com/search?searchTerm=%s', 'selector' => '.search-result__title'],
        ],
        'gaming' => [
            ['name' => 'IGN', 'search_url' => 'https://www.ign.com/search?q=%s', 'selector' => '.search-result a'],
            ['name' => 'PC Gamer', 'search_url' => 'https://www.pcgamer.com/search/?searchTerm=%s', 'selector' => '.search-result__title'],
            ['name' => 'GameSpot', 'search_url' => 'https://www.gamespot.com/search/?q=%s', 'selector' => '.search-result a'],
            ['name' => 'TechRadar', 'search_url' => 'https://www.techradar.com/search?searchTerm=%s', 'selector' => '.search-result__title'],
            ['name' => "Tom's Hardware", 'search_url' => 'https://www.tomshardware.com/search?searchTerm=%s', 'selector' => '.search-result__title'],
        ],
        'automotive' => [
            ['name' => 'Car and Driver', 'search_url' => 'https://www.caranddriver.com/search/?q=%s', 'selector' => '.result-title a'],
            ['name' => 'MotorTrend', 'search_url' => 'https://www.motortrend.com/search/?q=%s', 'selector' => '.search-result a'],
            ['name' => 'Edmunds', 'search_url' => 'https://www.edmunds.com/search/?keyword=%s', 'selector' => '.search-result a'],
            ['name' => 'AutoBlog', 'search_url' => 'https://www.autoblog.com/search/?q=%s', 'selector' => '.search-result a'],
            ['name' => 'The Drive', 'search_url' => 'https://www.thedrive.com/?s=%s', 'selector' => '.entry-title a'],
        ],
        'fashion' => [
            ['name' => 'GQ', 'search_url' => 'https://www.gq.com/search?q=%s', 'selector' => '.search-result a'],
            ['name' => 'Vogue', 'search_url' => 'https://www.vogue.com/search?q=%s', 'selector' => '.search-result a'],
            ['name' => 'Who What Wear', 'search_url' => 'https://www.whowhatwear.com/search?q=%s', 'selector' => '.search-result a'],
            ['name' => 'Wirecutter', 'search_url' => 'https://www.nytimes.com/wirecutter/search/?s=%s', 'selector' => '.search-results a'],
            ['name' => 'Reviewed', 'search_url' => 'https://reviewed.usatoday.com/search?query=%s', 'selector' => '.search-result a'],
        ],
        'baby_kids' => [
            ['name' => 'BabyGearLab', 'search_url' => 'https://www.babygearlab.com/search?q=%s', 'selector' => '.search-result a'],
            ['name' => 'What to Expect', 'search_url' => 'https://www.whattoexpect.com/search?q=%s', 'selector' => '.search-result a'],
            ['name' => 'Wirecutter', 'search_url' => 'https://www.nytimes.com/wirecutter/search/?s=%s', 'selector' => '.search-results a'],
            ['name' => 'Good Housekeeping', 'search_url' => 'https://www.goodhousekeeping.com/search/?q=%s', 'selector' => '.result-title a'],
            ['name' => 'The Bump', 'search_url' => 'https://www.thebump.com/search?q=%s', 'selector' => '.search-result a'],
        ],
        'pets' => [
            ['name' => 'The Spruce Pets', 'search_url' => 'https://www.thesprucepets.com/search?q=%s', 'selector' => '.search-result a'],
            ['name' => 'PetMD', 'search_url' => 'https://www.petmd.com/search?keys=%s', 'selector' => '.search-result a'],
            ['name' => 'Rover', 'search_url' => 'https://www.rover.com/blog/?s=%s', 'selector' => '.entry-title a'],
            ['name' => 'Wirecutter', 'search_url' => 'https://www.nytimes.com/wirecutter/search/?s=%s', 'selector' => '.search-results a'],
            ['name' => 'Chewy', 'search_url' => 'https://www.chewy.com/s?query=%s+review', 'selector' => '.search-result a'],
        ],
        'general' => [
            ['name' => 'Wirecutter', 'search_url' => 'https://www.nytimes.com/wirecutter/search/?s=%s', 'selector' => '.search-results a'],
            ['name' => 'CNET', 'search_url' => 'https://www.cnet.com/search/?q=%s', 'selector' => '.searchResult a'],
            ['name' => 'Reviewed', 'search_url' => 'https://reviewed.usatoday.com/search?query=%s', 'selector' => '.search-result a'],
            ['name' => 'Consumer Reports', 'search_url' => 'https://www.consumerreports.org/search/?query=%s', 'selector' => '.search-result a'],
            ['name' => 'Good Housekeeping', 'search_url' => 'https://www.goodhousekeeping.com/search/?q=%s', 'selector' => '.result-title a'],
        ],
    ];

    /**
     * Available focus categories for settings
     */
    public const FOCUS_CATEGORIES = [
        'general' => 'General Products',
        'electronics' => 'Electronics & Tech',
        'camping_outdoor' => 'Camping & Outdoor Gear',
        'home_kitchen' => 'Home & Kitchen',
        'beauty_health' => 'Beauty & Health',
        'fitness_sports' => 'Fitness & Sports',
        'gaming' => 'Gaming',
        'automotive' => 'Automotive',
        'fashion' => 'Fashion & Apparel',
        'baby_kids' => 'Baby & Kids',
        'pets' => 'Pets',
    ];

    /**
     * Scrape reviews for a product from category-relevant sites
     *
     * @param string $productName Product name/title
     * @param string $focusCategory Category key from settings
     * @param int $maxSources Maximum number of sources to scrape
     * @return array Array of review snippets
     */
    public function scrapeReviews(string $productName, string $focusCategory = 'general', int $maxSources = 4): array {
        $sites = self::CATEGORY_SITES[$focusCategory] ?? self::CATEGORY_SITES['general'];
        $reviews = [];

        // Simplify product name for search (remove brand noise)
        $searchTerm = $this->simplifyProductName($productName);

        foreach ($sites as $site) {
            if (count($reviews) >= $maxSources) {
                break;
            }

            $review = $this->scrapeFromSite($site, $searchTerm);
            if ($review) {
                $reviews[] = $review;
            }
        }

        return $reviews;
    }

    /**
     * Scrape review content from a specific site
     *
     * @param array $site Site configuration
     * @param string $searchTerm Search term
     * @return array|null Review data or null
     */
    private function scrapeFromSite(array $site, string $searchTerm): ?array {
        $url = sprintf($site['search_url'], rawurlencode($searchTerm));

        $response = wp_remote_get($url, [
            'timeout' => 8,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html) || strlen($html) < 500) {
            return null;
        }

        // Extract review snippets from the page
        $snippets = $this->extractSnippets($html, $searchTerm);

        if (empty($snippets)) {
            return null;
        }

        return [
            'source' => $site['name'],
            'url' => $url,
            'snippets' => $snippets,
        ];
    }

    /**
     * Extract relevant text snippets from HTML
     *
     * @param string $html Page HTML
     * @param string $searchTerm What we're looking for
     * @return array Text snippets
     */
    private function extractSnippets(string $html, string $searchTerm): array {
        $snippets = [];

        // Strip scripts and styles
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $html);

        // Get text content
        $text = wp_strip_all_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);

        // Split into sentences
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);

        // Search terms for matching
        $searchWords = array_filter(explode(' ', strtolower($searchTerm)));
        $reviewWords = ['review', 'tested', 'recommend', 'best', 'rated', 'verdict', 'performance', 'quality', 'worth', 'excellent', 'impressive', 'disappointing', 'solid', 'reliable', 'durable', 'comfortable', 'value'];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) < 40 || strlen($sentence) > 300) {
                continue;
            }

            $lower = strtolower($sentence);

            // Must contain at least one search word
            $hasSearchWord = false;
            foreach ($searchWords as $word) {
                if (strlen($word) > 3 && str_contains($lower, $word)) {
                    $hasSearchWord = true;
                    break;
                }
            }

            if (!$hasSearchWord) {
                continue;
            }

            // Must contain at least one review-related word
            $hasReviewWord = false;
            foreach ($reviewWords as $word) {
                if (str_contains($lower, $word)) {
                    $hasReviewWord = true;
                    break;
                }
            }

            if (!$hasReviewWord) {
                continue;
            }

            // Skip navigation/menu text
            if (preg_match('/^(menu|nav|skip|cookie|subscribe|sign up|log in)/i', $sentence)) {
                continue;
            }

            $snippets[] = $sentence;

            if (count($snippets) >= 3) {
                break;
            }
        }

        return $snippets;
    }

    /**
     * Simplify product name for better search results
     *
     * @param string $name Full product name
     * @return string Simplified search term
     */
    private function simplifyProductName(string $name): string {
        // Remove common noise words and model numbers at the end
        $name = preg_replace('/\s*\(.*?\)\s*/', ' ', $name);
        $name = preg_replace('/,\s*\d+.*$/', '', $name);

        // Keep first 6 meaningful words
        $words = array_filter(explode(' ', $name), fn($w) => strlen($w) > 1);
        $words = array_slice($words, 0, 6);

        return implode(' ', $words) . ' review';
    }

    /**
     * Format scraped reviews into a prompt section
     *
     * @param array $reviews Scraped review data
     * @return string Formatted text for prompt
     */
    public function formatForPrompt(array $reviews): string {
        if (empty($reviews)) {
            return '';
        }

        $prompt = "\n**Real Review Sources Found:**\n";
        $prompt .= "Use the following real review snippets from trusted publications. Quote or paraphrase them naturally in your article, crediting each source:\n\n";

        foreach ($reviews as $review) {
            $prompt .= "Source: {$review['source']}\n";
            foreach ($review['snippets'] as $snippet) {
                $prompt .= "- \"{$snippet}\"\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "Integrate these quotes naturally throughout the review. Use phrases like 'According to {source}...' or '{source} noted that...'\n";

        return $prompt;
    }

    /**
     * Get available focus categories
     */
    public static function getFocusCategories(): array {
        return self::FOCUS_CATEGORIES;
    }
}
