<?php
/**
 * Gutenberg Block Registrar
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Blocks;

/**
 * Registers Gutenberg blocks for the plugin
 */
class BlockRegistrar {
    /**
     * Available blocks
     */
    private array $blocks = [
        'product-box',
        'product-comparison',
        'product-carousel',
        'deals-box',
    ];

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'registerBlocks']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
    }

    /**
     * Register blocks
     */
    public function registerBlocks(): void {
        // Register block category
        add_filter('block_categories_all', [$this, 'registerBlockCategory'], 10, 2);

        // Blocks will be registered when build files exist
        foreach ($this->blocks as $block) {
            $block_path = WPB_PLUGIN_DIR . "build/blocks/{$block}";

            if (file_exists($block_path . '/block.json')) {
                register_block_type($block_path);
            }
        }
    }

    /**
     * Register custom block category
     *
     * @param array $categories Existing categories
     * @param \WP_Block_Editor_Context $context Block editor context
     * @return array Modified categories
     */
    public function registerBlockCategory(array $categories, $context): array {
        return array_merge([
            [
                'slug' => 'wp-product-builder',
                'title' => __('WP Product Builder', 'wp-product-builder'),
                'icon' => 'cart',
            ],
        ], $categories);
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueueEditorAssets(): void {
        $asset_file = WPB_PLUGIN_DIR . 'build/blocks/index.asset.php';

        if (!file_exists($asset_file)) {
            return;
        }

        $asset = include $asset_file;

        wp_enqueue_script(
            'wpb-blocks-editor',
            WPB_PLUGIN_URL . 'build/blocks/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_localize_script('wpb-blocks-editor', 'wpbBlocks', [
            'apiUrl' => rest_url('wp-product-builder/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'pluginUrl' => WPB_PLUGIN_URL,
        ]);

        wp_enqueue_style(
            'wpb-blocks-editor',
            WPB_PLUGIN_URL . 'build/blocks/index.css',
            ['wp-edit-blocks'],
            WPB_VERSION
        );
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets(): void {
        // Only load if post contains our blocks
        if (!has_block('wp-product-builder/')) {
            return;
        }

        wp_enqueue_style(
            'wpb-blocks-frontend',
            WPB_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            WPB_VERSION
        );
    }
}
