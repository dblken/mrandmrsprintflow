/**
 * Service Worker - PrintFlow PWA
 * Keep this static worker valid. Dynamic base-path handling lives in sw.php.
 */

const CACHE_VERSION = 'v11';
const SHELL_CACHE = 'printflow-shell-' + CACHE_VERSION;
const PAGE_CACHE = 'printflow-pages-' + CACHE_VERSION;
const IMG_CACHE = 'printflow-img-' + CACHE_VERSION;
const BASE_PATH = '';

const APP_SHELL = [
    BASE_PATH + '/public/offline.html',
    BASE_PATH + '/public/assets/css/output.css',
    BASE_PATH + '/public/assets/js/pwa.js',
    BASE_PATH + '/public/assets/images/icon-192.png',
    BASE_PATH + '/public/assets/images/icon-512.png',
    BASE_PATH + '/public/manifest.php',
];

const PRE_CACHE_PAGES = [
    BASE_PATH + '/',
    BASE_PATH + '/public/index.php',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        Promise.all([
            caches.open(SHELL_CACHE).then((cache) => cache.addAll(APP_SHELL).catch(() => {})),
            caches.open(PAGE_CACHE).then((cache) =>
                Promise.allSettled(PRE_CACHE_PAGES.map((url) => cache.add(url).catch(() => {})))
            ),
        ])
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    const keep = [SHELL_CACHE, PAGE_CACHE, IMG_CACHE];
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.map((key) => keep.includes(key) ? null : caches.delete(key)))
        )
    );
    return self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET') return;
    if (url.origin !== self.location.origin) return;
    if (url.pathname.includes('/api/') || url.pathname.includes('ajax')) return;

    if (request.destination === 'image') {
        event.respondWith(
            caches.open(IMG_CACHE).then((cache) =>
                cache.match(request).then((cached) =>
                    cached || fetch(request).then((response) => {
                        if (response.ok) cache.put(request, response.clone());
                        return response;
                    }).catch(() => caches.match(BASE_PATH + '/public/assets/images/icon-192.png'))
                )
            )
        );
        return;
    }

    if (request.mode === 'navigate' || request.destination === 'document') {
        event.respondWith(
            fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(PAGE_CACHE).then((cache) => cache.put(request, copy));
                return response;
            }).catch(() =>
                caches.match(request).then((cached) => cached || caches.match(BASE_PATH + '/public/offline.html'))
            )
        );
        return;
    }

    event.respondWith(
        caches.match(request).then((cached) =>
            cached || fetch(request).then((response) => {
                if (response.ok && (request.destination === 'style' || request.destination === 'script')) {
                    caches.open(SHELL_CACHE).then((cache) => cache.put(request, response.clone()));
                }
                return response;
            })
        )
    );
});
