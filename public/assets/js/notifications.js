/**
 * notifications.js — PrintFlow Notification Centre
 *
 * Responsibilities:
 *  1. Request notification permission (once per browser, after first login)
 *  2. Register / refresh a Web Push subscription and send it to the server
 *  3. Poll for in-tab notifications every 15 s when the tab is visible
 *  4. Show in-tab toast banners without duplicating push notifications
 *  5. Update unread badge counts in the sidebar / nav
 */

(function () {
    'use strict';

    /* ── Config ──────────────────────────────────────────────────────────── */
    const POLL_INTERVAL_MS       = 15_000;
    const POLL_INTERVAL_HIDDEN   = 60_000;   // Slower poll when tab is hidden
    const SW_PATH                = '/printflow/public/sw.js';
    const SW_SCOPE               = '/printflow/public/';
    const API_VAPID_PUB          = '/printflow/public/api/push/vapid_public_key.php';
    const API_SUBSCRIBE          = '/printflow/public/api/push/subscribe.php';
    const API_POLL               = '/printflow/public/api/push/poll.php';
    const SEEN_STORAGE_KEY       = 'pf_seen_notifications';
    const PERM_ASKED_KEY         = 'pf_notify_perm_asked';
    // Covers: admin sidebar badge, manager sidebar badge, customer nav badge, staff badge, generic
    const BADGE_SELECTOR         = '#sidebar-notif-badge, #nav-notif-badge, [data-notif-badge]';

    // Role config injected by PHP before this script loads (via PFConfig)
    const USER_TYPE = (window.PFConfig && window.PFConfig.userType) ? window.PFConfig.userType : 'Customer';

    let pollTimer   = null;
    let lastPollTs  = Math.floor(Date.now() / 1000) - 30;

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    function seenIds() {
        try { return new Set(JSON.parse(sessionStorage.getItem(SEEN_STORAGE_KEY) || '[]')); }
        catch { return new Set(); }
    }

    function markSeen(id) {
        const s = seenIds();
        s.add(String(id));
        // Keep at most 200 IDs to avoid unbounded growth
        const arr = [...s].slice(-200);
        sessionStorage.setItem(SEEN_STORAGE_KEY, JSON.stringify(arr));
    }

    function urlB64ToUint8Array(base64String) {
        const pad = '='.repeat((4 - base64String.length % 4) % 4);
        const b64 = (base64String + pad).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(b64);
        return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
    }

    function updateBadge(count) {
        document.querySelectorAll(BADGE_SELECTOR).forEach(el => {
            const inPersistentSidebar = el.id === 'sidebar-notif-badge' || (el.closest && el.closest('#printflow-persistent-sidebar'));
            if (count > 0) {
                el.textContent = count > 99 ? '99+' : count;
                if (inPersistentSidebar) {
                    el.style.display = 'inline-flex';
                    el.style.visibility = 'visible';
                } else {
                    el.style.visibility = '';
                    el.style.display = el.dataset.badgeDisplay || (el.id === 'nav-notif-badge' ? 'flex' : 'inline-flex');
                }
            } else {
                el.textContent = '';
                if (inPersistentSidebar) {
                    el.style.display = 'inline-flex';
                    el.style.visibility = 'hidden';
                } else {
                    el.style.visibility = '';
                    el.style.display = 'none';
                }
            }
        });
        // PWA badge API (Chrome/Android)
        if ('setAppBadge' in navigator) {
            count > 0 ? navigator.setAppBadge(count).catch(() => {}) : navigator.clearAppBadge().catch(() => {});
        }
    }

    function getNotifUrl(type, dataId) {
        const base  = '/printflow';
        const t     = (type || '').toLowerCase();
        const isStaff = ['admin', 'staff', 'manager'].includes(USER_TYPE.toLowerCase());

        if (isStaff) {
            if (t.includes('inventory'))               return base + '/admin/inv_items_management.php';
            if (t.includes('order') || t.includes('job') || t.includes('design') || t.includes('custom'))
                return base + '/admin/orders_management.php';
            if (t.includes('chat') || t.includes('message'))
                return dataId ? base + '/admin/orders_management.php?order_id=' + dataId : base + '/admin/orders_management.php';
            return base + '/admin/dashboard.php';
        }

        // Customer
        if (t.includes('order'))       return base + '/customer/orders.php';
        if (t.includes('job'))         return base + '/customer/new_job_order.php';
        if (t.includes('chat') || t.includes('message'))
            return dataId ? base + '/customer/chat.php?order_id=' + dataId : base + '/customer/messages.php';
        if ((t.includes('design') || t.includes('custom') || t.includes('order')) && dataId)
            return base + '/customer/chat.php?order_id=' + dataId;
        if (t.includes('order')) return base + '/customer/orders.php';
        return base + '/';
    }

    /* ── In-tab Toast ────────────────────────────────────────────────────── */

    function showToast(title, body, url) {
        // Reuse any container already in the DOM
        let container = document.getElementById('pf-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'pf-toast-container';
            Object.assign(container.style, {
                position:   'fixed',
                bottom:     '24px',
                right:      '24px',
                zIndex:     '99999',
                display:    'flex',
                flexDirection: 'column',
                gap:        '10px',
                maxWidth:   '340px',
            });
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        Object.assign(toast.style, {
            background:   '#ffffff',
            border:       '1px solid #e5e7eb',
            borderLeft:   '4px solid #f97316',   // Primary colour
            borderRadius: '8px',
            boxShadow:    '0 4px 16px rgba(0,0,0,.12)',
            padding:      '12px 16px',
            cursor:       url ? 'pointer' : 'default',
            animation:    'pfToastIn .25s ease',
            display:      'flex',
            alignItems:   'flex-start',
            gap:          '10px',
        });

        const icon = document.createElement('img');
        icon.src = '/printflow/public/assets/images/icon-72.png';
        Object.assign(icon.style, { width: '32px', height: '32px', borderRadius: '6px', flexShrink: '0' });

        const text = document.createElement('div');
        text.innerHTML = `<div style="font-weight:600;font-size:.875rem;color:#111827;margin-bottom:2px">${escHtml(title)}</div>`
                       + `<div style="font-size:.8125rem;color:#6b7280;line-height:1.4">${escHtml(body)}</div>`;

        const close = document.createElement('button');
        Object.assign(close.style, {
            marginLeft: 'auto', background: 'none', border: 'none',
            cursor: 'pointer', color: '#9ca3af', fontSize: '1rem', padding: '0 0 0 8px', flexShrink: '0',
        });
        close.innerHTML = '&times;';
        close.addEventListener('click', e => { e.stopPropagation(); dismissToast(toast); });

        toast.append(icon, text, close);
        container.appendChild(toast);

        if (url) {
            toast.addEventListener('click', () => { window.location.href = url; });
        }

        setTimeout(() => dismissToast(toast), 6000);
    }

    function dismissToast(toast) {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity .2s';
        setTimeout(() => toast.remove(), 200);
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Inject keyframe if not already present
    if (!document.getElementById('pf-toast-style')) {
        const s = document.createElement('style');
        s.id = 'pf-toast-style';
        s.textContent = '@keyframes pfToastIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}';
        document.head.appendChild(s);
    }

    /* ── Polling ─────────────────────────────────────────────────────────── */

    async function poll() {
        try {
            const res  = await fetch(API_POLL + '?since=' + lastPollTs, { credentials: 'include' });
            if (!res.ok) return;
            const data = await res.json();

            if (!data.success) return;

            updateBadge(data.unread_count || 0);

            // Update server_time for next poll window
            if (data.server_time) lastPollTs = data.server_time;

            const seen = seenIds();
            (data.notifications || []).forEach(n => {
                const sid = String(n.id);
                if (seen.has(sid)) return;
                markSeen(sid);

                // Skip in-tab toast if user is already on the relevant page
                const targetUrl = getNotifUrl(n.type, n.data_id);
                if (window.location.pathname + window.location.search === targetUrl) return;

                showToast('PrintFlow', n.message, targetUrl);
            });
        } catch (_) { /* Network hiccup — silently ignore */ }
    }

    function schedulePoll() {
        clearTimeout(pollTimer);
        const delay = document.hidden ? POLL_INTERVAL_HIDDEN : POLL_INTERVAL_MS;
        pollTimer = setTimeout(async () => { await poll(); schedulePoll(); }, delay);
    }

    document.addEventListener('visibilitychange', () => {
        clearTimeout(pollTimer);
        if (!document.hidden) { poll(); }
        schedulePoll();
    });

    /* ── Push Subscription ───────────────────────────────────────────────── */

    async function subscribePush(reg) {
        try {
            const keyRes  = await fetch(API_VAPID_PUB, { credentials: 'include' });
            const keyData = await keyRes.json();
            if (!keyData.public_key) return;  // VAPID not yet configured

            const existing = await reg.pushManager.getSubscription();

            // Check if we already sent this exact endpoint to the server
            const storedEp = localStorage.getItem('pf_push_ep');
            if (existing && storedEp === existing.endpoint) return; // Nothing to do

            const sub = existing || await reg.pushManager.subscribe({
                userVisibleOnly:      true,
                applicationServerKey: urlB64ToUint8Array(keyData.public_key),
            });

            const subJson = sub.toJSON();
            await fetch(API_SUBSCRIBE, {
                method:      'POST',
                credentials: 'include',
                headers:     { 'Content-Type': 'application/json' },
                body:        JSON.stringify({
                    action:   'subscribe',
                    endpoint: subJson.endpoint,
                    keys:     subJson.keys,
                }),
            });

            localStorage.setItem('pf_push_ep', subJson.endpoint);
        } catch (err) {
            // PushManager not available in all browsers (e.g. Firefox private mode)
            console.debug('[PF Notifications] Push subscription skipped:', err.message);
        }
    }

    /* ── Permission Request ──────────────────────────────────────────────── */

    async function requestPermission() {
        if (Notification.permission === 'granted') return true;
        if (Notification.permission === 'denied')  return false;
        if (sessionStorage.getItem(PERM_ASKED_KEY)) return false;

        sessionStorage.setItem(PERM_ASKED_KEY, '1');
        const result = await Notification.requestPermission();
        return result === 'granted';
    }

    /* ── Bootstrap ───────────────────────────────────────────────────────── */

    async function init() {
        // Start polling immediately (works without push / permission)
        poll();
        schedulePoll();

        if (!('Notification' in window) || !('serviceWorker' in navigator)) return;

        const granted = await requestPermission();
        if (!granted) return;

        // Register (or retrieve existing) service worker
        let reg;
        try {
            reg = await navigator.serviceWorker.register(SW_PATH, { scope: SW_SCOPE });
            await navigator.serviceWorker.ready;
        } catch (err) {
            console.debug('[PF Notifications] SW register failed:', err.message);
            return;
        }

        await subscribePush(reg);

        // When a push notification is clicked and the SW posts a message, navigate
        navigator.serviceWorker.addEventListener('message', e => {
            if (e.data && e.data.type === 'PF_NAVIGATE') {
                window.location.href = e.data.url;
            }
        });
    }

    // Wait for the DOM + any existing SW registrations to settle
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose for external callers (e.g. mark-read after modal open)
    window.PFNotifications = {
        markSeen,
        updateBadge,
        poll,
    };
})();
