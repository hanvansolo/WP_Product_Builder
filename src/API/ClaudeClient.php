<?php
/**
 * Claude API Client
 *
 * Wrapper for Anthropic's Claude API
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\API;

use WPProductBuilder\Encryption\EncryptionService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Claude API client for content generation
 */
class ClaudeClient {
    /**
     * API base URL
     */
    private const API_BASE_URL = 'https://api.anthropic.com/v1';

    /**
     * API version
     */
    private const API_VERSION = '2023-06-01';

    /**
     * HTTP client
     */
    private Client $client;

    /**
     * API key
     */
    private string $apiKey;

    /**
     * Model to use
     */
    private string $model;

    /**
     * Constructor
     */
    public function __construct() {
        $encryption = new EncryptionService();
        $credentials = get_option('wpb_credentials_encrypted', []);
        $settings = get_option('wpb_settings', []);

        $this->apiKey = !empty($credentials['claude_api_key'])
            ? $encryption->decrypt($credentials['claude_api_key'])
            : '';

        $this->model = $settings['claude_model'] ?? 'claude-sonnet-4-20250514';

        $this->client = new Client([
            'base_uri' => self::API_BASE_URL,
            'timeout' => 120,
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
            ],
        ]);
    }

    /**
     * Generate content using Claude
     *
     * @param string $prompt The user prompt
     * @param array $options Generation options
     * @return array Response with success status, content, and usage
     */
    public function generateContent(string $prompt, array $options = []): array {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error' => __('Claude API key is not configured.', 'wp-product-builder'),
            ];
        }

        $defaults = [
            'max_tokens' => 4096,
            'temperature' => 0.7,
            'system' => $this->getDefaultSystemPrompt(),
        ];

        $params = array_merge($defaults, $options);

        try {
            $response = $this->client->post('/messages', [
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => $params['max_tokens'],
                    'temperature' => $params['temperature'],
                    'system' => $params['system'],
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            // Extract content from response
            $content = '';
            if (!empty($body['content'])) {
                foreach ($body['content'] as $block) {
                    if ($block['type'] === 'text') {
                        $content .= $block['text'];
                    }
                }
            }

            // Track API usage
            $this->trackUsage($body['usage'] ?? []);

            return [
                'success' => true,
                'content' => $content,
                'usage' => [
                    'input_tokens' => $body['usage']['input_tokens'] ?? 0,
                    'output_tokens' => $body['usage']['output_tokens'] ?? 0,
                ],
                'model' => $this->model,
                'stop_reason' => $body['stop_reason'] ?? null,
            ];

        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'error' => $this->parseError($e),
            ];
        }
    }

    /**
     * Test API connection
     *
     * @return array Result with success status and message
     */
    public function testConnection(): array {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => __('API key is not configured.', 'wp-product-builder'),
            ];
        }

        try {
            $response = $this->client->post('/messages', [
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => 10,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Say "connected" in one word.'],
                    ],
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!empty($body['content'])) {
                return [
                    'success' => true,
                    'message' => __('Connection successful!', 'wp-product-builder'),
                    'model' => $this->model,
                ];
            }

            return [
                'success' => false,
                'message' => __('Unexpected response from API.', 'wp-product-builder'),
            ];

        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'message' => $this->parseError($e),
            ];
        }
    }

    /**
     * Get default system prompt
     *
     * @return string
     */
    private function getDefaultSystemPrompt(): string {
        return <<<PROMPT
You are an expert affiliate content writer specializing in product reviews and comparisons.

Your writing style should be:
- Engaging and helpful to readers making purchase decisions
- Honest about both pros and cons of products
- SEO-optimized with natural keyword usage
- Well-structured with clear headings and sections
- Professional yet conversational in tone

Guidelines:
- Always provide balanced, objective assessments
- Include specific product features and specifications when available
- Use bullet points for easy scanning
- Create compelling introductions and conclusions
- Include relevant FAQs when requested
- Format output in clean HTML with appropriate heading tags (h2, h3)

Never fabricate specifications or features. If information is not provided, acknowledge the limitation and focus on what is known.
PROMPT;
    }

    /**
     * Track API usage
     *
     * @param array $usage Usage data from response
     */
    private function trackUsage(array $usage): void {
        global $wpdb;

        $table = $wpdb->prefix . 'wpb_api_usage';
        $user_id = get_current_user_id();
        $today = current_time('Y-m-d');

        $input_tokens = $usage['input_tokens'] ?? 0;
        $output_tokens = $usage['output_tokens'] ?? 0;

        // Estimate cost (Claude Sonnet pricing as of 2024)
        $cost = ($input_tokens * 0.003 / 1000) + ($output_tokens * 0.015 / 1000);

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (user_id, api_type, endpoint, request_count, tokens_input, tokens_output, cost_estimate, date_recorded)
             VALUES (%d, 'claude', 'messages', 1, %d, %d, %f, %s)
             ON DUPLICATE KEY UPDATE
             request_count = request_count + 1,
             tokens_input = tokens_input + VALUES(tokens_input),
             tokens_output = tokens_output + VALUES(tokens_output),
             cost_estimate = cost_estimate + VALUES(cost_estimate)",
            $user_id,
            $input_tokens,
            $output_tokens,
            $cost,
            $today
        ));
    }

    /**
     * Parse error message from exception
     *
     * @param GuzzleException $e The exception
     * @return string Error message
     */
    private function parseError(GuzzleException $e): string {
        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            $body = json_decode($e->getResponse()->getBody()->getContents(), true);

            if (!empty($body['error']['message'])) {
                return $body['error']['message'];
            }
        }

        $message = $e->getMessage();

        // Clean up common error messages
        if (str_contains($message, 'authentication_error')) {
            return __('Invalid API key. Please check your Claude API key.', 'wp-product-builder');
        }

        if (str_contains($message, 'rate_limit')) {
            return __('Rate limit exceeded. Please try again later.', 'wp-product-builder');
        }

        if (str_contains($message, 'overloaded')) {
            return __('API is currently overloaded. Please try again later.', 'wp-product-builder');
        }

        return $message;
    }

    /**
     * Get available models
     *
     * @return array Model options
     */
    public static function getAvailableModels(): array {
        return [
            'claude-sonnet-4-20250514' => 'Claude Sonnet 4 (Recommended)',
            'claude-opus-4-20250514' => 'Claude Opus 4 (Most Capable)',
        ];
    }

    /**
     * Check if API is configured
     *
     * @return bool
     */
    public function isConfigured(): bool {
        return !empty($this->apiKey);
    }

    /**
     * Get current model
     *
     * @return string
     */
    public function getModel(): string {
        return $this->model;
    }
}
