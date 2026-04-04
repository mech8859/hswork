// 禾順系統 PWA Service Worker
var CACHE_NAME = 'hswork-v1';
var urlsToCache = [
  '/css/style.css',
  '/js/app.js'
];

// 安裝：快取基本資源
self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      return cache.addAll(urlsToCache);
    })
  );
  self.skipWaiting();
});

// 啟動：清除舊快取
self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(names) {
      return Promise.all(
        names.filter(function(name) { return name !== CACHE_NAME; })
             .map(function(name) { return caches.delete(name); })
      );
    })
  );
  self.clients.claim();
});

// 攔截請求：網路優先，失敗才用快取
self.addEventListener('fetch', function(event) {
  // 只快取 GET 請求
  if (event.request.method !== 'GET') return;
  // 不快取 API/AJAX 請求
  if (event.request.url.indexOf('action=ajax') !== -1) return;

  event.respondWith(
    fetch(event.request).then(function(response) {
      // 成功取得網路回應，存入快取
      if (response.status === 200) {
        var clone = response.clone();
        caches.open(CACHE_NAME).then(function(cache) {
          cache.put(event.request, clone);
        });
      }
      return response;
    }).catch(function() {
      // 網路失敗，從快取取
      return caches.match(event.request);
    })
  );
});
