<?php
/**
 * GitHub Plugin Updater
 *
 * Checks GitHub releases for plugin updates and integrates
 * with WordPress's built-in update system.
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Updater;

/**
 * Handles checking GitHub for plugin updates
 */
class GitHubUpdater {
    /**
     * GitHub repository owner
     */
    private const REPO_OWNER = 'hanvansolo';

    /**
     * GitHub repository name
     */
    private const REPO_NAME = 'WP_Product_Builder';

    /**
     * GitHub API base URL
     */
    private const API_URL = 'https://api.github.com/repos';

    /**
     * Cache transient key
     */
    private const CACHE_KEY = 'wpb_github_update_check';

    /**
     * Cache duration in seconds (6 hours)
     */
    private const CACHE_DURATION = 21600;

    /**
     * Current plugin version
     */
    private string $currentVersion;

    /**
     * Plugin basename (e.g. wp-product-builder/wp-product-builder.php)
     */
    private string $pluginBasename;

    /**
     * Plugin slug
     */
    private string $pluginSlug;

    /**
     * Constructor
     */
    public function __construct() {
        $this->currentVersion = WPB_VERSION;
        $this->pluginBasename = WPB_PLUGIN_BASENAME;
        $this->pluginSlug = 'wp-product-builder';
    }

    /**
     * Register WordPress hooks for update checking
     */
    public function register(): void {
        // Hook into update check
        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkForUpdate']);

        // Hook into plugin info popup
        add_filter('plugins_api', [$this, 'pluginInfo'], 20, 3);

        // Hook into post-install to fix directory name
        add_filter('upgrader_post_install', [$this, 'postInstall'], 10, 3);

        // Register REST route for manual update check
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST API routes for update actions
     */
    public function registerRoutes(): void {
        register_rest_route('wp-product-builder/v1', '/update/check', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'restCheckForUpdate'],
            'permission_callback' => function () {
                return current_user_can('update_plugins');
            },
        ]);
    }

    /**
     * REST handler: Check for updates
     */
    public function restCheckForUpdate(\WP_REST_Request $request): \WP_REST_Response {
        // Clear cache to force fresh check
        delete_transient(self::CACHE_KEY);

        $release = $this->getLatestRelease();

        if (!$release) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Could not check for updates. GitHub may be unreachable.', 'wp-product-builder'),
            ], 200);
        }

        $latestVersion = ltrim($release['tag_name'] ?? '', 'v');
        $hasUpdate = version_compare($this->currentVersion, $latestVersion, '<');

        return new \WP_REST_Response([
            'success' => true,
            'current_version' => $this->currentVersion,
            'latest_version' => $latestVersion,
            'has_update' => $hasUpdate,
            'download_url' => $release['zipball_url'] ?? '',
            'release_notes' => $release['body'] ?? '',
            'published_at' => $release['published_at'] ?? '',
            'update_url' => $hasUpdate ? admin_url('update-core.php') : '',
            'message' => $hasUpdate
                ? sprintf(__('Update available: v%s', 'wp-product-builder'), $latestVersion)
                : __('You are running the latest version.', 'wp-product-builder'),
        ], 200);
    }

    /**
     * Check GitHub for a newer release
     *
     * @param object $transient WordPress update transient
     * @return object Modified transient
     */
    public function checkForUpdate(object $transient): object {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->getLatestRelease();

        if (!$release) {
            return $transient;
        }

        $latestVersion = ltrim($release['tag_name'] ?? '', 'v');

        if (version_compare($this->currentVersion, $latestVersion, '<')) {
            $transient->response[$this->pluginBasename] = (object) [
                'slug' => $this->pluginSlug,
                'plugin' => $this->pluginBasename,
                'new_version' => $latestVersion,
                'url' => "https://github.com/" . self::REPO_OWNER . "/" . self::REPO_NAME,
                'package' => $release['zipball_url'] ?? '',
                'icons' => [],
                'banners' => [],
                'tested' => '',
                'requires_php' => '8.1',
                'compatibility' => new \stdClass(),
            ];
        }

        return $transient;
    }

    /**
     * Provide plugin info for the WordPress plugin details popup
     *
     * @param false|object|array $result
     * @param string $action
     * @param object $args
     * @return false|object
     */
    public function pluginInfo($result, string $action, object $args) {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== $this->pluginSlug) {
            return $result;
        }

        $release = $this->getLatestRelease();

        if (!$release) {
            return $result;
        }

        $latestVersion = ltrim($release['tag_name'] ?? '', 'v');

        return (object) [
            'name' => 'WP Product Builder',
            'slug' => $this->pluginSlug,
            'version' => $latestVersion,
            'author' => '<a href="https://github.com/hanvansolo">Ed Deyzel</a>',
            'homepage' => "https://github.com/" . self::REPO_OWNER . "/" . self::REPO_NAME,
            'requires' => '6.0',
            'tested' => '',
            'requires_php' => '8.1',
            'download_link' => $release['zipball_url'] ?? '',
            'sections' => [
                'description' => __('AI-powered affiliate content generator with Amazon, CJ Affiliate, and Awin support.', 'wp-product-builder'),
                'changelog' => nl2br(esc_html($release['body'] ?? '')),
            ],
        ];
    }

    /**
     * Fix directory name after GitHub zip install
     *
     * GitHub zips extract to owner-repo-hash/, we need wp-product-builder/
     *
     * @param bool $response
     * @param array $hook_extra
     * @param array $result
     * @return array
     */
    public function postInstall(bool $response, array $hook_extra, array $result): array {
        global $wp_filesystem;

        $pluginFolder = WP_PLUGIN_DIR . '/' . $this->pluginSlug;
        $wp_filesystem->move($result['destination'], $pluginFolder);
        $result['destination'] = $pluginFolder;

        // Re-activate if it was active
        if (is_plugin_active($this->pluginBasename)) {
            activate_plugin($this->pluginBasename);
        }

        return $result;
    }

    /**
     * Get the latest release from GitHub (cached)
     *
     * @return array|null Release data or null on failure
     */
    private function getLatestRelease(): ?array {
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false) {
            return $cached;
        }

        $url = self::API_URL . '/' . self::REPO_OWNER . '/' . self::REPO_NAME . '/releases/latest';

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WP-Product-Builder/' . $this->currentVersion,
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // Try tags as fallback (if no releases exist)
            return $this->getLatestTag();
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($release['tag_name'])) {
            set_transient(self::CACHE_KEY, $release, self::CACHE_DURATION);
            return $release;
        }

        return null;
    }

    /**
     * Fallback: get latest tag if no releases exist
     *
     * @return array|null Simulated release data
     */
    private function getLatestTag(): ?array {
        $url = self::API_URL . '/' . self::REPO_OWNER . '/' . self::REPO_NAME . '/tags?per_page=1';

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WP-Product-Builder/' . $this->currentVersion,
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $tags = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($tags[0]['name'])) {
            $tag = [
                'tag_name' => $tags[0]['name'],
                'zipball_url' => $tags[0]['zipball_url'] ?? '',
                'body' => '',
                'published_at' => '',
            ];
            set_transient(self::CACHE_KEY, $tag, self::CACHE_DURATION);
            return $tag;
        }

        return null;
    }

    /**
     * Clear the update cache (e.g. after manual check)
     */
    public static function clearCache(): void {
        delete_transient(self::CACHE_KEY);
    }
}
