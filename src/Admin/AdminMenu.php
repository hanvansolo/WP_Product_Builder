<?php
/**
 * Admin Menu Registration
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Admin;

/**
 * Handles admin menu registration and page routing
 */
class AdminMenu {
    /**
     * Menu slug prefix
     */
    private const MENU_SLUG = 'wp-product-builder';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'registerMenus']);
        add_action('admin_init', [$this, 'handleRedirectAfterActivation']);
    }

    /**
     * Register admin menus
     */
    public function registerMenus(): void {
        // Main menu
        add_menu_page(
            __('Nito Product Builder', 'wp-product-builder'),
            __('Nito Builder', 'wp-product-builder'),
            'wpb_generate_content',
            self::MENU_SLUG,
            [$this, 'renderDashboard'],
            'dashicons-products',
            30
        );

        // Dashboard (same as main menu)
        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard', 'wp-product-builder'),
            __('Dashboard', 'wp-product-builder'),
            'wpb_generate_content',
            self::MENU_SLUG,
            [$this, 'renderDashboard']
        );

        // Generate Content
        add_submenu_page(
            self::MENU_SLUG,
            __('Generate Content', 'wp-product-builder'),
            __('Generate Content', 'wp-product-builder'),
            'wpb_generate_content',
            self::MENU_SLUG . '-generate',
            [$this, 'renderGenerator']
        );

        // Content History
        add_submenu_page(
            self::MENU_SLUG,
            __('Content History', 'wp-product-builder'),
            __('Content History', 'wp-product-builder'),
            'wpb_view_history',
            self::MENU_SLUG . '-history',
            [$this, 'renderHistory']
        );

        // Templates
        add_submenu_page(
            self::MENU_SLUG,
            __('Templates', 'wp-product-builder'),
            __('Templates', 'wp-product-builder'),
            'wpb_manage_templates',
            self::MENU_SLUG . '-templates',
            [$this, 'renderTemplates']
        );

        // Products Cache
        add_submenu_page(
            self::MENU_SLUG,
            __('Products', 'wp-product-builder'),
            __('Products', 'wp-product-builder'),
            'wpb_generate_content',
            self::MENU_SLUG . '-products',
            [$this, 'renderProducts']
        );

        // WooCommerce Import (only if WooCommerce is active)
        if (class_exists('WooCommerce')) {
            add_submenu_page(
                self::MENU_SLUG,
                __('WooCommerce Import', 'wp-product-builder'),
                __('WooCommerce Import', 'wp-product-builder'),
                'manage_woocommerce',
                self::MENU_SLUG . '-woo-import',
                [$this, 'renderWooImport']
            );
        }

        // Auto Import Jobs
        add_submenu_page(
            self::MENU_SLUG,
            __('Auto Import', 'wp-product-builder'),
            __('Auto Import', 'wp-product-builder'),
            'wpb_generate_content',
            self::MENU_SLUG . '-auto-import',
            [$this, 'renderAutoImport']
        );

        // Bulk Generator
        add_submenu_page(
            self::MENU_SLUG,
            __('Bulk Generator', 'wp-product-builder'),
            __('Bulk Generator', 'wp-product-builder'),
            'wpb_generate_content',
            self::MENU_SLUG . '-bulk-generator',
            [$this, 'renderBulkGenerator']
        );

        // Statistics
        add_submenu_page(
            self::MENU_SLUG,
            __('Statistics', 'wp-product-builder'),
            __('Statistics', 'wp-product-builder'),
            'wpb_generate_content',
            self::MENU_SLUG . '-statistics',
            [$this, 'renderStatistics']
        );

        // Settings
        add_submenu_page(
            self::MENU_SLUG,
            __('Settings', 'wp-product-builder'),
            __('Settings', 'wp-product-builder'),
            'manage_wpb_settings',
            self::MENU_SLUG . '-settings',
            [$this, 'renderSettings']
        );
    }

    /**
     * Redirect to settings page after activation
     */
    public function handleRedirectAfterActivation(): void {
        if (get_transient('wpb_activated')) {
            delete_transient('wpb_activated');

            // Only redirect if not bulk activating
            if (!isset($_GET['activate-multi'])) {
                wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '-settings&welcome=1'));
                exit;
            }
        }
    }

    /**
     * Render Dashboard page
     */
    public function renderDashboard(): void {
        $this->renderPage('dashboard');
    }

    /**
     * Render Generator page
     */
    public function renderGenerator(): void {
        $this->renderPage('generator');
    }

    /**
     * Render History page
     */
    public function renderHistory(): void {
        $this->renderPage('history');
    }

    /**
     * Render Templates page
     */
    public function renderTemplates(): void {
        $this->renderPage('templates');
    }

    /**
     * Render Products page
     */
    public function renderProducts(): void {
        $this->renderPage('products');
    }

    /**
     * Render Settings page
     */
    public function renderSettings(): void {
        $this->renderPage('settings');
    }

    /**
     * Render WooCommerce Import page
     */
    public function renderWooImport(): void {
        $this->renderPage('woo-import');
    }

    /**
     * Render Auto Import page
     */
    public function renderAutoImport(): void {
        $this->renderPage('auto-import');
    }

    /**
     * Render Bulk Generator page
     */
    public function renderBulkGenerator(): void {
        $this->renderPage('bulk-generator');
    }

    /**
     * Render Statistics page
     */
    public function renderStatistics(): void {
        $this->renderPage('statistics');
    }

    /**
     * Render an admin page
     *
     * @param string $page The page to render
     */
    private function renderPage(string $page): void {
        $template_file = WPB_PLUGIN_DIR . "templates/admin/{$page}.php";

        if (file_exists($template_file)) {
            include $template_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html(ucfirst($page)) . '</h1>';
            echo '<div id="wpb-admin-app" data-page="' . esc_attr($page) . '"></div>';
            echo '</div>';
        }
    }

    /**
     * Get menu slug
     */
    public static function getMenuSlug(): string {
        return self::MENU_SLUG;
    }
}
