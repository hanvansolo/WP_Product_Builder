/**
 * Nito Product Builder - Content Script
 * Bridges between the WordPress plugin page and the background worker.
 */

(function() {
  // Only activate on pages with our plugin
  if (!document.getElementById('wpb-generator') && !document.getElementById('wpb-settings-form')) {
    return;
  }

  // Signal extension is installed
  var script = document.createElement('script');
  script.textContent = 'window.nitoExtensionInstalled=true;window.dispatchEvent(new CustomEvent("nito-extension-ready",{detail:{version:"1.0.0"}}));';
  document.documentElement.appendChild(script);
  script.remove();

  // Listen for search requests from the page
  window.addEventListener('nito-search-request', function(e) {
    var detail = e.detail;
    var domains = {
      'US': 'www.amazon.com', 'UK': 'www.amazon.co.uk',
      'DE': 'www.amazon.de', 'FR': 'www.amazon.fr',
      'CA': 'www.amazon.ca', 'JP': 'www.amazon.co.jp',
      'IT': 'www.amazon.it', 'ES': 'www.amazon.es',
      'AU': 'www.amazon.com.au'
    };

    var domain = domains[detail.marketplace] || 'www.amazon.co.uk';
    var url = 'https://' + domain + '/s?k=' + encodeURIComponent(detail.query);

    // Send to background worker (has cross-origin permissions)
    chrome.runtime.sendMessage({ action: 'fetchAmazon', url: url }, function(response) {
      if (response && response.success) {
        var products = parseResults(response.html, domain);
        sendToPage(detail.requestId, true, products);
      } else {
        sendToPage(detail.requestId, false, [], response ? response.error : 'Extension error');
      }
    });
  });

  function sendToPage(requestId, success, products, error) {
    var script = document.createElement('script');
    var data = JSON.stringify({ requestId: requestId, success: success, products: products, error: error || '' });
    script.textContent = 'window.dispatchEvent(new CustomEvent("nito-search-response",{detail:' + data + '}));';
    document.documentElement.appendChild(script);
    script.remove();
  }

  function parseResults(html, domain) {
    var parser = new DOMParser();
    var doc = parser.parseFromString(html, 'text/html');
    var products = [];
    var items = doc.querySelectorAll('[data-asin][data-component-type="s-search-result"]');

    items.forEach(function(item) {
      var asin = item.getAttribute('data-asin');
      if (!asin || asin.length !== 10) return;

      var titleEl = item.querySelector('h2 span');
      var title = titleEl ? titleEl.textContent.trim() : '';
      if (!title) return;

      var priceEl = item.querySelector('.a-price .a-offscreen');
      var price = priceEl ? priceEl.textContent.trim() : null;

      var imgEl = item.querySelector('img.s-image');
      var imageUrl = imgEl ? imgEl.getAttribute('src') : '';

      var ratingEl = item.querySelector('.a-icon-alt');
      var ratingText = ratingEl ? ratingEl.textContent : '';
      var ratingMatch = ratingText.match(/([0-9.]+)/);
      var rating = ratingMatch ? parseFloat(ratingMatch[1]) : null;

      products.push({
        product_id: asin, asin: asin, network: 'amazon',
        title: title, price: price, image_url: imageUrl,
        rating: rating, affiliate_url: 'https://' + domain + '/dp/' + asin,
        marketplace: '', merchant_name: null, source: 'chrome_extension'
      });
    });

    return products.slice(0, 20);
  }
})();
