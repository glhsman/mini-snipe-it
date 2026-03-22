const CACHE_NAME = 'inventory-pwa-v8';
const ASSETS = [
    './',
    './index.html',
    './css/style.css',
    './css/scanner.css',
    './js/app.js',
    './js/db.js',
    './js/models.js',
    './manifest.json'
];

self.addEventListener('install', (e) => {
    e.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (e) => {
    if (e.request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(e.request.url);

    // Never cache API traffic; always hit server for latest auth/session/data state.
    if (requestUrl.pathname.includes('/public/api/')) {
        e.respondWith(fetch(e.request));
        return;
    }

    const isStaticRuntimeCritical =
        e.request.destination === 'document' ||
        e.request.destination === 'script' ||
        e.request.destination === 'style';

    if (isStaticRuntimeCritical) {
        // Network-first prevents stale app.js/index.html after deployments.
        e.respondWith((async () => {
            try {
                const fresh = await fetch(e.request);
                const cache = await caches.open(CACHE_NAME);
                cache.put(e.request, fresh.clone());
                return fresh;
            } catch (err) {
                const cached = await caches.match(e.request);
                if (cached) {
                    return cached;
                }
                throw err;
            }
        })());
        return;
    }

    e.respondWith(
        caches.match(e.request).then((response) => response || fetch(e.request))
    );
});
