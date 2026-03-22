/**
 * Nito Product Builder - Chrome Extension Background Service Worker
 *
 * Handles search requests from the WordPress plugin and coordinates
 * with the content script to extract product data from Amazon.
 */

// Listen for messages from web pages (the WP plugin)
chrome.runtime.onMessageExternal.addListener(
  function(request, sender, sendResponse) {
    if (request.action === 'ping') {
      // Plugin checking if extension is installed
      sendResponse({ installed: true, version: '1.0.0' });
      return true;
    }

    if (request.action === 'search') {
      // Plugin wants to search Amazon
      handleSearch(request, sendResponse);
      return true; // Keep channel open for async response
    }
  }
);

// Also listen for internal messages from content script
chrome.runtime.onMessage.addListener(
  function(request, sender, sendResponse) {
    if (request.action === 'productsExtracted') {
      // Content script has extracted products - forward to any waiting callbacks
      if (pendingSearchCallback) {
        pendingSearchCallback(request.products);
        pendingSearchCallback = null;
      }
    }
  }
);

let pendingSearchCallback = null;

async function handleSearch(request, sendResponse) {
  const { query, marketplace } = request;

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
  const searchUrl = `https://${domain}/s?k=${encodeURIComponent(query)}`;

  try {
    // Fetch the search page using the browser's own credentials/cookies
    const response = await fetch(searchUrl, {
      credentials: 'include',
      headers: {
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language': 'en-GB,en;q=0.9'
      }
    });

    const html = await response.text();

    // Parse the HTML to extract products
    const products = parseSearchResults(html, domain);

    sendResponse({ success: true, products: products });
  } catch (error) {
    sendResponse({ success: false, error: error.message });
  }
}

function parseSearchResults(html, domain) {
  const parser = new DOMParser();
  const doc = parser.parseFromString(html, 'text/html');
  const products = [];

  const items = doc.querySelectorAll('[data-asin][data-component-type="s-search-result"]');

  items.forEach(function(item) {
    const asin = item.getAttribute('data-asin');
    if (!asin || asin.length !== 10) return;

    // Title
    const titleEl = item.querySelector('h2 span');
    const title = titleEl ? titleEl.textContent.trim() : '';
    if (!title) return;

    // Price
    const priceEl = item.querySelector('.a-price .a-offscreen');
    const price = priceEl ? priceEl.textContent.trim() : null;

    // Image
    const imgEl = item.querySelector('img.s-image');
    const imageUrl = imgEl ? imgEl.getAttribute('src') : '';

    // Rating
    const ratingEl = item.querySelector('.a-icon-alt');
    const ratingText = ratingEl ? ratingEl.textContent : '';
    const ratingMatch = ratingText.match(/([0-9.]+)/);
    const rating = ratingMatch ? parseFloat(ratingMatch[1]) : null;

    // Review count
    const reviewEl = item.querySelector('[aria-label*="star"] + span, .a-size-base.s-underline-text');
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
      affiliate_url: `https://${domain}/dp/${asin}`,
      marketplace: '',
      merchant_name: null,
      source: 'chrome_extension'
    });
  });

  return products.slice(0, 20);
}
