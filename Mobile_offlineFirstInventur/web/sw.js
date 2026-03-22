const CACHE_NAME = 'inventory-pwa-v7';
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
});

self.addEventListener('fetch', (e) => {
    e.respondWith(
        caches.match(e.request).then((response) => response || fetch(e.request))
    );
});
