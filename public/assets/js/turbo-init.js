/**
 * Hotwired Turbo Drive & Alpine.js Initialization
 * Handles re-initializing components after Turbo body swaps.
 */
(function () {
    /* Turbo 8 intercepts POST and requires a redirecting response. This app mostly returns 200 + HTML after POST.
       Opt-in: only forms under an ancestor with data-turbo="true" use Turbo (those endpoints must redirect). */
    try {
        if (typeof Turbo !== 'undefined' && Turbo.config && Turbo.config.forms) {
            Turbo.config.forms.mode = 'optin';
        }
    } catch (e) { /* ignore */ }

    /* True after a Turbo body swap; skip duplicate Alpine.initTree on first full load (Alpine.start already ran). */
    var printflowAlpineNeedsReinit = false;

    /* ─── Helpers ─────────────────────────────────────────────────────────── */
    function normPath(href) {
        try {
            var u = new URL(href, window.location.href);
            var p = u.pathname.replace(/\/+$/, '') || '/';
            p = p.replace(/\.php$/i, '');
            return p;
        } catch (e) { return ''; }
    }

    /* ─── Sidebar active-state sync ──────────────────────────────────────── */
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

    /* ─── Alpine: tear down only the swapped main column (not the whole body) ─
     * destroyTree(document.body) broke persistent sidebar + raced inline <script>
     * that define x-data factories (customerModal, ordersPage, …) before initTree. */
    document.addEventListener('turbo:before-render', function () {
        printflowAlpineNeedsReinit = true;
        try {
            if (typeof window.Alpine === 'undefined' || typeof Alpine.destroyTree !== 'function') return;
            var mc = document.querySelector('.main-content');
            if (mc) {
                Alpine.destroyTree(mc);
            } else {
                Alpine.destroyTree(document.body);
            }
        } catch (e) { /* Alpine may throw while tearing partial trees; safe to ignore */ }
    });

    /* ─── Charts: tear down before swap/cache ────────────────────────────── */
    function printflowTeardownAllCharts() {
        try { if (typeof window.printflowTeardownReportsCharts === 'function')  window.printflowTeardownReportsCharts(); } catch (e) { console.error(e); }
        try { if (typeof window.printflowTeardownDashboardCharts === 'function') window.printflowTeardownDashboardCharts(); } catch (e) { console.error(e); }
    }
    document.addEventListener('turbo:before-render', function () { printflowTeardownAllCharts(); });
    document.addEventListener('turbo:before-cache', function () {
        printflowTeardownAllCharts();
        /* Drop Alpine clones (e.g. x-for tabs) before snapshot; restore + initTree was duplicating DOM. */
        try {
            if (typeof window.Alpine !== 'undefined' && typeof Alpine.destroyTree === 'function') {
                var mc = document.querySelector('.main-content');
                if (mc) Alpine.destroyTree(mc);
            }
        } catch (e) { /* ignore */ }
    });

    /* ─── Charts: re-init after paint ────────────────────────────────────── */
    function printflowRunChartInitsForPage() {
        try {
            if (document.getElementById('reportsFilterForm') && typeof window.printflowInitReportsCharts === 'function') {
                window.printflowInitReportsCharts(); return;
            }
            if (document.getElementById('salesChart') && typeof window.printflowInitDashboardCharts === 'function') {
                window.printflowInitDashboardCharts();
            }
        } catch (e) { console.error(e); }
    }

    /* ─── Navigation progress (layout only; no full-screen loader) ───────── */
    document.addEventListener('turbo:before-visit', function (ev) {
        document.documentElement.classList.add('pf-turbo-nav');
        queueMicrotask(function () {
            if (ev.defaultPrevented) {
                document.documentElement.classList.remove('pf-turbo-nav');
            }
        });
    });

    /* ─── turbo:load — main re-init hook ─────────────────────────────────── */
    function printflowInitAll() {
        function finishPageBoot() {
            printflowRunChartInitsForPage();
            try {
                document.dispatchEvent(new CustomEvent('printflow:page-init', { bubbles: false }));
            } catch (e) { /* ignore */ }
            try {
                document.documentElement.dispatchEvent(new CustomEvent('printflow:turbo-page', { bubbles: true }));
            } catch (e) { /* ignore */ }
        }

        /* Inline <script> in the new body defines x-data factories; initTree must run after them.
           Only re-init on Turbo swaps — first paint uses Alpine.start() only. */
        if (printflowAlpineNeedsReinit && typeof window.Alpine !== 'undefined' && typeof Alpine.initTree === 'function') {
            printflowAlpineNeedsReinit = false;
            setTimeout(function () {
                try {
                    var root = document.querySelector('.main-content') || document.body;
                    if (root) {
                        try { Alpine.destroyTree(root); } catch (e0) { /* ignore */ }
                        Alpine.initTree(root);
                    }
                } catch (e) { console.error('[turbo] Alpine.initTree:', e); }
                finishPageBoot();
            }, 0);
            return;
        }

        finishPageBoot();
    }

    document.addEventListener('turbo:load', function () {
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                document.documentElement.classList.remove('pf-turbo-nav');
                
                if (typeof window.Alpine !== 'undefined') {
                    printflowInitAll();
                } else {
                    var retryCount = 0;
                    var retryTimer = setInterval(function() {
                        retryCount++;
                        if (typeof window.Alpine !== 'undefined') {
                            clearInterval(retryTimer);
                            printflowInitAll();
                        } else if (retryCount > 50) {
                            clearInterval(retryTimer);
                            console.warn('[turbo] Alpine.js failed to load within timeout');
                            printflowInitAll();
                        }
                    }, 40);
                }
            });
        });
    });

    /* Nav-link prefetch on hover */
    document.addEventListener('mouseenter', function (e) {
        var a = e.target && e.target.closest && e.target.closest('a.nav-item[href]');
        if (!a || a.getAttribute('href').charAt(0) === '#') return;
        if (a.dataset.pfPrefetched) return;
        if (a.target === '_blank' || a.hasAttribute('download')) return;
        try {
            var u = new URL(a.href, location.href);
            if (u.origin !== location.origin) return;
        } catch (err) { return; }
        a.dataset.pfPrefetched = '1';
        var l = document.createElement('link');
        l.rel = 'prefetch';
        l.href = a.href;
        document.head.appendChild(l);
    }, true);

    /* Stale row onclick from cached DOM can reference inv_items handlers on other admin pages. */
    document.addEventListener('turbo:load', function () {
        if (typeof window.openStockCard !== 'function') {
            window.openStockCard = function () {};
        }
        if (typeof window.viewTransaction !== 'function') {
            window.viewTransaction = function () {};
        }
    });
})();
