/**
 * Nito Product Builder - Injector
 *
 * Runs on all pages. Detects if we're on a Nito Product Builder admin page
 * and enables Amazon search communication. Zero config needed.
 */

(function() {
  // Only activate on pages that have our plugin
  if (!document.getElementById('wpb-generator') && !document.getElementById('wpb-settings-form')) {
    return;
  }

  // Signal to the plugin that the extension is installed
  window.nitoExtensionInstalled = true;
  window.dispatchEvent(new CustomEvent('nito-extension-ready', { detail: { version: '1.0.0' } }));

  // Listen for search requests from the plugin
  window.addEventListener('nito-search-request', async function(e) {
    const { query, marketplace, requestId } = e.detail;

    const domains = {
      'US': 'www.amazon.com',
      'UK': 'www.amazon.co.uk',
      'DE': 'www.amazon.de',
      'FR': 'www.amazon.fr',
      'CA': 'www.amazon.ca',
      'JP': 'www.amazon.co.jp',
      'IT': 'www.amazon.it',
      'ES': 'www.amazon.es',
      'AU': 'www.amazon.com.au'
    };

    const domain = domains[marketplace] || 'www.amazon.co.uk';
    const searchUrl = 'https://' + domain + '/s?k=' + encodeURIComponent(query);

    try {
      const response = await fetch(searchUrl, {
        credentials: 'omit',
        headers: {
          'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
          'Accept-Language': 'en-GB,en;q=0.9'
        }
      });

      const html = await response.text();
      const products = parseAmazonResults(html, domain);

      window.dispatchEvent(new CustomEvent('nito-search-response', {
        detail: { requestId: requestId, success: true, products: products }
      }));
    } catch (error) {
      window.dispatchEvent(new CustomEvent('nito-search-response', {
        detail: { requestId: requestId, success: false, error: error.message }
      }));
    }
  });

  function parseAmazonResults(html, domain) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const products = [];

    const items = doc.querySelectorAll('[data-asin][data-component-type="s-search-result"]');

    items.forEach(function(item) {
      const asin = item.getAttribute('data-asin');
      if (!asin || asin.length !== 10) return;

      const titleEl = item.querySelector('h2 span');
      const title = titleEl ? titleEl.textContent.trim() : '';
      if (!title) return;

      const priceEl = item.querySelector('.a-price .a-offscreen');
      const price = priceEl ? priceEl.textContent.trim() : null;

      const imgEl = item.querySelector('img.s-image');
      const imageUrl = imgEl ? imgEl.getAttribute('src') : '';

      const ratingEl = item.querySelector('.a-icon-alt');
      const ratingText = ratingEl ? ratingEl.textContent : '';
      const ratingMatch = ratingText.match(/([0-9.]+)/);
      const rating = ratingMatch ? parseFloat(ratingMatch[1]) : null;

      const reviewEl = item.querySelector('[aria-label*="star"] ~ span .a-size-base, .s-underline-text');
      const reviewCount = reviewEl ? parseInt(reviewEl.textContent.replace(/[^0-9]/g, '')) || null : null;

      products.push({
        product_id: asin,
        asin: asin,
        network: 'amazon',
        title: title,
        price: price,
        image_url: imageUrl,
        rating: rating,
        review_count: reviewCount,
        affiliate_url: 'https://' + domain + '/dp/' + asin,
        marketplace: '',
        merchant_name: null,
        source: 'chrome_extension'
      });
    });

    return products.slice(0, 20);
  }
})();
