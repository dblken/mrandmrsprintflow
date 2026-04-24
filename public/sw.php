<?php
// Load config for environment detection
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/../includes/shop_config.php';

$base_path = defined('BASE_PATH') ? BASE_PATH : '';
$logo_version = rawurlencode(printflow_logo_version());
$app_icon = !empty($shop_logo_url)
    ? ($shop_logo_url . '?v=' . $logo_version)
    : ($base_path . '/public/app-icon.php?v=' . $logo_version);
$app_badge = $base_path . '/public/assets/images/icon-72.png';

header('Content-Type: application/javascript');
header('Service-Worker-Allowed: /');
?>
/**
 * Service Worker - PrintFlow PWA
 * Strategy: App Shell (instant open) + Stale-While-Revalidate for pages
 */

const BASE_PATH = '<?php echo $base_path; ?>';
const CACHE_VERSION = 'v14';
const SHELL_CACHE = 'printflow-shell-' + CACHE_VERSION;
const PAGE_CACHE = 'printflow-pages-' + CACHE_VERSION;
const IMG_CACHE = 'printflow-img-' + CACHE_VERSION;
const PUSH_SUBSCRIBE_API = BASE_PATH + '/public/api/push/subscribe.php';

// App shell — cached immediately on install so the app opens instantly
const APP_SHELL = [
    BASE_PATH + '/public/offline.html',
    BASE_PATH + '/public/assets/css/output.css',
    BASE_PATH + '/public/assets/js/pwa.js',
    '<?php echo addslashes($app_icon); ?>',
    BASE_PATH + '/public/manifest.php',
];

// Pages to pre-cache
const PRE_CACHE_PAGES = [];

function normalizeTargetUrl(target) {
    const fallback = BASE_PATH + '/';
    const rawTarget = target || fallback;

    try {
        const url = new URL(rawTarget, self.location.origin);
        const host = String(self.location.hostname || '').toLowerCase();
        if (!BASE_PATH && host.includes('mrandmrsprintflow.com') && url.pathname.startsWith('/printflow/')) {
            url.pathname = url.pathname.replace(/^\/printflow(?=\/)/, '');
        }
        return url.pathname + url.search + url.hash;
    } catch (e) {
        if (!BASE_PATH && String(self.location.hostname || '').toLowerCase().includes('mrandmrsprintflow.com')) {
            return String(rawTarget).replace(/^\/printflow(?=\/)/, '');
        }
        return rawTarget;
    }
}

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

self.addEventListener('message', (event) => {
    const data = event.data || {};
    if (data && data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
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
        event.respondWith(networkOnlyDocument(request));
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

async function networkOnlyDocument(request) {
    try {
        return await fetch(request, { cache: 'no-store' });
    } catch {
        const offline = await caches.match(BASE_PATH + '/public/offline.html');
        return offline || new Response('<h1>Offline</h1>', {
            headers: { 'Content-Type': 'text/html' },
            status: 503
        });
    }
}

function isStaticAsset(pathname) {
    return /\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|woff|woff2|ttf|eot)$/i.test(pathname);
}

self.addEventListener('push', (event) => {
    const defaults = {
        title: 'PrintFlow',
        body:  'You have a new update',
        icon:  '<?php echo addslashes($app_icon); ?>',
        badge: '<?php echo addslashes($app_badge); ?>',
        image: '',
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
            const targetUrl = new URL(normalizeTargetUrl(payload.url), self.location.origin).href;
            let matchingVisibleClient = null;

            for (const client of windowClients) {
                const clientUrl = new URL(client.url, self.location.origin).href;
                if (clientUrl === targetUrl && client.visibilityState === 'visible') {
                    matchingVisibleClient = client;
                    break;
                }
            }

            if (matchingVisibleClient) {
                matchingVisibleClient.postMessage({ type: 'PF_PUSH_RECEIVED', payload });
            }

            const primaryOptions = {
                body:    payload.body,
                icon:    payload.icon,
                badge:   payload.badge,
                image:   payload.image || undefined,
                tag:     payload.tag,
                renotify: false,
                data:    { url: normalizeTargetUrl(payload.url) },
            };

            try {
                await self.registration.showNotification(payload.title, primaryOptions);
            } catch (error) {
                const fallbackOptions = {
                    ...primaryOptions,
                    icon: defaults.icon,
                    badge: defaults.badge,
                    image: undefined,
                };
                await self.registration.showNotification(payload.title, fallbackOptions);
            }
        })()
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = normalizeTargetUrl(event.notification.data?.url || BASE_PATH + '/');

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

self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(
        (async () => {
            try {
                const applicationServerKey = event.oldSubscription && event.oldSubscription.options
                    ? event.oldSubscription.options.applicationServerKey
                    : null;

                if (!applicationServerKey) {
                    return;
                }

                const subscription = await self.registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey,
                });

                await fetch(PUSH_SUBSCRIBE_API, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        endpoint: subscription.endpoint,
                        expirationTime: subscription.expirationTime,
                        keys: subscription.toJSON().keys || {},
                        action: 'subscribe'
                    })
                });
            } catch (error) {
                console.error('[SW] Failed to refresh push subscription:', error);
            }
        })()
    );
});
