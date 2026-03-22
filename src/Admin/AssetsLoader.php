<?php
/**
 * Admin Assets Loader
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Admin;

/**
 * Handles loading of admin scripts and styles
 */
class AssetsLoader {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook The current admin page hook
     */
    public function enqueueAssets(string $hook): void {
        // Only load on our plugin pages
        if (!$this->isPluginPage($hook)) {
            return;
        }

        // Admin styles
        wp_enqueue_style(
            'wpb-admin',
            WPB_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WPB_VERSION
        );

        // Admin scripts
        wp_enqueue_script(
            'wpb-admin',
            WPB_PLUGIN_URL . 'assets/js/admin/admin.js',
            ['jquery', 'wp-api-fetch'],
            WPB_VERSION,
            true
        );

        // Localize script with data
        wp_localize_script('wpb-admin', 'wpbAdmin', $this->getLocalizedData());

        // Page-specific assets
        $this->enqueuePageAssets($hook);
    }

    /**
     * Check if current page is a plugin page
     *
     * @param string $hook The current admin page hook
     * @return bool
     */
    private function isPluginPage(string $hook): bool {
        // Match any admin page that contains our plugin slug
        return str_contains($hook, 'wp-product-builder');
    }

    /**
     * Enqueue page-specific assets
     *
     * @param string $hook The current admin page hook
     */
    private function enqueuePageAssets(string $hook): void {
        // Settings page
        if (str_contains($hook, 'settings')) {
            wp_enqueue_script(
                'wpb-settings',
                WPB_PLUGIN_URL . 'assets/js/admin/settings.js',
                ['wpb-admin'],
                WPB_VERSION,
                true
            );
        }

        // Generator page
        if (str_contains($hook, 'generate')) {
            wp_enqueue_script(
                'wpb-generator',
                WPB_PLUGIN_URL . 'assets/js/admin/generator.js',
                ['wpb-admin'],
                WPB_VERSION,
                true
            );
        }
    }

    /**
     * Get localized data for scripts
     *
     * @return array
     */
    private function getLocalizedData(): array {
        return [
            'apiUrl' => rest_url('wp-product-builder/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'ajaxNonce' => wp_create_nonce('wpb_ajax'),
            'pluginUrl' => WPB_PLUGIN_URL,
            'settings' => get_option('wpb_settings', []),
            'extensionId' => get_option('wpb_settings', [])['chrome_extension_id'] ?? '',
            'i18n' => [
                'saving' => __('Saving...', 'wp-product-builder'),
                'saved' => __('Saved!', 'wp-product-builder'),
                'error' => __('Error', 'wp-product-builder'),
                'confirm_delete' => __('Are you sure you want to delete this?', 'wp-product-builder'),
                'generating' => __('Generating content...', 'wp-product-builder'),
                'testing_connection' => __('Testing connection...', 'wp-product-builder'),
                'connection_success' => __('Connection successful!', 'wp-product-builder'),
                'connection_failed' => __('Connection failed', 'wp-product-builder'),
            ],
            'contentTypes' => [
                'product_review' => [
                    'label' => __('Product Review', 'wp-product-builder'),
                    'description' => __('Detailed review of a single product', 'wp-product-builder'),
                    'icon' => 'star-filled',
                    'maxProducts' => 1,
                ],
                'products_roundup' => [
                    'label' => __('Products Roundup', 'wp-product-builder'),
                    'description' => __('Best X products in a category', 'wp-product-builder'),
                    'icon' => 'list-view',
                    'maxProducts' => 20,
                ],
                'products_comparison' => [
                    'label' => __('Products Comparison', 'wp-product-builder'),
                    'description' => __('Compare 2-4 products side by side', 'wp-product-builder'),
                    'icon' => 'columns',
                    'maxProducts' => 4,
                ],
                'listicle' => [
                    'label' => __('Listicle', 'wp-product-builder'),
                    'description' => __('Numbered list content', 'wp-product-builder'),
                    'icon' => 'editor-ol',
                    'maxProducts' => 15,
                ],
                'deals' => [
                    'label' => __('Deals', 'wp-product-builder'),
                    'description' => __('Promotional/sale content', 'wp-product-builder'),
                    'icon' => 'tag',
                    'maxProducts' => 10,
                ],
            ],
        ];
    }
}
