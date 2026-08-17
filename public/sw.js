const CACHE_NAME = 'hasira-queue-v1';
const ASSETS = [
    '/',
    '/login',
    '/gatekeeper',
    '/build/assets/app.css', // Ensure these exist or use offline fallback
    '/build/assets/app.js'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS);
        })
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method === 'GET') {
        event.respondWith(
            fetch(event.request).catch(() => {
                return caches.match(event.request);
            })
        );
    }
});
