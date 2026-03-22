<?php
/**
 * Hotwired Turbo Drive: same-origin navigations swap <body> without full reload.
 * #printflow-persistent-sidebar (data-turbo-permanent) stays in the DOM — sidebar does not remount.
 *
 * Disable on a page: $GLOBALS['PRINTFLOW_DISABLE_TURBO'] = true; before including admin_style.php
 */
?>
<script>
(function () {
    var orig = EventTarget.prototype.addEventListener;
    EventTarget.prototype.addEventListener = function (type, listener, options) {
        if (type === 'DOMContentLoaded' && document.readyState !== 'loading') {
            var cb = null;
            if (typeof listener === 'function') cb = listener;
            else if (listener && typeof listener.handleEvent === 'function') {
                cb = function () {
                    listener.handleEvent(new Event('DOMContentLoaded'));
                };
            }
            if (cb) {
                queueMicrotask(function () {
                    try {
                        cb.call(document, new Event('DOMContentLoaded'));
                    } catch (e) {
                        console.error(e);
                    }
                });
            }
            return;
        }
        return orig.call(this, type, listener, options);
    };
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@8.0.13/dist/turbo.es2017-umd.js"></script>
<script>
(function () {
    function normPath(href) {
        try {
            var u = new URL(href, window.location.href);
            var p = u.pathname.replace(/\/+$/, '') || '/';
            /* Same page may be /admin/foo or /admin/foo.php (rewrite) — match active nav either way */
            p = p.replace(/\.php$/i, '');
            return p;
        } catch (e) {
            return '';
        }
    }
    document.addEventListener('turbo:before-render', function (ev) {
        var nb = ev.detail && ev.detail.newBody;
        if (!nb) return;
        var incoming = nb.querySelector('#printflow-persistent-sidebar');
        if (!incoming) return;
        var newActive = incoming.querySelector('a.nav-item.active');
        var live = document.getElementById('printflow-persistent-sidebar');
        if (!live || !newActive) return;
        var want = normPath(newActive.href);
        live.querySelectorAll('a.nav-item').forEach(function (a) {
            a.classList.toggle('active', normPath(a.href) === want);
        });
    });

    /* Charts: tear down before swap/cache; init after paint (double rAF). Per-page inits are registered from body scripts so Turbo always gets the right handlers. */
    function printflowTeardownAllCharts() {
        try {
            if (typeof window.printflowTeardownReportsCharts === 'function') {
                window.printflowTeardownReportsCharts();
            }
        } catch (err) { console.error(err); }
        try {
            if (typeof window.printflowTeardownDashboardCharts === 'function') {
                window.printflowTeardownDashboardCharts();
            }
        } catch (err) { console.error(err); }
    }
    document.addEventListener('turbo:before-render', function () {
        printflowTeardownAllCharts();
    });
    document.addEventListener('turbo:before-cache', function () {
        /* Do NOT Alpine.destroyTree() here: Turbo snapshots the DOM after this event.
         * A destroyed Alpine subtree is what gets cached — returning via Turbo restores dead x-data / @click. */
        printflowTeardownAllCharts();
    });
    function printflowRunChartInitsForPage() {
        try {
            if (document.getElementById('reportsFilterForm') && typeof window.printflowInitReportsCharts === 'function') {
                window.printflowInitReportsCharts();
                return;
            }
            if (document.getElementById('salesChart') && typeof window.printflowInitDashboardCharts === 'function') {
                window.printflowInitDashboardCharts();
            }
        } catch (err) {
            console.error(err);
        }
    }

    /**
     * Alpine.start() runs once on first full page load; Turbo swaps do not re-run it.
     * If any [x-data] node still has no _x_dataStack (race with defer, or Turbo body swap),
     * bind the whole tree. If Alpine.start() already ran, every node has a stack — no-op.
     */
    function printflowEnsureAlpineBound() {
        try {
            if (typeof window.Alpine === 'undefined' || typeof Alpine.initTree !== 'function' || !document.body) {
                return;
            }
            var need = false;
            document.querySelectorAll('[x-data]').forEach(function (el) {
                if (!el._x_dataStack) {
                    need = true;
                }
            });
            if (need) {
                Alpine.initTree(document.body);
            }
        } catch (err) {
            console.error(err);
        }
    }
    document.addEventListener('turbo:before-visit', function () {
        document.documentElement.classList.add('pf-turbo-nav');
    });
    document.addEventListener('turbo:load', function () {
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                document.documentElement.classList.remove('pf-turbo-nav');
                /* Microtasks (Alpine.start) always run before rAF — safe to probe + initTree */
                printflowEnsureAlpineBound();
                /* One more microtask pass for edge cases where Alpine flushes after our rAF */
                queueMicrotask(printflowEnsureAlpineBound);
                    try {
                        if (typeof window.printflowInitInvLedger === 'function') {
                            window.printflowInitInvLedger();
                        }
                    } catch (err2) {
                        console.error(err2);
                    }
                    try {
                        if (typeof window.printflowInitCustomizationsPage === 'function') {
                            window.printflowInitCustomizationsPage();
                        }
                    } catch (err3) {
                        console.error(err3);
                    }
                    try {
                        if (typeof window.printflowInitCustomersPage === 'function') {
                            window.printflowInitCustomersPage();
                        }
                    } catch (err4) {
                        console.error(err4);
                    }
                    try {
                        if (typeof window.printflowInitProductsPage === 'function') {
                            window.printflowInitProductsPage();
                        }
                    } catch (err5) {
                        console.error(err5);
                    }
                    try {
                        if (typeof window.printflowInitServicesPage === 'function') {
                            window.printflowInitServicesPage();
                        }
                    } catch (err6) {
                        console.error(err6);
                    }
                    try {
                        if (typeof window.printflowInitInvItemsPage === 'function') {
                            window.printflowInitInvItemsPage();
                        }
                    } catch (err7) {
                        console.error(err7);
                    }
                    try {
                        if (typeof window.printflowInitBranchesPage === 'function') {
                            window.printflowInitBranchesPage();
                        }
                    } catch (err8) {
                        console.error(err8);
                    }
                    try {
                        if (typeof window.printflowInitUserStaffPage === 'function') {
                            window.printflowInitUserStaffPage();
                        }
                    } catch (err9) {
                        console.error(err9);
                    }
                printflowRunChartInitsForPage();
                try {
                    document.documentElement.dispatchEvent(new CustomEvent('printflow:turbo-page', { bubbles: true }));
                } catch (e3) { /* ignore */ }
            });
        });
    });
})();
</script>
