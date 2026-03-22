<?php
/**
 * Plugin Name: Nito Product Builder
 * Plugin URI: https://github.com/hanvansolo/WP_Product_Builder
 * Description: AI-powered affiliate content generator using Claude API with Amazon, CJ Affiliate, and Awin support. Create product reviews, roundups, comparisons, and more.
 * Version: 2.2.2
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Ed Deyzel
 * Author URI: https://github.com/hanvansolo
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-product-builder
 * Domain Path: /languages
 * Update URI: https://github.com/hanvansolo/WP_Product_Builder
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin version
define('WPB_VERSION', '2.2.2');

// Plugin paths
define('WPB_PLUGIN_FILE', __FILE__);
define('WPB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPB_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Minimum requirements
define('WPB_MIN_PHP_VERSION', '8.1');
define('WPB_MIN_WP_VERSION', '6.0');

/**
 * Check if the server meets the minimum requirements
 */
function wpb_check_requirements(): bool {
    $meets_requirements = true;
    $errors = [];

    // Check PHP version
    if (version_compare(PHP_VERSION, WPB_MIN_PHP_VERSION, '<')) {
        $meets_requirements = false;
        $errors[] = sprintf(
            /* translators: 1: Required PHP version, 2: Current PHP version */
            __('Nito Product Builder requires PHP %1$s or higher. You are running PHP %2$s.', 'wp-product-builder'),
            WPB_MIN_PHP_VERSION,
            PHP_VERSION
        );
    }

    // Check WordPress version
    global $wp_version;
    if (version_compare($wp_version, WPB_MIN_WP_VERSION, '<')) {
        $meets_requirements = false;
        $errors[] = sprintf(
            /* translators: 1: Required WordPress version, 2: Current WordPress version */
            __('Nito Product Builder requires WordPress %1$s or higher. You are running WordPress %2$s.', 'wp-product-builder'),
            WPB_MIN_WP_VERSION,
            $wp_version
        );
    }

    // Check for required PHP extensions
    $required_extensions = ['openssl', 'json', 'curl'];
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $meets_requirements = false;
            $errors[] = sprintf(
                /* translators: %s: PHP extension name */
                __('Nito Product Builder requires the %s PHP extension.', 'wp-product-builder'),
                $ext
            );
        }
    }

    // Display admin notice if requirements not met
    if (!$meets_requirements) {
        add_action('admin_notices', function() use ($errors) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__('Nito Product Builder', 'wp-product-builder') . '</strong></p>';
            echo '<ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul></div>';
        });
    }

    return $meets_requirements;
}

/**
 * Load autoloader (Composer or fallback)
 */
function wpb_load_autoloader(): bool {
    // Try Composer autoloader first
    $composer_autoloader = WPB_PLUGIN_DIR . 'vendor/autoload.php';
    if (file_exists($composer_autoloader)) {
        require_once $composer_autoloader;
        return true;
    }

    // Fall back to simple PSR-4 autoloader
    $fallback_autoloader = WPB_PLUGIN_DIR . 'autoload.php';
    if (file_exists($fallback_autoloader)) {
        require_once $fallback_autoloader;
        return true;
    }

    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Nito Product Builder: Autoloader not found.', 'wp-product-builder');
        echo '</p></div>';
    });
    return false;
}

/**
 * Initialize the plugin
 */
function wpb_init(): void {
    // Check requirements
    if (!wpb_check_requirements()) {
        return;
    }

    // Load autoloader
    if (!wpb_load_autoloader()) {
        return;
    }

    // Initialize the plugin
    \WPProductBuilder\Plugin::getInstance();
}

// Hook into plugins_loaded to initialize
add_action('plugins_loaded', 'wpb_init');

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function() {
    // Check requirements before activation
    if (!wpb_check_requirements()) {
        deactivate_plugins(WPB_PLUGIN_BASENAME);
        wp_die(
            esc_html__('Nito Product Builder cannot be activated. Please check the server requirements.', 'wp-product-builder'),
            esc_html__('Plugin Activation Error', 'wp-product-builder'),
            ['back_link' => true]
        );
    }

    // Load autoloader for activation
    if (!wpb_load_autoloader()) {
        deactivate_plugins(WPB_PLUGIN_BASENAME);
        wp_die(
            esc_html__('Nito Product Builder: Autoloader not found.', 'wp-product-builder'),
            esc_html__('Plugin Activation Error', 'wp-product-builder'),
            ['back_link' => true]
        );
    }

    \WPProductBuilder\Activator::activate();
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    if (wpb_load_autoloader()) {
        \WPProductBuilder\Deactivator::deactivate();
    }
});
