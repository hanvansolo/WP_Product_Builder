<?php
/**
 * Settings REST API Endpoint
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\API\Endpoints;

use WPProductBuilder\Encryption\EncryptionService;
use WPProductBuilder\API\ClaudeClient;
use WPProductBuilder\API\AmazonClient;
use WPProductBuilder\API\CJClient;
use WPProductBuilder\API\AwinClient;
use WPProductBuilder\Database\Repositories\ProductRepository;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API controller for settings
 */
class SettingsEndpoint extends WP_REST_Controller {
    /**
     * Namespace
     */
    protected $namespace = 'wp-product-builder/v1';

    /**
     * Register routes
     */
    public function register_routes(): void {
        // Get/update settings
        register_rest_route($this->namespace, '/settings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_admin_permissions'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_admin_permissions'],
            ],
        ]);

        // Test API connection
        register_rest_route($this->namespace, '/test-connection', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'test_connection'],
                'permission_callback' => [$this, 'check_admin_permissions'],
                'args' => [
                    'api' => [
                        'required' => true,
                        'type' => 'string',
                        'enum' => ['claude', 'amazon', 'cj', 'awin'],
                    ],
                ],
            ],
        ]);

        // Get dashboard stats
        register_rest_route($this->namespace, '/stats', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_stats'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
        ]);

        // Clear cache
        register_rest_route($this->namespace, '/cache/clear', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'clear_cache'],
                'permission_callback' => [$this, 'check_admin_permissions'],
            ],
        ]);
    }

    /**
     * Check admin permissions
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_admin_permissions(WP_REST_Request $request) {
        if (!current_user_can('manage_wpb_settings')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to manage settings.', 'wp-product-builder'),
                ['status' => 403]
            );
        }
        return true;
    }

    /**
     * Check basic permissions
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_permissions(WP_REST_Request $request) {
        if (!current_user_can('wpb_generate_content')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access this resource.', 'wp-product-builder'),
                ['status' => 403]
            );
        }
        return true;
    }

    /**
     * Get settings
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_settings(WP_REST_Request $request): WP_REST_Response {
        $settings = get_option('wpb_settings', []);
        $credentials = get_option('wpb_credentials_encrypted', []);

        // Mask sensitive data
        $response = [
            'settings' => $settings,
            'credentials' => [
                'claude_api_key_set' => !empty($credentials['claude_api_key']),
                'amazon_access_key_set' => !empty($credentials['amazon_access_key']),
                'amazon_secret_key_set' => !empty($credentials['amazon_secret_key']),
                'amazon_partner_tag' => $credentials['amazon_partner_tag'] ?? '',
                'cj_api_key_set' => !empty($credentials['cj_api_key']),
                'cj_website_id' => $credentials['cj_website_id'] ?? '',
                'awin_api_key_set' => !empty($credentials['awin_api_key']),
                'awin_publisher_id' => $credentials['awin_publisher_id'] ?? '',
            ],
        ];

        return new WP_REST_Response($response, 200);
    }

    /**
     * Update settings
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_settings(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $params = $request->get_json_params();

        $encryption = new EncryptionService();
        $current_credentials = get_option('wpb_credentials_encrypted', []);
        $current_settings = get_option('wpb_settings', []);

        // Handle credentials
        $credentials = $current_credentials;

        if (!empty($params['claude_api_key'])) {
            $credentials['claude_api_key'] = $encryption->encrypt($params['claude_api_key']);
        }

        if (!empty($params['amazon_access_key'])) {
            $credentials['amazon_access_key'] = $encryption->encrypt($params['amazon_access_key']);
        }

        if (!empty($params['amazon_secret_key'])) {
            $credentials['amazon_secret_key'] = $encryption->encrypt($params['amazon_secret_key']);
        }

        if (isset($params['amazon_partner_tag'])) {
            $credentials['amazon_partner_tag'] = sanitize_text_field($params['amazon_partner_tag']);
        }

        // CJ Affiliate credentials
        if (!empty($params['cj_api_key'])) {
            $credentials['cj_api_key'] = $encryption->encrypt($params['cj_api_key']);
        }

        if (isset($params['cj_website_id'])) {
            $credentials['cj_website_id'] = sanitize_text_field($params['cj_website_id']);
        }

        // Awin credentials
        if (!empty($params['awin_api_key'])) {
            $credentials['awin_api_key'] = $encryption->encrypt($params['awin_api_key']);
        }

        if (isset($params['awin_publisher_id'])) {
            $credentials['awin_publisher_id'] = sanitize_text_field($params['awin_publisher_id']);
        }

        // Handle settings
        $settings = $current_settings;

        $setting_fields = [
            'claude_model',
            'amazon_marketplace',
            'default_post_status',
            'affiliate_disclosure',
            'focus_category',
        ];

        foreach ($setting_fields as $field) {
            if (isset($params[$field])) {
                $settings[$field] = sanitize_text_field($params[$field]);
            }
        }

        // Boolean settings
        $boolean_fields = [
            'auto_insert_schema',
            'enable_price_updates',
            'remove_data_on_uninstall',
        ];

        foreach ($boolean_fields as $field) {
            if (isset($params[$field])) {
                $settings[$field] = (bool) $params[$field];
            }
        }

        // Integer settings
        if (isset($params['cache_duration_hours'])) {
            $settings['cache_duration_hours'] = max(1, min(168, (int) $params['cache_duration_hours']));
        }

        // Save
        update_option('wpb_credentials_encrypted', $credentials);
        update_option('wpb_settings', $settings);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Settings saved successfully.', 'wp-product-builder'),
        ], 200);
    }

    /**
     * Test API connection
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function test_connection(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $api = $request->get_param('api');
        $api_key = $request->get_param('api_key');

        if ($api === 'claude') {
            $client = new ClaudeClient($api_key ?: null);
            $result = $client->testConnection();
        } elseif ($api === 'amazon') {
            $access_key = $request->get_param('access_key');
            $secret_key = $request->get_param('secret_key');
            $partner_tag = $request->get_param('partner_tag');
            $client = new AmazonClient($access_key ?: null, $secret_key ?: null, $partner_tag ?: null);
            $result = $client->testConnection();
        } elseif ($api === 'cj') {
            $cj_api_key = $request->get_param('cj_api_key');
            $cj_website_id = $request->get_param('cj_website_id');
            $client = new CJClient($cj_api_key ?: null, $cj_website_id ?: null);
            $result = $client->testConnection();
        } elseif ($api === 'awin') {
            $awin_api_key = $request->get_param('awin_api_key');
            $awin_publisher_id = $request->get_param('awin_publisher_id');
            $client = new AwinClient($awin_api_key ?: null, $awin_publisher_id ?: null);
            $result = $client->testConnection();
        } else {
            return new WP_Error(
                'invalid_api',
                __('Invalid API specified.', 'wp-product-builder'),
                ['status' => 400]
            );
        }

        if ($result['success']) {
            return new WP_REST_Response($result, 200);
        }

        return new WP_Error(
            'connection_failed',
            $result['message'] ?? __('Connection test failed.', 'wp-product-builder'),
            ['status' => 400]
        );
    }

    /**
     * Get dashboard statistics
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_stats(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $history_table = $wpdb->prefix . 'wpb_content_history';
        $cache_table = $wpdb->prefix . 'wpb_product_cache';
        $usage_table = $wpdb->prefix . 'wpb_api_usage';

        // Content count
        $content_count = $wpdb->get_var("SELECT COUNT(*) FROM {$history_table}");

        // Products cached
        $products_cached = $wpdb->get_var("SELECT COUNT(*) FROM {$cache_table} WHERE expires_at > NOW()");

        // Tokens used (this month)
        $tokens_used = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(tokens_input + tokens_output) FROM {$usage_table}
             WHERE api_type = 'claude' AND date_recorded >= %s",
            date('Y-m-01')
        ));

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'content_count' => (int) $content_count,
                'products_cached' => (int) $products_cached,
                'tokens_used' => (int) ($tokens_used ?? 0),
            ],
        ], 200);
    }

    /**
     * Clear product cache
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function clear_cache(WP_REST_Request $request): WP_REST_Response {
        $repo = new ProductRepository();
        $deleted = $repo->clearAll();

        return new WP_REST_Response([
            'success' => true,
            'message' => sprintf(
                __('Cache cleared. %d entries removed.', 'wp-product-builder'),
                $deleted
            ),
        ], 200);
    }
}
