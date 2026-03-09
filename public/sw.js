/**
 * Service Worker - PrintFlow PWA
 * Strategy: App Shell (instant open) + Stale-While-Revalidate for pages
 */

const CACHE_VERSION = 'v4';
const SHELL_CACHE = 'printflow-shell-' + CACHE_VERSION;
const PAGE_CACHE = 'printflow-pages-' + CACHE_VERSION;
const IMG_CACHE = 'printflow-img-' + CACHE_VERSION;

// App shell — cached immediately on install so the app opens instantly
const APP_SHELL = [
    '/printflow/public/offline.html',
    '/printflow/public/assets/css/output.css',
    '/printflow/public/assets/js/pwa.js',
    '/printflow/public/assets/images/icon-192.png',
    '/printflow/public/assets/images/icon-512.png',
    '/printflow/public/manifest.json',
];

// Pages to pre-cache so they open instantly (served from cache, updated in bg)
const PRE_CACHE_PAGES = [
    '/printflow/',
    '/printflow/public/index.php',
    '/printflow/public/login.php',
    '/printflow/public/products.php',
];

// ── Install: cache shell + pages immediately ─────────────────────────────────
self.addEventListener('install', (event) => {
    console.log('[SW] Installing...');
    event.waitUntil(
        Promise.all([
            // Cache app shell (must succeed)
            caches.open(SHELL_CACHE).then((cache) => {
                console.log('[SW] Caching app shell');
                return cache.addAll(APP_SHELL);
            }),
            // Cache pages (best-effort — don't fail install if a page 404s)
            caches.open(PAGE_CACHE).then((cache) => {
                return Promise.allSettled(
                    PRE_CACHE_PAGES.map((url) => cache.add(url).catch(() => { }))
                );
            }),
        ])
    );
    // Take control immediately — no need to wait for old SW to expire
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
    return self.clients.claim(); // Take control of all open tabs immediately
});

// ── Fetch: routing strategies ─────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // 1. Skip non-GET and cross-origin requests
    if (request.method !== 'GET') return;
    if (!url.origin.includes(self.location.hostname) &&
        !url.hostname.includes('localhost')) return;

    // 2. API calls → Network-only (always fresh data)
    if (url.pathname.includes('/api/') || url.pathname.includes('ajax')) {
        event.respondWith(fetch(request).catch(() => caches.match(request)));
        return;
    }

    // 3. Static assets (CSS, JS, images, fonts) → Cache-first (instant)
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(request, SHELL_CACHE));
        return;
    }

    // 4. HTML pages → Stale-While-Revalidate (except verify_email.php which is highly dynamic)
    if (request.destination === 'document' || url.pathname.endsWith('.php') || url.pathname.endsWith('/')) {
        if (url.pathname.includes('verify_email.php')) {
            event.respondWith(fetch(request)); // Always network
            return;
        }
        event.respondWith(staleWhileRevalidate(request, PAGE_CACHE));
        return;
    }

    // 5. Everything else → Network with cache fallback
    event.respondWith(networkWithCacheFallback(request, SHELL_CACHE));
});

// ── Strategies ────────────────────────────────────────────────────────────────

/** Cache-first: serve from cache, fetch and update cache if missing */
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

/**
 * Stale-While-Revalidate: serve cached version instantly,
 * fetch fresh copy in the background and update cache.
 * Falls back to offline.html if both fail.
 */
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    // Kick off a background fetch to keep cache fresh
    const fetchPromise = fetch(request).then((response) => {
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    }).catch(() => null);

    // Return cached version immediately (instant open!) or wait for network
    if (cached) {
        // Background update already kicked off — return cache right away
        fetchPromise; // eslint-disable-line no-unused-expressions
        return cached;
    }

    // No cache yet — wait for network
    const networkResponse = await fetchPromise;
    if (networkResponse) return networkResponse;

    // Both failed — show offline page
    const offline = await caches.match('/printflow/public/offline.html');
    return offline || new Response('<h1>Offline</h1>', {
        headers: { 'Content-Type': 'text/html' }
    });
}

/** Network with cache fallback */
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

/** Returns true for CSS, JS, images, fonts */
function isStaticAsset(pathname) {
    return /\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|woff|woff2|ttf|eot)$/i.test(pathname);
}

// ── Push Notifications ────────────────────────────────────────────────────────
self.addEventListener('push', (event) => {
    let data = {
        title: 'PrintFlow',
        body: 'You have a new update',
        icon: '/printflow/public/assets/images/icon-192.png',
        badge: '/printflow/public/assets/images/icon-72.png',
    };
    if (event.data) {
        try { data = { ...data, ...event.data.json() }; }
        catch { data.body = event.data.text(); }
    }
    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body, icon: data.icon, badge: data.badge,
            data: { url: data.url || '/printflow/' }
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = event.notification.data?.url || '/printflow/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const client of list) {
                if (client.url === target && 'focus' in client) return client.focus();
            }
            if (clients.openWindow) return clients.openWindow(target);
        })
    );
});
