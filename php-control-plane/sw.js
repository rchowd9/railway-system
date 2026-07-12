const CACHE_NAME = 'mta-monitor-v2';

// Assets to cache for offline capabilities (Static Assets Only)
const ASSETS_TO_CACHE = [
  '/',
  '/timetable.php',
  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
];

// Install Event - Cache Core Layout UI Framework Shell
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('💾 Core App Shell cached successfully.');
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

// Activate Event - Clear old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            console.log('🗑️ Clearing obsolete cache profile:', key);
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch Event - Intercept requests safely without breaking event loops
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // CRITICAL FIX: Explicitly bypass real-time server streaming channels
  if (url.pathname.includes('stream.php') || url.pathname.includes('alert-stream.php')) {
    // Return early without calling event.respondWith(), letting the browser stream directly
    return;
  }

  // Handle standard layouts, map tiles, and script asset layers
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }

      // If missing from cache, fetch dynamically over the network pipe
      return fetch(event.request)
        .then((networkResponse) => {
          // Verify response is structurally sound before attempting a storage commit
          if (!networkResponse || networkResponse.status !== 200 || networkResponse.type !== 'basic') {
            return networkResponse;
          }

          // Dynamically cache valid assets for offline performance gains
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });

          return networkResponse;
        })
        .catch(() => {
          // Return a structured offline response rather than rejecting the promise if network goes down
          if (event.request.mode === 'navigate') {
            return new Response(
              '<h1>Subway Station Offline</h1><p>Check terminal gateway routing.</p>',
              {
                status: 503,
                headers: { 'Content-Type': 'text/html' }
              }
            );
          }
        });
    })
  );
});