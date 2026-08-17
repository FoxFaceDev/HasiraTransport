const CACHE_VERSION = 'hasira-gatekeeper-v4';
const PAGE_CACHE = `${CACHE_VERSION}-pages`;
const ASSET_CACHE = `${CACHE_VERSION}-assets`;
const OFFLINE_FALLBACK = '/offline.html';

self.addEventListener('install', event => {
    event.waitUntil(caches.open(ASSET_CACHE).then(cache => cache.add(OFFLINE_FALLBACK)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(
        keys.filter(key => key.startsWith('hasira-') && ![PAGE_CACHE, ASSET_CACHE].includes(key)).map(key => caches.delete(key))
    )).then(() => self.clients.claim()));
});

self.addEventListener('message', event => {
    if (event.data?.type === 'CLEAR_PRIVATE_CACHE') event.waitUntil(caches.delete(PAGE_CACHE));
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate' && url.pathname.startsWith('/gatekeeper')) {
        event.respondWith(networkFirstGatekeeperPage(request));
        return;
    }

    if (request.mode === 'navigate' && ['/', '/login'].includes(url.pathname)) {
        event.respondWith(networkFirstLoginOrGatekeeper(request));
        return;
    }

    if (['style', 'script', 'font', 'image'].includes(request.destination)) {
        event.respondWith(cacheFirstAsset(request));
    }
});

async function networkFirstGatekeeperPage(request) {
    const cache = await caches.open(PAGE_CACHE);
    try {
        const response = await fetch(request);
        const responsePath = new URL(response.url).pathname;
        if (response.ok && responsePath.startsWith('/gatekeeper')) await cache.put(request, response.clone());
        return response;
    } catch (error) {
        return await cache.match(request) || await cache.match('/gatekeeper') || await caches.match(OFFLINE_FALLBACK);
    }
}

async function networkFirstLoginOrGatekeeper(request) {
    try {
        return await fetch(request);
    } catch (error) {
        const pageCache = await caches.open(PAGE_CACHE);
        return await pageCache.match('/gatekeeper') || await caches.match(OFFLINE_FALLBACK);
    }
}

async function cacheFirstAsset(request) {
    const cached = await caches.match(request);
    if (cached) return cached;
    try {
        const response = await fetch(request);
        if (response.ok) await (await caches.open(ASSET_CACHE)).put(request, response.clone());
        return response;
    } catch (error) {
        return new Response('', { status: 504, statusText: 'Offline' });
    }
}
