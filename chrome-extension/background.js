/**
 * Nito Product Builder - Background Service Worker
 * Minimal - all work is done by inject.js content script
 */

// Keep service worker alive
chrome.runtime.onInstalled.addListener(function() {
  console.log('Nito Product Builder extension installed');
});
