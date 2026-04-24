/**
 * Service Worker - PrintFlow PWA
 * Keep this static worker valid. Dynamic base-path handling lives in sw.php.
 */

const CACHE_VERSION = 'v11';
const SHELL_CACHE = 'printflow-shell-' + CACHE_VERSION;
const PAGE_CACHE = 'printflow-pages-' + CACHE_VERSION;
const IMG_CACHE = 'printflow-img-' + CACHE_VERSION;
const SCOPE_URL = new URL(self.registration.scope);
const BASE_PATH = SCOPE_URL.pathname.replace(/\/$/, '') || '';
const PUSH_SUBSCRIBE_API = BASE_PATH + '/public/api/push/subscribe.php';

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

self.addEventListener('message', (event) => {
    const data = event.data || {};
    if (data && data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
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

self.addEventListener('push', (event) => {
    const defaults = {
        title: 'PrintFlow',
        body: 'You have a new update',
        icon: BASE_PATH + '/public/assets/images/icon-192.png',
        badge: BASE_PATH + '/public/assets/images/icon-72.png',
        image: '',
        tag: 'pf-general',
        url: BASE_PATH + '/',
    };

    let payload = { ...defaults };
    if (event.data) {
        try {
            payload = { ...defaults, ...event.data.json() };
        } catch (e) {
            payload.body = event.data.text() || defaults.body;
        }
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

            await self.registration.showNotification(payload.title, {
                body: payload.body,
                icon: payload.icon,
                badge: payload.badge,
                image: payload.image || undefined,
                tag: payload.tag,
                renotify: false,
                data: { url: normalizeTargetUrl(payload.url) },
            });
        })()
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = normalizeTargetUrl(event.notification.data && event.notification.data.url ? event.notification.data.url : BASE_PATH + '/');

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
