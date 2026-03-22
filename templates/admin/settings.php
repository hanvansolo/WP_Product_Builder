<?php
/**
 * Admin Settings Template
 *
 * @package WPProductBuilder
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('wpb_settings', []);
$credentials = get_option('wpb_credentials_encrypted', []);
$encryption = new \WPProductBuilder\Encryption\EncryptionService();

// Mask credentials for display
$claude_key_display = !empty($credentials['claude_api_key'])
    ? \WPProductBuilder\Encryption\EncryptionService::mask($encryption->decrypt($credentials['claude_api_key']))
    : '';
$amazon_access_display = !empty($credentials['amazon_access_key'])
    ? \WPProductBuilder\Encryption\EncryptionService::mask($encryption->decrypt($credentials['amazon_access_key']))
    : '';
$amazon_secret_display = !empty($credentials['amazon_secret_key'])
    ? \WPProductBuilder\Encryption\EncryptionService::mask($encryption->decrypt($credentials['amazon_secret_key']))
    : '';
$cj_key_display = !empty($credentials['cj_api_key'])
    ? \WPProductBuilder\Encryption\EncryptionService::mask($encryption->decrypt($credentials['cj_api_key']))
    : '';
$awin_key_display = !empty($credentials['awin_api_key'])
    ? \WPProductBuilder\Encryption\EncryptionService::mask($encryption->decrypt($credentials['awin_api_key']))
    : '';

$marketplaces = [
    'US' => 'United States (amazon.com)',
    'UK' => 'United Kingdom (amazon.co.uk)',
    'DE' => 'Germany (amazon.de)',
    'FR' => 'France (amazon.fr)',
    'CA' => 'Canada (amazon.ca)',
    'JP' => 'Japan (amazon.co.jp)',
    'IT' => 'Italy (amazon.it)',
    'ES' => 'Spain (amazon.es)',
    'AU' => 'Australia (amazon.com.au)',
];

$claude_models = [
    'claude-sonnet-4-20250514' => 'Claude Sonnet 4 (Recommended)',
    'claude-opus-4-20250514' => 'Claude Opus 4 (Most Capable)',
];

// Check if welcome redirect
$is_welcome = isset($_GET['welcome']) && $_GET['welcome'] === '1';
?>
<div class="wrap wpb-admin-wrap">
    <h1><?php esc_html_e('Settings', 'wp-product-builder'); ?></h1>

    <?php if ($is_welcome): ?>
    <div class="notice notice-info is-dismissible">
        <p>
            <strong><?php esc_html_e('Welcome to WP Product Builder!', 'wp-product-builder'); ?></strong>
            <?php esc_html_e('Please configure your API keys below to get started.', 'wp-product-builder'); ?>
        </p>
    </div>
    <?php endif; ?>

    <div id="wpb-settings-notices"></div>

    <form id="wpb-settings-form" class="wpb-settings-form">
        <?php wp_nonce_field('wpb_settings', 'wpb_settings_nonce'); ?>

        <!-- Claude API Settings -->
        <div class="wpb-card">
            <h2>
                <span class="dashicons dashicons-admin-generic"></span>
                <?php esc_html_e('Claude API Settings', 'wp-product-builder'); ?>
            </h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="claude_api_key"><?php esc_html_e('API Key', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <input type="password"
                               id="claude_api_key"
                               name="claude_api_key"
                               class="regular-text"
                               placeholder="<?php echo $claude_key_display ? esc_attr($claude_key_display) : esc_attr__('Enter your Claude API key', 'wp-product-builder'); ?>"
                               autocomplete="off">
                        <button type="button" class="button wpb-toggle-password" data-target="claude_api_key">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        <button type="button" class="button wpb-test-connection" data-api="claude">
                            <?php esc_html_e('Test Connection', 'wp-product-builder'); ?>
                        </button>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: Link to Anthropic console */
                                esc_html__('Get your API key from the %s', 'wp-product-builder'),
                                '<a href="https://console.anthropic.com/" target="_blank">' . esc_html__('Anthropic Console', 'wp-product-builder') . '</a>'
                            );
                            ?>
                        </p>
                        <div class="wpb-connection-result" id="claude-connection-result"></div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="claude_model"><?php esc_html_e('Model', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <select id="claude_model" name="claude_model">
                            <?php foreach ($claude_models as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['claude_model'] ?? 'claude-sonnet-4-20250514', $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Sonnet 4 is faster and more cost-effective. Opus 4 produces higher quality content.', 'wp-product-builder'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Amazon PA-API Settings -->
        <div class="wpb-card">
            <h2>
                <span class="dashicons dashicons-cart"></span>
                <?php esc_html_e('Amazon PA-API Settings', 'wp-product-builder'); ?>
            </h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="amazon_access_key"><?php esc_html_e('Access Key ID', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <input type="password"
                               id="amazon_access_key"
                               name="amazon_access_key"
                               class="regular-text"
                               placeholder="<?php echo $amazon_access_display ? esc_attr($amazon_access_display) : esc_attr__('Enter your Access Key ID', 'wp-product-builder'); ?>"
                               autocomplete="off">
                        <button type="button" class="button wpb-toggle-password" data-target="amazon_access_key">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="amazon_secret_key"><?php esc_html_e('Secret Access Key', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <input type="password"
                               id="amazon_secret_key"
                               name="amazon_secret_key"
                               class="regular-text"
                               placeholder="<?php echo $amazon_secret_display ? esc_attr($amazon_secret_display) : esc_attr__('Enter your Secret Access Key', 'wp-product-builder'); ?>"
                               autocomplete="off">
                        <button type="button" class="button wpb-toggle-password" data-target="amazon_secret_key">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="amazon_partner_tag"><?php esc_html_e('Partner Tag', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="amazon_partner_tag"
                               name="amazon_partner_tag"
                               class="regular-text"
                               value="<?php echo esc_attr($credentials['amazon_partner_tag'] ?? ''); ?>"
                               placeholder="yourstore-20">
                        <button type="button" class="button wpb-test-connection" data-api="amazon">
                            <?php esc_html_e('Test Connection', 'wp-product-builder'); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('Your Amazon Associates tracking ID (e.g., yourstore-20)', 'wp-product-builder'); ?>
                        </p>
                        <div class="wpb-connection-result" id="amazon-connection-result"></div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="amazon_marketplace"><?php esc_html_e('Marketplace', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <select id="amazon_marketplace" name="amazon_marketplace">
                            <?php foreach ($marketplaces as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['amazon_marketplace'] ?? 'US', $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- CJ Affiliate Settings -->
        <div class="wpb-card">
            <h2>
                <span class="dashicons dashicons-networking"></span>
                <?php esc_html_e('CJ Affiliate Settings', 'wp-product-builder'); ?>
                <span class="wpb-optional-badge"><?php esc_html_e('Optional', 'wp-product-builder'); ?></span>
            </h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="cj_api_key"><?php esc_html_e('API Key', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <input type="password"
                               id="cj_api_key"
                               name="cj_api_key"
                               class="regular-text"
                               placeholder="<?php echo $cj_key_display ? esc_attr($cj_key_display) : esc_attr__('Enter your CJ API key', 'wp-product-builder'); ?>"
                               autocomplete="off">
                        <button type="button" class="button wpb-toggle-password" data-target="cj_api_key">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        <p class="description">
                            <?php
                            printf(
                                esc_html__('Get your API key from the %s', 'wp-product-builder'),
                                '<a href="https://developers.cj.com/" target="_blank">' . esc_html__('CJ Developer Portal', 'wp-product-builder') . '</a>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cj_website_id"><?php esc_html_e('Website ID', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="cj_website_id"
                               name="cj_website_id"
                               class="regular-text"
                               value="<?php echo esc_attr($credentials['cj_website_id'] ?? ''); ?>"
                               placeholder="<?php esc_attr_e('Enter your CJ Website ID', 'wp-product-builder'); ?>">
                        <button type="button" class="button wpb-test-connection" data-api="cj">
                            <?php esc_html_e('Test Connection', 'wp-product-builder'); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('Your CJ publisher website ID, found in your CJ account settings.', 'wp-product-builder'); ?>
                        </p>
                        <div class="wpb-connection-result" id="cj-connection-result"></div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Awin Settings -->
        <div class="wpb-card">
            <h2>
                <span class="dashicons dashicons-networking"></span>
                <?php esc_html_e('Awin Settings', 'wp-product-builder'); ?>
                <span class="wpb-optional-badge"><?php esc_html_e('Optional', 'wp-product-builder'); ?></span>
            </h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="awin_api_key"><?php esc_html_e('API Key', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <input type="password"
                               id="awin_api_key"
                               name="awin_api_key"
                               class="regular-text"
                               placeholder="<?php echo $awin_key_display ? esc_attr($awin_key_display) : esc_attr__('Enter your Awin API key', 'wp-product-builder'); ?>"
                               autocomplete="off">
                        <button type="button" class="button wpb-toggle-password" data-target="awin_api_key">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        <p class="description">
                            <?php
                            printf(
                                esc_html__('Get your API key from the %s', 'wp-product-builder'),
                                '<a href="https://wiki.awin.com/index.php/Publisher_API" target="_blank">' . esc_html__('Awin Publisher API', 'wp-product-builder') . '</a>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="awin_publisher_id"><?php esc_html_e('Publisher ID', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="awin_publisher_id"
                               name="awin_publisher_id"
                               class="regular-text"
                               value="<?php echo esc_attr($credentials['awin_publisher_id'] ?? ''); ?>"
                               placeholder="<?php esc_attr_e('Enter your Awin Publisher ID', 'wp-product-builder'); ?>">
                        <button type="button" class="button wpb-test-connection" data-api="awin">
                            <?php esc_html_e('Test Connection', 'wp-product-builder'); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('Your numeric Awin publisher ID, found in your Awin dashboard.', 'wp-product-builder'); ?>
                        </p>
                        <div class="wpb-connection-result" id="awin-connection-result"></div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Content Settings -->
        <div class="wpb-card">
            <h2>
                <span class="dashicons dashicons-edit"></span>
                <?php esc_html_e('Content Settings', 'wp-product-builder'); ?>
            </h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="default_post_status"><?php esc_html_e('Default Post Status', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <select id="default_post_status" name="default_post_status">
                            <option value="draft" <?php selected($settings['default_post_status'] ?? 'draft', 'draft'); ?>>
                                <?php esc_html_e('Draft', 'wp-product-builder'); ?>
                            </option>
                            <option value="publish" <?php selected($settings['default_post_status'] ?? 'draft', 'publish'); ?>>
                                <?php esc_html_e('Published', 'wp-product-builder'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="auto_insert_schema"><?php esc_html_e('Auto-insert Schema', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   id="auto_insert_schema"
                                   name="auto_insert_schema"
                                   value="1"
                                   <?php checked($settings['auto_insert_schema'] ?? true); ?>>
                            <?php esc_html_e('Automatically add JSON-LD schema markup to generated content', 'wp-product-builder'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="affiliate_disclosure"><?php esc_html_e('Affiliate Disclosure', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <textarea id="affiliate_disclosure"
                                  name="affiliate_disclosure"
                                  rows="3"
                                  class="large-text"><?php echo esc_textarea($settings['affiliate_disclosure'] ?? ''); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('This disclosure will be automatically added to generated content.', 'wp-product-builder'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Cache Settings -->
        <div class="wpb-card">
            <h2>
                <span class="dashicons dashicons-database"></span>
                <?php esc_html_e('Cache Settings', 'wp-product-builder'); ?>
            </h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="cache_duration_hours"><?php esc_html_e('Cache Duration', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="cache_duration_hours"
                               name="cache_duration_hours"
                               value="<?php echo esc_attr($settings['cache_duration_hours'] ?? 24); ?>"
                               min="1"
                               max="168"
                               class="small-text">
                        <?php esc_html_e('hours', 'wp-product-builder'); ?>
                        <p class="description">
                            <?php esc_html_e('How long to cache product data from Amazon (1-168 hours).', 'wp-product-builder'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="enable_price_updates"><?php esc_html_e('Auto Price Updates', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   id="enable_price_updates"
                                   name="enable_price_updates"
                                   value="1"
                                   <?php checked($settings['enable_price_updates'] ?? false); ?>>
                            <?php esc_html_e('Automatically update product prices in published content', 'wp-product-builder'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"></th>
                    <td>
                        <button type="button" class="button" id="wpb-clear-cache">
                            <?php esc_html_e('Clear Product Cache', 'wp-product-builder'); ?>
                        </button>
                        <span id="wpb-cache-status"></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Plugin Updates -->
        <div class="wpb-card">
            <h2>
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Plugin Updates', 'wp-product-builder'); ?>
            </h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Current Version', 'wp-product-builder'); ?></th>
                    <td>
                        <strong>v<?php echo esc_html(WPB_VERSION); ?></strong>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Latest Version', 'wp-product-builder'); ?></th>
                    <td>
                        <span id="wpb-latest-version">—</span>
                        <span id="wpb-update-badge" style="display:none;" class="update-plugins count-1">
                            <span class="plugin-count" id="wpb-update-badge-text"></span>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"></th>
                    <td>
                        <button type="button" class="button" id="wpb-check-update">
                            <span class="dashicons dashicons-update" style="margin-top:4px;"></span>
                            <?php esc_html_e('Check for Updates', 'wp-product-builder'); ?>
                        </button>
                        <a href="<?php echo esc_url(admin_url('update-core.php')); ?>" class="button button-primary" id="wpb-do-update" style="display:none;">
                            <span class="dashicons dashicons-download" style="margin-top:4px;"></span>
                            <?php esc_html_e('Update Now', 'wp-product-builder'); ?>
                        </a>
                        <span id="wpb-update-status"></span>
                        <div id="wpb-release-notes" style="display:none; margin-top:10px;">
                            <p class="description"><strong><?php esc_html_e('Release Notes:', 'wp-product-builder'); ?></strong></p>
                            <div id="wpb-release-notes-content" style="background:#f9f9f9;padding:10px;border-left:4px solid #0073aa;margin-top:5px;"></div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Advanced Settings -->
        <div class="wpb-card">
            <h2>
                <span class="dashicons dashicons-admin-tools"></span>
                <?php esc_html_e('Advanced Settings', 'wp-product-builder'); ?>
            </h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="remove_data_on_uninstall"><?php esc_html_e('Data Removal', 'wp-product-builder'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   id="remove_data_on_uninstall"
                                   name="remove_data_on_uninstall"
                                   value="1"
                                   <?php checked($settings['remove_data_on_uninstall'] ?? false); ?>>
                            <?php esc_html_e('Remove all plugin data when uninstalling', 'wp-product-builder'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Warning: This will delete all content history, cached products, and templates.', 'wp-product-builder'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary button-hero" id="wpb-save-settings">
                <?php esc_html_e('Save Settings', 'wp-product-builder'); ?>
            </button>
            <span class="spinner"></span>
        </p>
    </form>
</div>
