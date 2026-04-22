(function () {
    'use strict';

    /* ── Config ──────────────────────────────────────────────────────────── */
    var POLL_INTERVAL_MS       = 15000;
    var POLL_INTERVAL_HIDDEN   = 60000;
    var SEEN_STORAGE_KEY       = 'pf_seen_notifications';
    var PERM_ASKED_KEY         = 'pf_notify_perm_asked';
    var BADGE_SELECTOR         = '#sidebar-notif-badge, #nav-notif-badge, [data-notif-badge]';

    var USER_TYPE = (window.PFConfig && window.PFConfig.userType) ? window.PFConfig.userType : 'Customer';

    var pollTimer   = null;
    var lastPollTs  = Math.floor(Date.now() / 1000) - 30;

    /* ── Export Early ────────────────────────────────────────────────────── */
    // Using simple var to ensure global access without modern scoping issues
    window.PFNotifications = {
        markSeen: markSeen,
        updateBadge: updateBadge,
        poll: poll,
        loadDropdown: loadDropdown,
        subscribeToPush: subscribeToPush,
        unsubscribeFromPush: unsubscribeFromPush,
        handlePushToggleClick: handlePushToggleClick
    };

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    function normalizeBasePath(rawBase) {
        var base = String(rawBase || '').trim();
        if (!base || base === '/') return '';
        if (base.charAt(0) !== '/') base = '/' + base;
        base = base.replace(/\/+$/, '');

        var path = window.location.pathname || '';
        if (base && path !== base && path.indexOf(base + '/') !== 0) {
            return '';
        }
        return base;
    }

    function getBasePath() {
        if (window.PFConfig && Object.prototype.hasOwnProperty.call(window.PFConfig, 'basePath')) {
            return normalizeBasePath(window.PFConfig.basePath);
        }
        return normalizeBasePath(window.BASE_PATH || '');
    }

    function buildAppUrl(path) {
        var cleanPath = String(path || '').replace(/^\/+/, '');
        var base = getBasePath();
        return cleanPath ? (base + '/' + cleanPath) : (base || '');
    }

    function normalizeNotificationTarget(url) {
        if (!url) return url;

        var base = getBasePath();
        var host = String(window.location.hostname || '').toLowerCase();

        try {
            var target = new URL(url, window.location.origin);
            if (!base && host.indexOf('mrandmrsprintflow.com') !== -1 && target.pathname.indexOf('/printflow/') === 0) {
                target.pathname = target.pathname.replace(/^\/printflow(?=\/)/, '');
            }
            return target.pathname + target.search + target.hash;
        } catch (e) {
            if (!base && host.indexOf('mrandmrsprintflow.com') !== -1) {
                return String(url).replace(/^\/printflow(?=\/)/, '');
            }
            return url;
        }
    }

    function appendMarkRead(url, notifId) {
        if (!url || !notifId) return url;

        var role = String(USER_TYPE || '').toLowerCase();
        var notifPath = 'customer/notifications.php';
        if (role === 'admin') notifPath = 'admin/notifications.php';
        else if (role === 'manager') notifPath = 'manager/notifications.php';
        else if (role === 'staff') notifPath = 'staff/notifications.php';

        var markBase = buildAppUrl(notifPath);
        var safeTarget = normalizeNotificationTarget(url);

        try {
            var target = new URL(safeTarget, window.location.origin);
            var mark = new URL(markBase, window.location.origin);
            mark.searchParams.set('mark_read', notifId);
            mark.searchParams.set('next', target.pathname + target.search + target.hash);
            return mark.pathname + mark.search + mark.hash;
        } catch (e) {
            return markBase + '?mark_read=' + encodeURIComponent(notifId) + '&next=' + encodeURIComponent(safeTarget);
        }
    }

    var SW_PATH                = buildAppUrl('public/sw.js');
    var SW_SCOPE               = buildAppUrl('public') + '/';
    var SW_REGISTER_PATH       = buildAppUrl('public/sw.php');
    var API_VAPID_PUB          = buildAppUrl('public/api/push/vapid_public_key.php');
    var API_SUBSCRIBE          = buildAppUrl('public/api/push/subscribe.php');
    var API_POLL               = buildAppUrl('public/api/push/poll.php');
    var API_LIST               = buildAppUrl('public/api/notifications/list.php');

    function isPushSupported() {
        return 'serviceWorker' in navigator && 'PushManager' in window && typeof Notification !== 'undefined';
    }

    function ensureServiceWorker() {
        if (!('serviceWorker' in navigator)) return Promise.reject(new Error('serviceWorker unsupported'));

        return navigator.serviceWorker.getRegistration().then(function(reg) {
            if (reg) return reg;
            return navigator.serviceWorker.register(SW_REGISTER_PATH, { updateViaCache: 'none' });
        });
    }

    function getUserId() {
        return (window.PFConfig && window.PFConfig.userId) ? Number(window.PFConfig.userId) : 0;
    }

    function fetchVapidPublicKey() {
        return fetch(API_VAPID_PUB, { credentials: 'include' })
            .then(function(res) { return res.ok ? res.json() : null; })
            .then(function(data) { return data && data.public_key ? String(data.public_key) : ''; })
            .catch(function() { return ''; });
    }

    function sendSubscription(sub, action) {
        var payload = sub && typeof sub.toJSON === 'function' ? sub.toJSON() : sub;
        payload = payload || {};
        payload.action = action || 'subscribe';

        return fetch(API_SUBSCRIBE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        }).catch(function() {});
    }

    function unsubscribeFromPush() {
        if (!isPushSupported()) return Promise.resolve(false);

        return ensureServiceWorker()
            .then(function(reg) {
                return reg.pushManager.getSubscription().then(function(existing) {
                    if (!existing) return false;
                    return existing.unsubscribe().then(function() {
                        sendSubscription({ endpoint: existing.endpoint }, 'unsubscribe');
                        return true;
                    });
                });
            })
            .catch(function() { return false; });
    }

    function subscribeToPush(isUserAction) {
        if (!isPushSupported()) return Promise.resolve(null);

        var watchdog = null;
        if (isUserAction) {
            alert('Starting push setup...');
            watchdog = setTimeout(function() {
                alert('Push setup is taking too long. Please check your internet and try again.');
            }, 6000);
        }

        return navigator.serviceWorker.ready
            .then(function(reg) {
                return reg.pushManager.getSubscription().then(function(existing) {
                    if (existing) {
                        sendSubscription(existing, 'subscribe');
                        return existing;
                    }

                    if (isUserAction) {
                        alert('Fetching VAPID key...');
                    }
                    return fetchVapidPublicKey().then(function(pubKey) {
                        if (!pubKey) {
                            if (isUserAction) {
                                alert('Push is not configured yet. Missing VAPID public key.');
                            }
                            return null;
                        }

                        if (isUserAction) {
                            alert('Requesting permission...');
                        }
                        return Notification.requestPermission().then(function(permission) {
                            if (permission !== 'granted') {
                                if (isUserAction && permission === 'denied') {
                                    alert('Notifications are blocked in your browser settings.');
                                } else if (isUserAction) {
                                    alert('Notification permission was dismissed. Please allow it to enable alerts.');
                                }
                                return null;
                            }

                            return reg.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: urlB64ToUint8Array(pubKey)
                            }).then(function(sub) {
                                sendSubscription(sub, 'subscribe');
                                return sub;
                            });
                        });
                    });
                });
            })
            .catch(function() { return null; })
            .finally(function() {
                if (watchdog) clearTimeout(watchdog);
            });
    }

    function maybeInitPush() {
        if (!isPushSupported()) return;
        if (getUserId() <= 0) return;

        if (Notification.permission === 'granted') {
            subscribeToPush(false);
            return;
        }

        if (Notification.permission === 'default') {
            try {
                var asked = localStorage.getItem(PERM_ASKED_KEY);
                if (!asked) {
                    localStorage.setItem(PERM_ASKED_KEY, '1');
                    subscribeToPush(false);
                }
            } catch (e) {
                subscribeToPush(false);
            }
        }
    }

    function updatePushToggle(btn, state) {
        if (!btn) return;
        btn.dataset.state = state;

        if (state === 'unsupported') {
            btn.textContent = 'Notifications unsupported';
            btn.disabled = false;
            return;
        }
        if (state === 'blocked') {
            btn.textContent = 'Notifications blocked';
            btn.disabled = false;
            return;
        }
        if (state === 'enabled') {
            btn.textContent = 'Disable notifications';
            btn.disabled = false;
            return;
        }
        btn.textContent = 'Enable notifications';
        btn.disabled = false;
    }

    function initPushToggle() {
        var btn = document.getElementById('pf-push-toggle');
        if (!btn) return;

        if (!isPushSupported()) {
            updatePushToggle(btn, 'unsupported');
            return;
        }

        if (Notification.permission === 'denied') {
            updatePushToggle(btn, 'blocked');
            return;
        }

        ensureServiceWorker()
            .then(function(reg) { return reg.pushManager.getSubscription(); })
            .then(function(sub) {
                updatePushToggle(btn, sub ? 'enabled' : 'disabled');
            })
            .catch(function() {
                updatePushToggle(btn, 'disabled');
            });

        btn.addEventListener('click', function() {
            handlePushToggleClick(btn);
        });
    }

    function handlePushToggleClick(btn) {
        if (!btn) return;
        alert('Checking notification permission...');
        var state = btn.dataset.state || 'disabled';
        if (state === 'unsupported') {
            alert('This device/browser does not support push notifications. Please use a supported browser or install the PWA.');
            return;
        }
        if (state === 'blocked') {
            alert('Notifications are blocked in your browser settings. Please allow them to enable alerts.');
            return;
        }
        if (state === 'enabled') {
            if (!confirm('Disable notifications on this device?')) return;
            unsubscribeFromPush().then(function() {
                updatePushToggle(btn, 'disabled');
            });
            return;
        }
            subscribeToPush(true).then(function(sub) {
                var perm = typeof Notification !== 'undefined' ? Notification.permission : 'unsupported';
                alert('Permission status: ' + perm);
                updatePushToggle(btn, sub ? 'enabled' : 'disabled');
                if (!sub && perm === 'granted') {
                    alert('Notifications are allowed, but subscription failed. Please reload and try again.');
                }
        });
    }

    function bindPushMessages() {
        if (!('serviceWorker' in navigator)) return;
        navigator.serviceWorker.addEventListener('message', function(event) {
            var data = event.data || {};
            if (data.type === 'PF_PUSH_RECEIVED' && data.payload) {
                var payload = data.payload || {};
                var title = payload.title || 'PrintFlow';
                var body = payload.body || '';
                var url = payload.url ? normalizeNotificationTarget(payload.url) : '';
                showToast(title, body, url);
                return;
            }
            if (data.type === 'PF_NAVIGATE' && data.url) {
                window.location.href = normalizeNotificationTarget(data.url);
            }
        });
    }

    function seenIds() {
        try {
            var data = sessionStorage.getItem(SEEN_STORAGE_KEY);
            return new Set(JSON.parse(data || '[]'));
        } catch (e) {
            return new Set();
        }
    }

    function markSeen(id) {
        var s = seenIds();
        s.add(String(id));
        var arr = [];
        s.forEach(function(val) { arr.push(val); });
        arr = arr.slice(-200);
        sessionStorage.setItem(SEEN_STORAGE_KEY, JSON.stringify(arr));
    }

    function urlB64ToUint8Array(base64String) {
        var pad = '='.repeat((4 - base64String.length % 4) % 4);
        var b64 = (base64String + pad).replace(/-/g, '+').replace(/_/g, '/');
        var raw = atob(b64);
        var outputArray = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) {
            outputArray[i] = raw.charCodeAt(i);
        }
        return outputArray;
    }

    function updateBadge(count) {
        var els = document.querySelectorAll(BADGE_SELECTOR);
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            if (count > 0) {
                el.textContent = count > 99 ? '99+' : count;
                el.style.display = el.getAttribute('data-badge-display') || (el.id === 'nav-notif-badge' ? 'flex' : 'inline-flex');
                el.style.visibility = 'visible';
            } else {
                el.textContent = '';
                el.style.display = 'none';
                el.style.visibility = 'hidden';
            }
        }
    }

    function timeAgo(date) {
        if (!date) return 'just now';
        var d = new Date(date.replace(/-/g, '/'));
        var seconds = Math.floor((new Date() - d) / 1000);
        if (seconds < 60) return 'just now';
        var minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + 'm ago';
        var hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + 'h ago';
        var days = Math.floor(hours / 24);
        if (days < 7) return days + 'd ago';
        return d.toLocaleDateString();
    }

    function loadDropdown() {
        var lists = document.querySelectorAll('[data-pf-notif-list]');
        if (lists.length === 0) return;

        fetch(API_LIST + '?limit=8', { credentials: 'include' })
            .then(function(res) {
                if (!res.ok) throw new Error('Response ' + res.status);
                return res.json();
            })
            .then(function(data) {
                if (!data.success) {
                    for (var i = 0; i < lists.length; i++) lists[i].innerHTML = '<div class="pf-notif-empty">' + escHtml(data.error || 'Failed to load.') + '</div>';
                    return;
                }

                if (!data.notifications || data.notifications.length === 0) {
                    for (var i = 0; i < lists.length; i++) lists[i].innerHTML = '<div class="pf-notif-empty">No notifications yet.</div>';
                    updateBadge(0);
                    return;
                }

                updateBadge(data.unread_count || 0);

                var html = '';
                for (var j = 0; j < data.notifications.length; j++) {
                    var n = data.notifications[j];
                    var target = normalizeNotificationTarget(getNotifUrl(n));
                    var unreadClass = n.is_read == 0 ? 'unread' : '';
                    var type = (n.type || '').toLowerCase();
                    var iconSvg = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>';
                    
                    if (type.indexOf('order') !== -1 || type.indexOf('status') !== -1) {
                        iconSvg = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>';
                    } else if (type.indexOf('message') !== -1 || type.indexOf('chat') !== -1) {
                        iconSvg = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>';
                    } else if (type.indexOf('payment') !== -1) {
                        iconSvg = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
                    }

                    html += '<a href="' + target + '" class="pf-notif-item ' + unreadClass + '">' +
                            '  <div class="pf-notif-item-icon">' + iconSvg + '</div>' +
                            '  <div class="pf-notif-item-content">' +
                            '    <div class="pf-notif-item-text">' + escHtml(n.message) + '</div>' +
                            '    <div class="pf-notif-item-time">' + timeAgo(n.created_at) + '</div>' +
                            '  </div>' +
                            '</a>';
                }
                for (var k = 0; k < lists.length; k++) lists[k].innerHTML = html;
            })
            .catch(function(err) {
                for (var i = 0; i < lists.length; i++) lists[i].innerHTML = '<div class="pf-notif-empty">Error: ' + escHtml(err.message) + '</div>';
            });
    }

    function getNotifUrl(notification) {
        var type = notification && notification.type ? notification.type : '';
        var dataId = notification && notification.data_id != null ? notification.data_id : 0;
        var message = notification && notification.message ? notification.message : '';
        var notifId = notification && notification.id ? notification.id : 0;
        var orderType = notification && notification.order_type ? notification.order_type : '';
        var directTarget = notification && notification.target_url ? String(notification.target_url) : '';
        if (directTarget) {
            return appendMarkRead(normalizeNotificationTarget(directTarget), notifId);
        }

        var t = String(type || '').toLowerCase();
        var role = String(USER_TYPE || '').toLowerCase();
        var isAdmin = role === 'admin';
        var isManager = role === 'manager';
        var isStaff = role === 'staff';
        var msg = String(message || '').toLowerCase();
        var did = (dataId != null && dataId !== '') ? parseInt(dataId, 10) : 0;
        if (!did && msg) {
            var match = msg.match(/#(\d+)/);
            if (match && match[1]) did = parseInt(match[1], 10) || 0;
        }
        var url = buildAppUrl('');

        if (isAdmin || isManager) {
            var panel = isManager ? 'manager' : 'admin';
            if (t === 'system' && did > 0 && (msg.indexOf('ready for admin review') !== -1 || msg.indexOf('completed their profile') !== -1) && isAdmin) {
                url = buildAppUrl('admin/user_staff_management.php?open_user=' + did);
            } else if (t.indexOf('stock') !== -1 || t.indexOf('inventory') !== -1) {
                url = buildAppUrl(panel + '/inv_transactions_ledger.php' + (did > 0 ? '?item_id=' + did : ''));
            } else if (t.indexOf('job order') !== -1 || t.indexOf('payment issue') !== -1) {
                url = buildAppUrl(panel + '/job_orders.php' + (did > 0 ? '?open_job=' + did : ''));
            } else if (t.indexOf('order') !== -1 || t.indexOf('design') !== -1 || t.indexOf('message') !== -1 || t.indexOf('payment') !== -1) {
                url = buildAppUrl((isManager ? 'manager/orders.php' : 'admin/orders_management.php') + (did > 0 ? '?open_order=' + did : ''));
            } else {
                url = buildAppUrl(panel + '/notifications.php');
            }
        } else if (isStaff) {
            if (t === 'system') url = buildAppUrl('staff/notifications.php');
            else if (t.indexOf('rating') !== -1 || t.indexOf('review') !== -1) url = buildAppUrl('staff/reviews.php');
            else if (t.indexOf('stock') !== -1 || t.indexOf('inventory') !== -1) url = buildAppUrl('staff/notifications.php');
            else if (t.indexOf('order') !== -1 || t.indexOf('job') !== -1 || t.indexOf('design') !== -1 || t.indexOf('custom') !== -1 || t.indexOf('payment') !== -1) {
                var oType = String(orderType || '').toLowerCase();
                if (oType === 'custom' || t.indexOf('job') !== -1 || t.indexOf('custom') !== -1) {
                    url = buildAppUrl('staff/customizations.php?order_id=' + did + '&job_type=ORDER');
                } else {
                    url = buildAppUrl('staff/orders.php' + (did > 0 ? '?order_id=' + did : ''));
                }
            } else if (t.indexOf('chat') !== -1 || t.indexOf('message') !== -1) {
                url = buildAppUrl('staff/chats.php' + (did > 0 ? '?order_id=' + did : ''));
            } else {
                url = buildAppUrl('staff/dashboard.php');
            }
        } else {
            if (t.indexOf('order') !== -1 || t.indexOf('status') !== -1 || t.indexOf('payment') !== -1) {
                url = did > 0 ? buildAppUrl('customer/orders.php?highlight=' + did) : buildAppUrl('customer/orders.php');
            } else if (t.indexOf('job') !== -1) url = buildAppUrl('customer/new_job_order.php');
            else if (t.indexOf('chat') !== -1 || t.indexOf('message') !== -1) url = did ? buildAppUrl('customer/chat.php?order_id=' + did) : buildAppUrl('customer/messages.php');
            else if ((t.indexOf('design') !== -1 || t.indexOf('custom') !== -1) && did) url = buildAppUrl('customer/chat.php?order_id=' + did);
            else url = buildAppUrl('customer/notifications.php');
        }

        return appendMarkRead(url, notifId);
    }

    /* ── Polling ─────────────────────────────────────────────────────────── */

    function poll() {
        var url = API_POLL + '?since=' + lastPollTs;
        fetch(url, { credentials: 'include' })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data.success) return;
                updateBadge(data.unread_count || 0);
                if (data.server_time) lastPollTs = data.server_time;

                var seen = seenIds();
                var notifs = data.notifications || [];
                for (var i = 0; i < notifs.length; i++) {
                    var n = notifs[i];
                    var sid = String(n.id);
                    if (seen.has(sid)) continue;
                    markSeen(sid);
                    var targetUrl = normalizeNotificationTarget(getNotifUrl(n));
                    if (window.location.pathname + window.location.search === targetUrl) continue;
                    showToast('PrintFlow', n.message, targetUrl);
                }
            })
            .catch(function(){});
    }

    function schedulePoll() {
        clearTimeout(pollTimer);
        var delay = document.hidden ? POLL_INTERVAL_HIDDEN : POLL_INTERVAL_MS;
        pollTimer = setTimeout(function() { poll(); schedulePoll(); }, delay);
    }

    function showToast(title, body, url) {
        var container = document.getElementById('pf-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'pf-toast-container';
            container.style.position = 'fixed';
            container.style.bottom = '24px';
            container.style.right = '24px';
            container.style.zIndex = '99999';
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.gap = '10px';
            container.style.maxWidth = '340px';
            document.body.appendChild(container);
        }

        var toast = document.createElement('div');
        toast.style.background = '#ffffff';
        toast.style.border = '1px solid #e5e7eb';
        toast.style.borderLeft = '4px solid #f97316';
        toast.style.borderRadius = '8px';
        toast.style.boxShadow = '0 4px 16px rgba(0,0,0,.12)';
        toast.style.padding = '12px 16px';
        toast.style.cursor = url ? 'pointer' : 'default';
        toast.style.display = 'flex';
        toast.style.alignItems = 'flex-start';
        toast.style.gap = '10px';

        var icon = document.createElement('img');
        var logoUrl = (window.PFConfig && window.PFConfig.logoUrl) ? String(window.PFConfig.logoUrl) : '';
        icon.src = logoUrl || buildAppUrl('public/assets/images/icon-72.png');
        icon.style.width = '32px';
        icon.style.height = '32px';
        icon.style.borderRadius = '6px';
        icon.style.flexShrink = '0';
        icon.onerror = function() {
            this.onerror = null;
            this.src = buildAppUrl('public/assets/images/icon-192.png');
        };

        var text = document.createElement('div');
        text.innerHTML = '<div style="font-weight:600;font-size:.875rem;color:#111827;margin-bottom:2px">' + escHtml(title) + '</div>' +
                         '<div style="font-size:.8125rem;color:#6b7280;line-height:1.4">' + escHtml(body) + '</div>';

        var close = document.createElement('button');
        close.style.marginLeft = 'auto';
        close.style.background = 'none';
        close.style.border = 'none';
        close.style.cursor = 'pointer';
        close.style.color = '#9ca3af';
        close.style.fontSize = '1rem';
        close.style.padding = '0 0 0 8px';
        close.style.flexShrink = '0';
        close.innerHTML = '&times;';
        close.onclick = function(e) { e.stopPropagation(); toast.remove(); };

        toast.appendChild(icon);
        toast.appendChild(text);
        toast.appendChild(close);
        container.appendChild(toast);

        if (url) toast.onclick = function() { window.location.href = normalizeNotificationTarget(url); };
        setTimeout(function() { if (toast.parentNode) toast.remove(); }, 6000);
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function init() {
        bindPushMessages();
        maybeInitPush();
        initPushToggle();
        poll();
        schedulePoll();
    }

    function markSeen(id) {} // Placeholder to avoid errors if called before definition

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

})();
