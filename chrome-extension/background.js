/**
 * Nito Product Builder - Background Service Worker
 * Handles Amazon fetches (content scripts can't do cross-origin)
 */

chrome.runtime.onMessage.addListener(function(request, sender, sendResponse) {
  if (request.action === 'fetchAmazon') {
    fetchAmazon(request.url)
      .then(function(html) { sendResponse({ success: true, html: html }); })
      .catch(function(err) { sendResponse({ success: false, error: err.message }); });
    return true;
  }
});

async function fetchAmazon(url) {
  const response = await fetch(url, {
    headers: {
      'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
      'Accept-Language': 'en-GB,en;q=0.9'
    }
  });
  return await response.text();
}
