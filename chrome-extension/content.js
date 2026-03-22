/**
 * Nito Product Builder - Content Script
 *
 * Runs on Amazon search pages. Extracts product data and sends
 * it back to the background service worker when requested.
 */

// Listen for messages from the background script
chrome.runtime.onMessage.addListener(function(request, sender, sendResponse) {
  if (request.action === 'extractProducts') {
    const products = extractProducts();
    sendResponse({ products: products });
  }
  return true;
});

function extractProducts() {
  const products = [];
  const items = document.querySelectorAll('[data-asin][data-component-type="s-search-result"]');

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

    products.push({
      product_id: asin,
      asin: asin,
      network: 'amazon',
      title: title,
      price: price,
      image_url: imageUrl,
      rating: rating,
      affiliate_url: window.location.origin + '/dp/' + asin,
      source: 'chrome_extension'
    });
  });

  return products;
}
