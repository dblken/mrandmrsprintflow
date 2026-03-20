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
    document.addEventListener('turbo:load', function () {
        requestAnimationFrame(function () {
            requestAnimationFrame(printflowRunChartInitsForPage);
        });
    });
})();
</script>
