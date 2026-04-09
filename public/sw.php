<?php
// Load config for environment detection
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

$base_path = defined('BASE_PATH') ? BASE_PATH : '';

header('Content-Type: application/javascript');
header('Service-Worker-Allowed: /');
?>
/**
 * Service Worker - PrintFlow PWA
 * Strategy: App Shell (instant open) + Stale-While-Revalidate for pages
 */

const BASE_PATH = '<?php echo $base_path; ?>';
const CACHE_VERSION = 'v8';
const SHELL_CACHE = 'printflow-shell-' + CACHE_VERSION;
const PAGE_CACHE = 'printflow-pages-' + CACHE_VERSION;
const IMG_CACHE = 'printflow-img-' + CACHE_VERSION;

// App shell — cached immediately on install so the app opens instantly
const APP_SHELL = [
    BASE_PATH + '/public/offline.html',
    BASE_PATH + '/public/assets/css/output.css',
    BASE_PATH + '/public/assets/js/pwa.js',
    BASE_PATH + '/public/assets/images/icon-192.png',
    BASE_PATH + '/public/assets/images/icon-512.png',
    BASE_PATH + '/manifest.php',
];

// Pages to pre-cache
const PRE_CACHE_PAGES = [
    BASE_PATH + '/',
    BASE_PATH + '/public/index.php',
];

// ── Install: cache shell + pages immediately ─────────────────────────────────
self.addEventListener('install', (event) => {
    console.log('[SW] Installing...');
    event.waitUntil(
        Promise.all([
            caches.open(SHELL_CACHE).then((cache) => {
                console.log('[SW] Caching app shell');
                return cache.addAll(APP_SHELL).catch(err => console.log('[SW] Cache failed:', err));
            }),
            caches.open(PAGE_CACHE).then((cache) => {
                return Promise.allSettled(
                    PRE_CACHE_PAGES.map((url) => cache.add(url).catch(() => { }))
                );
            }),
        ])
    );
    self.skipWaiting();
});

// ── Activate: delete old caches ───────────────────────────────────────────────
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating...');
    const KEEP = [SHELL_CACHE, PAGE_CACHE, IMG_CACHE];
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.map((key) => {
                    if (!KEEP.includes(key)) {
                        console.log('[SW] Removing old cache:', key);
                        return caches.delete(key);
                    }
                })
            )
        )
    );
    return self.clients.claim();
});

// ── Fetch: routing strategies ─────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET') return;
    if (!url.origin.includes(self.location.hostname) &&
        !url.hostname.includes('localhost')) return;

    if (url.pathname.includes('/api/') || url.pathname.includes('ajax')) {
        event.respondWith(fetch(request).catch(() => caches.match(request)));
        return;
    }

    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(request, SHELL_CACHE));
        return;
    }

    if (request.destination === 'document' || url.pathname.endsWith('.php') || url.pathname.endsWith('/')) {
        if (url.pathname.includes('verify_email.php')) {
            event.respondWith(fetch(request));
            return;
        }
        event.respondWith(staleWhileRevalidate(request, PAGE_CACHE));
        return;
    }

    event.respondWith(networkWithCacheFallback(request, SHELL_CACHE));
});

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return new Response('Asset unavailable offline', { status: 503 });
    }
}

async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    const fetchPromise = fetch(request).then((response) => {
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    }).catch(() => null);

    if (cached) {
        fetchPromise;
        return cached;
    }

    const networkResponse = await fetchPromise;
    if (networkResponse) return networkResponse;

    const offline = await caches.match(BASE_PATH + '/public/offline.html');
    return offline || new Response('<h1>Offline</h1>', {
        headers: { 'Content-Type': 'text/html' }
    });
}

async function networkWithCacheFallback(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        return cached || new Response('Unavailable offline', { status: 503 });
    }
}

function isStaticAsset(pathname) {
    return /\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|woff|woff2|ttf|eot)$/i.test(pathname);
}

self.addEventListener('push', (event) => {
    const defaults = {
        title: 'PrintFlow',
        body:  'You have a new update',
        icon:  BASE_PATH + '/public/assets/images/icon-192.png',
        badge: BASE_PATH + '/public/assets/images/icon-72.png',
        tag:   'pf-general',
        url:   BASE_PATH + '/',
    };

    let payload = { ...defaults };
    if (event.data) {
        try { payload = { ...defaults, ...event.data.json() }; }
        catch { payload.body = event.data.text() || defaults.body; }
    }

    event.waitUntil(
        (async () => {
            const windowClients = await clients.matchAll({ type: 'window', includeUncontrolled: true });
            const targetUrl = new URL(payload.url, self.location.origin).href;

            for (const client of windowClients) {
                const clientUrl = new URL(client.url, self.location.origin).href;
                if (clientUrl === targetUrl && client.visibilityState === 'visible') {
                    client.postMessage({ type: 'PF_PUSH_RECEIVED', payload });
                    return;
                }
            }

            await self.registration.showNotification(payload.title, {
                body:    payload.body,
                icon:    payload.icon,
                badge:   payload.badge,
                tag:     payload.tag,
                renotify: false,
                data:    { url: payload.url },
            });
        })()
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = event.notification.data?.url || BASE_PATH + '/';

    event.waitUntil(
        (async () => {
            const windowClients = await clients.matchAll({ type: 'window', includeUncontrolled: true });

            let bestClient = null;
            for (const client of windowClients) {
                if (new URL(client.url).pathname === new URL(target, self.location.origin).pathname) {
                    bestClient = client;
                    break;
                }
                if (!bestClient) bestClient = client;
            }

            if (bestClient) {
                await bestClient.focus();
                if (new URL(bestClient.url).pathname !== new URL(target, self.location.origin).pathname) {
                    bestClient.postMessage({ type: 'PF_NAVIGATE', url: target });
                }
                return;
            }

            if (clients.openWindow) await clients.openWindow(target);
        })()
    );
});
