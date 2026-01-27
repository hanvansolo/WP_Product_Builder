=== WP Product Builder ===
Contributors: hanvansolo
Tags: affiliate, amazon, ai, content generator, product reviews, claude
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered affiliate content generator using Claude API and Amazon product data.

== Description ==

WP Product Builder is a powerful WordPress plugin that generates high-quality affiliate content using Claude AI and Amazon product data. Create professional product reviews, roundups, comparisons, and more with just a few clicks.

**Features:**

* AI-powered content generation using Claude API
* Amazon product data integration (PA-API or scraper fallback)
* Multiple content types: Reviews, Roundups, Comparisons, Listicles, Deals
* WooCommerce product import
* Bulk article generation with scheduling
* Auto-import products based on keywords
* Statistics and click tracking
* Local image storage
* Gutenberg blocks for product display

**Content Types:**

1. Product Review - Detailed single product reviews
2. Products Roundup - Best X products in a category
3. Products Comparison - Compare 2-4 products side by side
4. Listicle - Numbered list content
5. Deals - Promotional/sale content

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wp-product-builder`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WP Product Builder > Settings to configure your API keys
4. Start generating content!

== Frequently Asked Questions ==

= Do I need Amazon PA-API access? =

No! The plugin includes a scraper fallback for new affiliates who haven't yet qualified for PA-API access (requires 3 sales). Once you have API access, you can configure it for better reliability.

= What AI model is used? =

The plugin uses Claude API from Anthropic. You'll need a Claude API key to generate content.

== Changelog ==

= 1.1.1 =
* Added missing admin templates (templates.php, products.php)
* Fixed fatal error on activation

= 1.1.0 =
* Removed Guzzle HTTP dependency - now uses WordPress HTTP API
* Added fallback autoloader for easier installation (no Composer required)
* Fixed activation issues
* Improved error handling

= 1.0.0 =
* Initial release
* AI content generation with Claude API
* Amazon PA-API integration
* Amazon scraper fallback
* WooCommerce product import
* Bulk article generation
* Auto-import features
* Statistics tracking
* Gutenberg blocks

== Upgrade Notice ==

= 1.1.0 =
This version removes the Composer dependency. You can now install the plugin without running composer install.
