const CACHE_NAME = 'transit-dashboard-v1';
const ASSETS_TO_CACHE = [
    '/php-control-plane/dashboard.php',
    // Add paths to any local external CSS or image files here if you use them later
];

// 1. Install Event: Cache the dashboard structural skeleton
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('Service Worker: Caching Core UI Shell Assets');
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
});

// 2. Activate Event: Clean up legacy caches
self.addEventListener('activate', (event) => {
    console.log('Service Worker: System Interface Fully Active');
});

// 3. Fetch Event: Intercept network failures and serve the cache fallback
self.addEventListener('fetch', (event) => {
    event.respondWith(
        fetch(event.request).catch(() => {
            // If network fails (e.g., Apache server goes down), match the cache
            return caches.match(event.request);
        })
    );
});