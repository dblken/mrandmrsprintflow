<?php
/**
 * Reports & Analytics — chart bootstrap (include before </body> so Turbo Drive re-executes it).
 * Expects the same variables as admin/reports.php in local scope.
 */
if (!isset($branch_empty)) {
    return;
}
?>
<script>
window.__pfReportsApexCharts = window.__pfReportsApexCharts || [];
var __pfReportsChartRootIds = ['ch-forecast','ch-products','ch-donut','ch-custom','ch-status'];
window.printflowDisconnectReportsChartLayoutHooks = function () {
    if (window.__pfReportsRevealIO) {
        try { window.__pfReportsRevealIO.disconnect(); } catch (e) {}
        window.__pfReportsRevealIO = null;
    }
    if (window.__pfReportsChartIO) {
        try { window.__pfReportsChartIO.disconnect(); } catch (e) {}
        window.__pfReportsChartIO = null;
    }
    if (window.__pfReportsLayoutResizeHandler) {
        try { window.removeEventListener('resize', window.__pfReportsLayoutResizeHandler); } catch (e) {}
        window.__pfReportsLayoutResizeHandler = null;
    }
    if (window.__pfReportsScrollSettledHandler) {
        var mc2 = document.querySelector('.main-content');
        if (mc2) {
            try { mc2.removeEventListener('scroll', window.__pfReportsScrollSettledHandler); } catch (e) {}
        }
        window.__pfReportsScrollSettledHandler = null;
    }
    if (window.__pfReportsMainRO) {
        try { window.__pfReportsMainRO.disconnect(); } catch (e) {}
        window.__pfReportsMainRO = null;
    }
    if (window.__pfReportsLayoutTimer) {
        try { clearTimeout(window.__pfReportsLayoutTimer); } catch (e) {}
        window.__pfReportsLayoutTimer = null;
    }
    if (window.__pfReportsScrollSettleTimer) {
        try { clearTimeout(window.__pfReportsScrollSettleTimer); } catch (e) {}
        window.__pfReportsScrollSettleTimer = null;
    }
    if (window.__pfReportsProductsRO) {
        try { window.__pfReportsProductsRO.disconnect(); } catch (e) {}
        window.__pfReportsProductsRO = null;
    }
};
window.printflowResizeAllReportsCharts = function () {
    (window.__pfReportsApexCharts || []).forEach(function (c) {
        try {
            if (c && typeof c.resize === 'function') c.resize();
        } catch (e) {}
    });
};
window.printflowAttachReportsChartLayoutHooks = function () {
    if (!document.getElementById('reportsFilterForm')) return;
    window.printflowDisconnectReportsChartLayoutHooks();
    function debouncedLayoutResize() {
        if (window.__pfReportsLayoutTimer) clearTimeout(window.__pfReportsLayoutTimer);
        window.__pfReportsLayoutTimer = setTimeout(function () {
            window.__pfReportsLayoutTimer = null;
            window.printflowResizeAllReportsCharts();
        }, 240);
    }
    window.__pfReportsLayoutResizeHandler = debouncedLayoutResize;
    window.addEventListener('resize', debouncedLayoutResize);
    var mainEl = document.querySelector('.main-content');
    if (mainEl && typeof ResizeObserver !== 'undefined') {
        window.__pfReportsMainRO = new ResizeObserver(function () {
            debouncedLayoutResize();
        });
        window.__pfReportsMainRO.observe(mainEl);
    }
    pfWireReportsScrollReveal();
};
window.printflowTeardownReportsCharts = function () {
    window.printflowDisconnectReportsChartLayoutHooks();
    if (window.__pfReportsTrendChart) {
        try { window.__pfReportsTrendChart.destroy(); } catch (e) {}
        window.__pfReportsTrendChart = null;
    }
    window.__pfReportsChartQueue = [];
    document.querySelectorAll('.ch-box[data-pf-chart-revealed]').forEach(function (b) {
        b.removeAttribute('data-pf-chart-revealed');
    });
    document.querySelectorAll('.ch-box.pf-chart-reveal-done').forEach(function (b) {
        b.classList.remove('pf-chart-reveal-done');
    });
    (window.__pfReportsApexCharts || []).forEach(function (c) {
        try {
            if (c && typeof c.destroy === 'function') c.destroy();
        } catch (e) {}
    });
    window.__pfReportsApexCharts = [];
    __pfReportsChartRootIds.forEach(function (id) {
        var n = document.getElementById(id);
        if (n) n.innerHTML = '';
    });
    document.querySelectorAll('.ch-box.pf-chart-loading').forEach(function (box) {
        box.classList.remove('pf-chart-loading');
        box.removeAttribute('aria-busy');
    });
};
window.__pfReportsChartQueue = window.__pfReportsChartQueue || [];
function pfNormalizeApexChartOptions(opts) {
    if (!opts || typeof opts !== 'object') return opts;
    opts.tooltip = opts.tooltip || {};
    opts.tooltip.fixed = Object.assign({ enabled: true }, opts.tooltip.fixed || {});
    return opts;
}
function pfExecuteApexReveal(entry, delayMs) {
    if (!entry || entry.rendered) return;
    var host = entry.host;
    var el = entry.el;
    var options = pfNormalizeApexChartOptions(entry.options);
    var run = function () {
        if (!el || !el.isConnected) {
            if (host && host.isConnected) {
                host.removeAttribute('data-pf-chart-revealed');
                host.classList.remove('pf-chart-loading');
                host.removeAttribute('aria-busy');
            }
            return;
        }
        var ch;
        try {
            ch = new ApexCharts(el, options);
        } catch (e) {
            if (host && host.isConnected) {
                host.removeAttribute('data-pf-chart-revealed');
                host.classList.remove('pf-chart-loading');
                host.removeAttribute('aria-busy');
            }
            return;
        }
        entry.rendered = true;
        window.__pfReportsApexCharts.push(ch);
        var done = function () {
            if (!el.isConnected) {
                try {
                    if (ch && typeof ch.destroy === 'function') ch.destroy();
                } catch (e2) {}
                return;
            }
            if (host && host.isConnected) {
                host.classList.remove('pf-chart-loading');
                host.removeAttribute('aria-busy');
                host.classList.add('pf-chart-reveal-done');
            }
            requestAnimationFrame(function () {
                try {
                    if (el.isConnected && ch && typeof ch.resize === 'function') ch.resize();
                } catch (e3) {}
            });
        };
        try {
            var pr = ch.render();
            if (pr && typeof pr.then === 'function') {
                pr.then(done).catch(done);
            } else {
                requestAnimationFrame(done);
            }
        } catch (e) {
            done();
        }
    };
    if (delayMs > 0) {
        setTimeout(run, delayMs);
    } else {
        requestAnimationFrame(run);
    }
}
function pfWireReportsScrollReveal() {
    var q = window.__pfReportsChartQueue || [];
    if (!q.length) return;
    var pendingByHost = new Map();
    var orphan = [];
    var stagger = 0;
    q.forEach(function (item) {
        if (item.rendered) return;
        if (item.host) {
            pendingByHost.set(item.host, item);
        } else {
            orphan.push(item);
        }
    });
    orphan.forEach(function (item) {
        pfExecuteApexReveal(item, stagger);
        stagger += 130;
    });
    if (!pendingByHost.size) {
        return;
    }
    if (typeof IntersectionObserver === 'undefined') {
        pendingByHost.forEach(function (item) {
            pfExecuteApexReveal(item, stagger);
            stagger += 130;
        });
        return;
    }
    var scrollRoot = document.querySelector('.main-content');
    if (window.__pfReportsRevealIO) {
        try { window.__pfReportsRevealIO.disconnect(); } catch (e) {}
    }
    window.__pfReportsRevealIO = new IntersectionObserver(function (entries) {
        var local = 0;
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            var host = entry.target;
            if (!host || !host.isConnected) return;
            if (host.getAttribute('data-pf-chart-revealed') === '1') return;
            var item = pendingByHost.get(host);
            if (!item || item.rendered || !item.el || !item.el.isConnected) return;
            host.setAttribute('data-pf-chart-revealed', '1');
            pfExecuteApexReveal(item, local * 135);
            local += 1;
        });
    }, { root: scrollRoot || null, threshold: 0, rootMargin: '0px 0px -6% 0px' });
    pendingByHost.forEach(function (item, host) {
        window.__pfReportsRevealIO.observe(host);
    });
}
function pfPushApexChart(el, options) {
    if (!el || !el.isConnected) return;
    var host = el.closest('.ch-box');
    if (host) {
        host.classList.add('pf-chart-loading');
        host.setAttribute('aria-busy', 'true');
    }
    el.innerHTML = '';
    window.__pfReportsChartQueue.push({ el: el, options: options, host: host, rendered: false });
}

function reportsFilterPanel() {
    const defFrom = '<?php echo date('Y-m-01'); ?>';
    const defTo   = '<?php echo date('Y-m-d'); ?>';
    return {
        filterOpen: false,
        sortOpen: false,
        get hasActiveFilters() {
            const f = document.getElementById('fp_from')?.value || defFrom;
            const t = document.getElementById('fp_to')?.value || defTo;
            return f !== defFrom || t !== defTo;
        },
        get filterCount() {
            return this.hasActiveFilters ? 1 : 0;
        },
        resetDateRange() {
            const f = document.getElementById('fp_from');
            const t = document.getElementById('fp_to');
            if (f) f.value = defFrom;
            if (t) t.value = defTo;
        },
        resetFilters() {
            this.resetDateRange();
            document.getElementById('reportsFilterForm')?.submit();
        },
        setPreset(preset) {
            const today = new Date();
            let from, to;
            if (preset === 'last_7') {
                to = new Date(today);
                from = new Date(today);
                from.setDate(from.getDate() - 7);
            } else if (preset === 'last_30') {
                to = new Date(today);
                from = new Date(today);
                from.setDate(from.getDate() - 30);
            } else if (preset === 'this_month') {
                from = new Date(today.getFullYear(), today.getMonth(), 1);
                to = new Date(today);
            } else if (preset === 'last_3') {
                to = new Date(today);
                from = new Date(today);
                from.setMonth(from.getMonth() - 3);
            } else if (preset === 'last_6') {
                to = new Date(today);
                from = new Date(today);
                from.setMonth(from.getMonth() - 6);
            } else if (preset === 'last_12') {
                to = new Date(today);
                from = new Date(today);
                from.setMonth(from.getMonth() - 12);
            } else return;
            const fmt = d => d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            const f = document.getElementById('fp_from');
            const t = document.getElementById('fp_to');
            if (f) f.value = fmt(from);
            if (t) t.value = fmt(to);
        }
    };
}

window.printflowInitReportsCharts = function () {
    if (!document.getElementById('reportsFilterForm')) return;
    var PF_HEATMAP_API = <?php echo json_encode(rtrim(AUTH_REDIRECT_BASE, '/') . '/admin/api_reports_heatmap.php'); ?>;
    if (typeof window.ApexCharts === 'undefined') {
        window.__pfApexLoadAttempts = (window.__pfApexLoadAttempts || 0) + 1;
        if (window.__pfApexLoadAttempts < 50) {
            window.setTimeout(function () {
                if (typeof window.printflowInitReportsCharts === 'function') window.printflowInitReportsCharts();
            }, 50);
        }
        return;
    }
    window.__pfApexLoadAttempts = 0;
    window.printflowTeardownReportsCharts();

    const PF_PRIMARY = '#00232b';
    const PF_SECONDARY = '#53C5E0';
    const PF_PAL = ['#00232b','#53C5E0','#0F4C5C','#3498DB','#6C5CE7','#3A86A8','#8ED6E6','#6B7C85','#F39C12','#2ECC71'];
    const PF_BAR_RANK = ['#00232b','#0F4C5C','#3A86A8','#2B6CB0','#276749','#2C5282','#234E52','#1A365D'];
    const PF_LINE_DARK = ['#00232b','#0F4C5C','#3A86A8','#3498DB','#6C5CE7','#6B7C85'];
    const PF_LINE_FORE = ['#8ED6E6','#53C5E0','#8ED6E6','#E5EEF2','#C4B5FD','#B8C5CC'];
    const PF_OPT = {
        toolbar:{show:false},
        redrawOnParentResize:false,
        redrawOnWindowResize:true,
        animations:{
            enabled:true,
            easing:'easeinout',
            speed:1850,
            animateGradually:{enabled:true,delay:95},
            dynamicAnimation:{enabled:true,speed:780}
        },
        fontFamily:'inherit'
    };
    function pfEscHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /**
     * Calculate tier based on percentage of max value (matches PHP logic).
     * @param {number} v - Current value
     * @param {number} maxV - Maximum value in dataset
     * @returns {string} 'low'|'med'|'high'
     */
    function pfHmValueTier(v, maxV) {
        v = Number(v) || 0;
        maxV = Number(maxV) || 0;
        if (v <= 0 || maxV <= 0) return 'low';
        var pct = (v / maxV) * 100;
        if (pct <= 25) return 'low';
        if (pct <= 65) return 'med';
        return 'high';
    }

    /**
     * Build HTML/CSS heatmap into mount (replaces innerHTML).
     * series = [{ name, data: [{ x, y, kind: future|empty|value }] }]
     * meta = { serverYear, serverMonth, year } for month-header styling when kind omitted.
     */
    function pfReportsMountHeatmapFromApi(mount, series, meta) {
        if (!mount || !series || !series.length) return;
        meta = meta || {};
        var serverYear = Number(meta.serverYear);
        var serverMonth = Number(meta.serverMonth);
        if (!serverYear) serverYear = new Date().getFullYear();
        if (!serverMonth) serverMonth = new Date().getMonth() + 1;
        var displayYear = Number(meta.year);
        if (!displayYear) displayYear = serverYear;

        // Calculate global max value for dynamic tier thresholds
        var maxValue = 0;
        series.forEach(function(row) {
            var pts = row.data || [];
            pts.forEach(function(pt) {
                if (pt && typeof pt.y !== 'undefined') {
                    var v = Number(pt.y) || 0;
                    if (v > maxValue) maxValue = v;
                }
            });
        });

        var fallbackM = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var headMonths = fallbackM;
        var firstPts = series[0] && series[0].data ? series[0].data : [];
        if (firstPts.length === 12) {
            headMonths = firstPts.map(function (p, i) {
                return p && p.x != null ? String(p.x) : fallbackM[i];
            });
        }
        mount.innerHTML = '';
        var outer = document.createElement('div');
        outer.className = 'pf-hm-outer';
        var root = document.createElement('div');
        root.className = 'pf-hm-root';
        root.id = 'pf-hm-root';
        var grid = document.createElement('div');
        grid.className = 'pf-hm-grid';
        grid.setAttribute('role', 'grid');
        grid.setAttribute('aria-label', 'Seasonal demand by service and month');
        var corner = document.createElement('div');
        corner.className = 'pf-hm-corner';
        corner.setAttribute('aria-hidden', 'true');
        var monthRow = document.createElement('div');
        monthRow.className = 'pf-hm-months';
        monthRow.setAttribute('role', 'row');
        headMonths.forEach(function (m, idx) {
            var th = document.createElement('div');
            var mi = idx + 1;
            th.className = 'pf-hm-month' + (displayYear === serverYear && mi > serverMonth ? ' pf-hm-month--future' : '');
            th.setAttribute('role', 'columnheader');
            th.textContent = m;
            monthRow.appendChild(th);
        });
        grid.appendChild(corner);
        grid.appendChild(monthRow);
        series.forEach(function (row) {
            var svc = row.name != null ? String(row.name) : '';
            var labelCol = document.createElement('div');
            labelCol.className = 'pf-hm-label-col';
            var span = document.createElement('span');
            span.className = 'pf-hm-label-text';
            span.textContent = svc;
            span.setAttribute('title', svc);
            labelCol.appendChild(span);
            var tiles = document.createElement('div');
            tiles.className = 'pf-hm-tiles';
            tiles.setAttribute('role', 'row');
            var pts = row.data || [];
            headMonths.forEach(function (m, idx) {
                var pt = pts[idx];
                var v = pt && typeof pt.y !== 'undefined' ? Number(pt.y) || 0 : 0;
                var moLabel = pt && pt.x != null ? String(pt.x) : m;
                var kind = pt && pt.kind ? String(pt.kind) : '';
                if (!kind) {
                    if (displayYear === serverYear && idx + 1 > serverMonth) kind = 'future';
                    else kind = v > 0 ? 'value' : 'empty';
                }
                var cell = document.createElement('div');
                var val = document.createElement('span');
                val.className = 'pf-hm-val';
                if (kind === 'future') {
                    cell.className = 'pf-hm-cell pf-hm-cell--future';
                    cell.setAttribute('role', 'gridcell');
                    cell.setAttribute('aria-disabled', 'true');
                    cell.setAttribute('title', svc + ' · ' + moLabel + ' — No data yet');
                } else if (kind === 'empty') {
                    cell.className = 'pf-hm-cell pf-hm-cell--nodata';
                    cell.setAttribute('role', 'gridcell');
                    cell.setAttribute('tabindex', '0');
                    cell.setAttribute('title', svc + ' · ' + moLabel + ' — No transactions');
                } else {
                    // Pass maxValue to pfHmValueTier for dynamic thresholds
                    cell.className = 'pf-hm-cell pf-hm-cell--' + pfHmValueTier(v, maxValue);
                    cell.setAttribute('role', 'gridcell');
                    cell.setAttribute('tabindex', '0');
                    cell.setAttribute('title', svc + ' · ' + moLabel + ' · ' + v + ' units');
                    val.textContent = String(v);
                }
                cell.appendChild(val);
                tiles.appendChild(cell);
            });
            grid.appendChild(labelCol);
            grid.appendChild(tiles);
        });
        root.appendChild(grid);
        outer.appendChild(root);
        mount.appendChild(outer);
    }
    window.pfReportsMountHeatmapFromApi = pfReportsMountHeatmapFromApi;

    window.pfDestroyReportsHeatmapChart = function () {};

<?php if (!$branch_empty): ?>

    (function(){
        const labels   = <?php echo json_encode(array_merge($trend12_labels, [$next_month_label])); ?>;
        /** Last index of 12-month history; next index is the forecast point. */
        var PF_TREND_LAST_HIST_IDX = 11;
        var PF_TREND_FORECAST_IDX = 12;
        function pfTrendForecastDatasetStyle() {
            return {
                segment: {
                    borderDash: function (ctx) {
                        if (ctx.p0DataIndex === PF_TREND_LAST_HIST_IDX && ctx.p1DataIndex === PF_TREND_FORECAST_IDX) {
                            return [6, 4];
                        }
                        return undefined;
                    }
                },
                pointRadius: function (ctx) {
                    return ctx.dataIndex === PF_TREND_FORECAST_IDX ? 4 : 3;
                },
                pointHoverRadius: function (ctx) {
                    return ctx.dataIndex === PF_TREND_FORECAST_IDX ? 7 : 6;
                }
            };
        }
        var pfTrendForecastBoundaryPlugin = {
            id: 'pfTrendForecastBoundary',
            beforeDatasetsDraw: function (chart) {
                var labels = chart.data.labels || [];
                if (labels.length <= PF_TREND_FORECAST_IDX) return;
                var xScale = chart.scales.x;
                if (!xScale) return;
                var mid;
                if (typeof xScale.getPixelForTick === 'function') {
                    var t1 = xScale.getPixelForTick(PF_TREND_LAST_HIST_IDX);
                    var t2 = xScale.getPixelForTick(PF_TREND_FORECAST_IDX);
                    if (t1 == null || t2 == null) return;
                    mid = (t1 + t2) / 2;
                } else {
                    var m0 = chart.getDatasetMeta(0);
                    if (!m0 || !m0.data || !m0.data[PF_TREND_LAST_HIST_IDX] || !m0.data[PF_TREND_FORECAST_IDX]) return;
                    mid = (m0.data[PF_TREND_LAST_HIST_IDX].x + m0.data[PF_TREND_FORECAST_IDX].x) / 2;
                }
                var ctx2 = chart.ctx;
                var top = chart.chartArea.top;
                var bot = chart.chartArea.bottom;
                ctx2.save();
                ctx2.fillStyle = 'rgba(99, 102, 241, 0.07)';
                ctx2.fillRect(mid, top, chart.chartArea.right - mid, bot - top);
                ctx2.restore();
            },
            afterDatasetsDraw: function (chart) {
                var labels = chart.data.labels || [];
                if (labels.length <= PF_TREND_FORECAST_IDX) return;
                var xScale = chart.scales.x;
                if (!xScale) return;
                var mid;
                if (typeof xScale.getPixelForTick === 'function') {
                    var a = xScale.getPixelForTick(PF_TREND_LAST_HIST_IDX);
                    var b = xScale.getPixelForTick(PF_TREND_FORECAST_IDX);
                    if (a == null || b == null) return;
                    mid = (a + b) / 2;
                } else {
                    var m0 = chart.getDatasetMeta(0);
                    if (!m0 || !m0.data || !m0.data[PF_TREND_LAST_HIST_IDX] || !m0.data[PF_TREND_FORECAST_IDX]) return;
                    mid = (m0.data[PF_TREND_LAST_HIST_IDX].x + m0.data[PF_TREND_FORECAST_IDX].x) / 2;
                }
                var ctx2 = chart.ctx;
                var top = chart.chartArea.top;
                var bot = chart.chartArea.bottom;
                var right = chart.chartArea.right;
                ctx2.save();
                ctx2.strokeStyle = 'rgba(71, 85, 105, 0.55)';
                ctx2.lineWidth = 1.25;
                ctx2.setLineDash([5, 5]);
                ctx2.beginPath();
                ctx2.moveTo(mid, top);
                ctx2.lineTo(mid, bot);
                ctx2.stroke();
                ctx2.setLineDash([]);
                var cx = (mid + right) / 2;
                ctx2.fillStyle = 'rgba(71, 85, 105, 0.92)';
                ctx2.font = '600 11px system-ui, -apple-system, Segoe UI, sans-serif';
                ctx2.textAlign = 'center';
                ctx2.textBaseline = 'top';
                ctx2.fillText('Forecast', cx, top + 4);
                ctx2.restore();
            }
        };
<?php if ($trend_metric === 'revenue'): ?>
        const trendStore = <?php echo json_encode(array_merge($trend12_revenue_store, [$forecast_revenue_store])); ?>;
        const trendCustom = <?php echo json_encode(array_merge($trend12_revenue_custom, [$forecast_revenue_custom])); ?>;
        const trendYAxisFmt = function (v) { return '₱' + Number(v).toLocaleString(); };
        const trendDatasets = [
            {
                label: 'Store revenue (₱)',
                data: trendStore,
                borderColor: '#00232b',
                backgroundColor: 'transparent',
                borderWidth: 3,
                tension: 0.35,
                pointBackgroundColor: '#00232b',
                pointRadius: 3,
                pointHoverRadius: 6,
                yAxisID: 'y'
            },
            {
                label: 'Customization revenue (₱)',
                data: trendCustom,
                borderColor: '#6366F1',
                backgroundColor: 'transparent',
                borderWidth: 3,
                tension: 0.35,
                pointBackgroundColor: '#6366F1',
                pointRadius: 3,
                pointHoverRadius: 6,
                yAxisID: 'y'
            }
        ];
        const trendLegend = true;
        const trendScales = {
            y:  { beginAtZero: true, ticks: { font: { size: 11 }, callback: function(v) { return trendYAxisFmt(v); } }, grid: { color: '#f3f4f6' } },
            x:  { ticks: { font: { size: 10 }, maxRotation: 45 }, grid: { display: false } }
        };
<?php else: ?>
<?php if ($trend_metric === 'orders'): ?>
        const trendData = <?php echo json_encode(array_merge($trend12_orders, [$forecast_orders])); ?>;
        const trendLabel = 'Orders';
        const trendColor = '#0F4C5C';
        const trendYAxisFmt = function (v) { return Math.round(v); };
        const trendTipFmt = function (v) { return Math.round(v) + ' orders'; };
        const trendDatasets = [{
            label: trendLabel,
            data: trendData,
            borderColor: trendColor,
            backgroundColor: 'transparent',
            borderWidth: 3,
            tension: 0.35,
            pointBackgroundColor: trendColor,
            pointRadius: 3,
            pointHoverRadius: 6,
            yAxisID: 'y'
        }];
        const trendLegend = false;
        const trendScales = {
            y:  { beginAtZero: true, ticks: { font: { size: 11 }, callback: function(v) { return trendYAxisFmt(v); } }, grid: { color: '#f3f4f6' } },
            x:  { ticks: { font: { size: 10 }, maxRotation: 45 }, grid: { display: false } }
        };
<?php else: ?>
        // Default view: store + customization revenue (₱) + total orders — aligned with admin/manager dashboard chart.
        const trendStore = <?php echo json_encode(array_merge($trend12_revenue_store, [$forecast_revenue_store])); ?>;
        const trendCustom = <?php echo json_encode(array_merge($trend12_revenue_custom, [$forecast_revenue_custom])); ?>;
        const trendOrders = <?php echo json_encode(array_merge($trend12_orders, [$forecast_orders])); ?>;
        const trendDatasets = [
            {
                label: 'Store revenue (₱)',
                data: trendStore,
                borderColor: '#00232b',
                backgroundColor: 'transparent',
                borderWidth: 3,
                tension: 0.35,
                pointBackgroundColor: '#00232b',
                pointRadius: 3,
                pointHoverRadius: 6,
                yAxisID: 'yRevenue'
            },
            {
                label: 'Customization revenue (₱)',
                data: trendCustom,
                borderColor: '#6366F1',
                backgroundColor: 'transparent',
                borderWidth: 3,
                tension: 0.35,
                pointBackgroundColor: '#6366F1',
                pointRadius: 3,
                pointHoverRadius: 6,
                yAxisID: 'yRevenue'
            },
            {
                label: 'Orders (total)',
                data: trendOrders,
                borderColor: '#53C5E0',
                backgroundColor: 'transparent',
                borderWidth: 3,
                tension: 0.35,
                pointBackgroundColor: '#53C5E0',
                pointRadius: 3,
                pointHoverRadius: 6,
                yAxisID: 'yOrders'
            }
        ];
        const trendLegend = true;
        const trendScales = {
            yRevenue: {
                type: 'linear',
                position: 'left',
                beginAtZero: true,
                ticks: { font: { size: 11 }, callback: function(v) { return '₱' + Number(v).toLocaleString(); } },
                grid: { color: '#f3f4f6' }
            },
            yOrders: {
                type: 'linear',
                position: 'right',
                beginAtZero: true,
                ticks: { font: { size: 11 }, callback: function(v) { return Math.round(v); } },
                grid: { display: false }
            },
            x: { ticks: { font: { size: 10 }, maxRotation: 45 }, grid: { display: false } }
        };
<?php endif; ?>
<?php endif; ?>
        trendDatasets.forEach(function (ds) {
            Object.assign(ds, pfTrendForecastDatasetStyle());
        });
        const ctx = document.getElementById('salesChart');
        if (!ctx || typeof Chart === 'undefined') return;
        if (window.__pfReportsTrendChart) {
            try { window.__pfReportsTrendChart.destroy(); } catch (e) {}
            window.__pfReportsTrendChart = null;
        }
        window.__pfReportsTrendChart = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: trendDatasets
            },
            plugins: [pfTrendForecastBoundaryPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 1200, easing: 'easeOutQuart' },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: trendLegend, position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: {
                        animation: { duration: 180 },
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            title: function (items) {
                                if (!items || !items.length) return '';
                                var it = items[0];
                                var t = it.label != null ? String(it.label) : '';
                                if (it.dataIndex === PF_TREND_FORECAST_IDX) {
                                    return t + ' (forecast)';
                                }
                                return t;
                            },
                            label: function(ctx0) {
                                var lab = ctx0.dataset && ctx0.dataset.label ? String(ctx0.dataset.label) : '';
                                if (lab.indexOf('Orders') !== -1) {
                                    return lab.replace(/\s*\(total\)\s*/i, '').trim() + ': ' + Math.round(ctx0.parsed.y);
                                }
                                if (lab.indexOf('revenue') !== -1 || lab.indexOf('₱') !== -1) {
                                    return lab + ': ₱' + Number(ctx0.parsed.y).toLocaleString(undefined, { minimumFractionDigits: 0 });
                                }
                                return (lab ? lab + ': ' : '') + String(ctx0.parsed.y ?? '');
                            }
                        }
                    }
                },
                scales: trendScales
            }
        });
    })();

<?php if ($can_forecast && !empty($fc_series_data)): ?>
    (function(){
        const allLabels = <?php echo json_encode($fc_all_labels); ?>;
        const fcHistCount = <?php echo (int) count($fc_hist_labels); ?>;
        /** Compact month labels for the axis (e.g. Oct'25) so every month can show without skipping. */
        function pfFcShortLabel(lb) {
            var t = String(lb == null ? '' : lb).trim();
            var parts = t.split(/\s+/).filter(Boolean);
            if (parts.length >= 2) {
                var mo = parts[0];
                if (mo.length > 3) mo = mo.slice(0, 3);
                var y = String(parts[parts.length - 1]).replace(/[^0-9]/g, '');
                if (y.length >= 2) y = y.slice(-2);
                return mo + "'" + y;
            }
            return t.length > 9 ? t.slice(0, 9) : t;
        }
        const shortCats = allLabels.map(function (lb) { return pfFcShortLabel(lb); });
        /** Vertical line + label must use same category strings as xaxis (shortCats). */
        const fcastStart = shortCats[fcHistCount] || shortCats[0];
        const series = [];
        const colors = [];
        const dashes = [];
        let idx = 0;
<?php
    $fc_js_data = [];
foreach ($fc_series_data as $prod => $pd) {
    $fc_js_data[] = [
        'name' => $prod,
        'hist' => $pd['hist'],
        'fore' => $pd['fore'],
    ];
}
echo '        const fcData = '.json_encode($fc_js_data).";\n";
?>
        fcData.forEach(function(p){
            const cAct = PF_LINE_DARK[idx % PF_LINE_DARK.length];
            const cFore = PF_LINE_FORE[idx % PF_LINE_FORE.length];
            const histData = [...p.hist, ...new Array(p.fore.length).fill(null)];
            const foreData = [...new Array(p.hist.length - 1).fill(null), p.hist[p.hist.length - 1], ...p.fore];
            series.push({ name: p.name + ' (actual)', data: histData });
            series.push({ name: p.name + ' (forecast)', data: foreData });
            colors.push(cAct, cFore);
            dashes.push(0, 6);
            idx++;
        });

        function pfFcPushForecastChart() {
            var fcMount = document.getElementById('ch-forecast');
            if (!fcMount || !fcMount.parentElement) return;
            
            // Clear any existing content to prevent duplicates
            fcMount.innerHTML = '';
            
            var wrap = fcMount.parentElement;
            var h = wrap.clientHeight || wrap.getBoundingClientRect().height;
            if (h < 160) h = 320;
            var fcChartH = Math.max(288, Math.min(480, Math.round(h)));

            pfPushApexChart(fcMount, {
            chart: {
                ...PF_OPT, 
                type: 'line', 
                height: fcChartH,
                toolbar: { show: false },
                parentHeightOffset: 0,
                animations: { enabled: true, easing: 'easeinout', speed: 800 },
                zoom: { enabled: false },
                offsetX: 0,
                offsetY: 0,
                width: '100%'
            },
            dataLabels: { enabled: false },
            series: series,
            xaxis: {
                categories: shortCats,
                tickPlacement: 'on',
                range: (shortCats.length - 1),
                labels: {
                    style: { 
                        fontSize: '11px', 
                        fontWeight: 700,
                        colors: '#1f2937'
                    },
                    rotate: -45,
                    rotateAlways: true,
                    trim: false,
                    hideOverlappingLabels: false,
                    offsetX: 0,
                    offsetY: 6
                },
                axisBorder: { 
                    show: true,
                    color: '#d1d5db',
                    height: 1.5
                },
                axisTicks: { 
                    show: true,
                    color: '#e5e7eb',
                    height: 4
                }
            },
            yaxis: {
                show: true,
                floating: false,
                labels: {
                    formatter: function (v) { return v != null ? Math.round(v) : ''; },
                    offsetX: -8,
                    style: { 
                        fontSize: '11px', 
                        fontWeight: 600,
                        colors: '#374151'
                    }
                },
                axisBorder: { 
                    show: true,
                    color: '#e5e7eb'
                },
                axisTicks: { 
                    show: true,
                    color: '#e5e7eb'
                }
            },
            colors: colors,
            stroke: { curve: 'smooth', width: 3, dashArray: dashes },
            markers: { size: 0 },
            tooltip:{
                shared: true,
                intersect: false,
                followCursor: false,
                theme: 'dark',
                style: {
                    fontSize: '12px',
                    fontFamily: 'inherit'
                },
                x: {
                    show: true,
                    formatter: function (val, opts) {
                        var i = opts && typeof opts.dataPointIndex === 'number' ? opts.dataPointIndex : -1;
                        if (i >= 0 && allLabels[i] != null) {
                            var label = String(allLabels[i]);
                            // Add forecast indicator if in forecast range
                            if (i >= fcHistCount) {
                                return label + ' (forecast)';
                            }
                            return label;
                        }
                        return val;
                    }
                },
                y: { 
                    formatter: function(v) { 
                        return v != null && v !== 0 ? Math.round(v) + ' orders' : null;
                    },
                    title: {
                        formatter: function(seriesName) {
                            // Remove (actual) and (forecast) suffixes for cleaner display
                            return seriesName.replace(/\s*\((actual|forecast)\)\s*/gi, '').trim();
                        }
                    }
                },
                custom: function({ series, seriesIndex, dataPointIndex, w }) {
                    if (dataPointIndex < 0) return '';
                    
                    // Get the month label
                    var monthLabel = allLabels[dataPointIndex] || '';
                    var isForecast = dataPointIndex >= fcHistCount;
                    
                    // Build a map of unique products with their values
                    var productMap = {};
                    var seriesNames = w.config.series || [];
                    
                    seriesNames.forEach(function(s, idx) {
                        if (!s || !s.data || s.data[dataPointIndex] == null) return;
                        
                        var value = s.data[dataPointIndex];
                        if (value === null || value === 0) return; // Skip empty values
                        
                        // Extract product name (remove actual/forecast suffix)
                        var productName = (s.name || '').replace(/\s*\((actual|forecast)\)\s*/gi, '').trim();
                        if (!productName) return;
                        
                        // Determine if this is actual or forecast data
                        var isActualSeries = /\(actual\)/i.test(s.name);
                        var isForecastSeries = /\(forecast\)/i.test(s.name);
                        
                        // Only show forecast series in forecast range, actual series in historical range
                        if (isForecast && !isForecastSeries) return;
                        if (!isForecast && isForecastSeries) return;
                        
                        // Store or update the product value
                        if (!productMap[productName]) {
                            productMap[productName] = {
                                value: Math.round(value),
                                color: w.config.colors[idx] || '#94a3b8',
                                isForecast: isForecastSeries
                            };
                        }
                    });
                    
                    // Build HTML
                    var html = '<div style="padding:10px 12px;min-width:180px;max-width:280px;">';
                    
                    // Title with clear visibility
                    html += '<div style="font-weight:700;font-size:13px;color:#f1f5f9;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,0.1);">';
                    html += pfEscHtml(monthLabel);
                    if (isForecast) {
                        html += ' <span style="color:#93c5fd;font-size:11px;font-weight:600;">(forecast)</span>';
                    }
                    html += '</div>';
                    
                    // Product list
                    var products = Object.keys(productMap);
                    if (products.length === 0) {
                        html += '<div style="color:#94a3b8;font-size:11px;font-style:italic;">No data</div>';
                    } else {
                        products.forEach(function(productName, idx) {
                            var data = productMap[productName];
                            var isLast = idx === products.length - 1;
                            
                            html += '<div style="display:flex;align-items:center;gap:8px;padding:5px 0;' + (!isLast ? 'border-bottom:1px solid rgba(255,255,255,0.05);' : '') + '">';
                            
                            // Color indicator
                            html += '<span style="width:8px;height:8px;border-radius:50%;background:' + data.color + ';flex-shrink:0;"></span>';
                            
                            // Product name and value
                            html += '<div style="flex:1;display:flex;justify-content:space-between;align-items:center;gap:12px;min-width:0;">';
                            
                            // Truncate long names
                            var displayName = productName.length > 28 ? productName.substring(0, 28) + '...' : productName;
                            html += '<span style="color:#e2e8f0;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' + pfEscHtml(productName) + '">' + pfEscHtml(displayName) + '</span>';
                            
                            // Value
                            html += '<span style="color:#fff;font-weight:700;font-size:12px;white-space:nowrap;">' + data.value.toLocaleString() + '</span>';
                            
                            html += '</div></div>';
                        });
                    }
                    
                    html += '</div>';
                    return html;
                }
            },
            annotations: {
                xaxis: [{
                    x: fcastStart,
                    borderColor: '#0ea5e9',
                    strokeDashArray: 4
                }]
            },
            legend: { show: false, floating: true },
            grid: {
                borderColor: '#e5e7eb',
                strokeDashArray: 0,
                xaxis: { lines: { show: false } },
                yaxis: { lines: { show: true } },
                padding: {
                    left: 20,
                    right: 20,
                    top: 15,
                    bottom: 25
                }
            },
            responsive: [
                {
                    breakpoint: 1024,
                    options: {
                        chart: { height: 320 },
                        xaxis: {
                            labels: {
                                rotate: -45,
                                style: { fontSize: '9px' },
                                hideOverlappingLabels: true
                            }
                        },
                        grid: { padding: { left: 20, right: 10, bottom: 20 } }
                    }
                }
            ]
        });
        }

        /* Must run before printflowAttachReportsChartLayoutHooks() so pfWireReportsScrollReveal sees this chart.
           (Deferred rAF previously queued the push after the observer was wired — chart never revealed.) */
        pfFcPushForecastChart();
    })();
<?php endif; ?>

<?php if (!empty($top_products)): ?>
    (function(){
        const fullNames = <?php echo json_encode(array_map(fn($p) => (string)($p['product_name'] ?? ''), $top_products)); ?>;
        const mergeKeys = <?php
            $mk = [];
            foreach ($top_products as $p) {
                $mk[] = !empty($p['product_id']) ? 'p:' . (int) $p['product_id'] : 's:' . mb_strtolower((string) ($p['product_name'] ?? ''));
            }
            echo json_encode($mk);
            ?>;
        const prevMap = <?php echo json_encode($top_products_prev ?? []); ?>;
        const qtys  = <?php echo json_encode(array_map(fn($p) => (int)$p['qty_sold'], $top_products)); ?>;
        const revenues = <?php echo json_encode(array_map(fn($p) => (float)$p['revenue'], $top_products)); ?>;
        
        // Limit to top 8 for cleaner display
        const displayCount = Math.min(8, qtys.length);
        const displayQtys = qtys.slice(0, displayCount);
        const displayNames = fullNames.slice(0, displayCount);
        const displayRevenues = revenues.slice(0, displayCount);
        
        var maxQty = Math.max(...displayQtys);
        var xMax = Math.ceil(maxQty * 1.05); // Only 5% padding for tighter fit
        
        // Calculate percentages relative to top seller
        const topQty = displayQtys[0] || 1;
        const percentages = displayQtys.map(q => Math.round((q / topQty) * 100));
        
        // Truncate names for display
        const shortNames = displayNames.map(function(nm) {
            var t = String(nm || '').trim();
            return t.length > 30 ? (t.substring(0, 30) + '...') : t;
        });
        
        // Use empty string as categories to hide Y-axis labels
        const categories = displayQtys.map(function() { return ''; });
        
        // Premium color gradient
        const barColors = [
            '#00232b', '#0F4C5C', '#3A86A8', '#2B6CB0', 
            '#276749', '#2C5282', '#234E52', '#1A365D'
        ];
        
        const productSeriesData = categories.map(function (cat, i) {
            return { 
                x: cat, 
                y: displayQtys[i] || 0, 
                fillColor: barColors[i]
            };
        });
        
        const productsMount = document.getElementById('ch-products');
        var productsWrap = productsMount ? productsMount.closest('.ch-box') : null;
        var productsWrapH = productsWrap
            ? Math.max(360, Math.round(productsWrap.getBoundingClientRect().height || productsWrap.clientHeight || 0))
            : 420;
        
        pfPushApexChart(productsMount, {
            chart:{
                ...PF_OPT,
                id:'pf-ch-products-bar',
                redrawOnParentResize:true,
                type:'bar',
                height: productsWrapH,
                width: '100%',
                animations:{
                    enabled:true, 
                    easing:'easeinout', 
                    speed:800,
                    animateGradually: { enabled: true, delay: 80 },
                    dynamicAnimation: { enabled: true, speed: 400 }
                }
            },
            plotOptions:{
                bar:{
                    horizontal:true,
                    borderRadius:6,
                    barHeight:'70%',
                    distributed:true,
                    dataLabels:{ position:'center' }
                }
            },
            series:[{name:'Units Sold', data:productSeriesData}],
            xaxis:{
                min: 0,
                max: xMax,
                tickAmount: 5,
                labels:{
                    style:{fontSize:'11px', fontWeight:600, colors:'#64748b'},
                    formatter:function (v) { return Number(v || 0).toLocaleString(); }
                },
                axisBorder: { show: true, color: '#e5e7eb' },
                axisTicks: { show: true, color: '#e5e7eb' }
            },
            yaxis:{
                labels:{
                    show: false
                }
            },
            colors: barColors, 
            legend:{show:false},
            dataLabels:{
                enabled:true,
                offsetX: 0,
                textAnchor: 'middle',
                distributed: false,
                style:{
                    fontSize:'12px',
                    colors:['#ffffff'],
                    fontWeight: 700
                },
                dropShadow: {
                    enabled: true,
                    top: 1,
                    left: 1,
                    blur: 3,
                    color: '#000',
                    opacity: 0.45
                },
                formatter:function (v, opts) { 
                    var idx = opts.dataPointIndex;
                    var qty = Number(v || 0);
                    var pct = percentages[idx];
                    var name = shortNames[idx] || '';
                    
                    // Format: Name • Value (Percentage for top 3)
                    if (idx < 3) {
                        return name + ' • ' + qty.toLocaleString() + ' (' + pct + '%)';
                    }
                    return name + ' • ' + qty.toLocaleString();
                }
            },
            states: {
                hover: {
                    filter: { type: 'lighten', value: 0.15 }
                },
                active: {
                    filter: { type: 'darken', value: 0.05 }
                }
            },
            tooltip:{
                theme:'dark',
                fillSeriesColor:false,
                style:{fontSize:'12px'},
                custom:function (ctx) {
                    var i = ctx.dataPointIndex;
                    if (i < 0) return '';
                    var nm = displayNames[i] || '';
                    var q = displayQtys[i];
                    var rev = displayRevenues[i];
                    var pct = percentages[i];
                    var k = mergeKeys[i];
                    var prev = prevMap[k];
                    var trend = '';
                    var trendIcon = '';
                    var trendColor = '#94a3b8';
                    
                    if (typeof prev === 'number' && prev > 0) {
                        var chg = Math.round(((q - prev) / prev) * 100);
                        if (chg > 0) {
                            trendIcon = '↑';
                            trendColor = '#10b981';
                            trend = trendIcon + ' +' + chg + '% vs prior period';
                        } else if (chg < 0) {
                            trendIcon = '↓';
                            trendColor = '#ef4444';
                            trend = trendIcon + ' ' + chg + '% vs prior period';
                        } else {
                            trendIcon = '→';
                            trend = trendIcon + ' No change';
                        }
                    }
                    
                    var html = '<div style="padding:12px 14px;min-width:240px;">';
                    
                    // Rank badge + Service name
                    html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">';
                    html += '<span style="background:' + barColors[i] + ';color:#fff;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;">#' + (i + 1) + '</span>';
                    html += '<span style="font-weight:700;color:#f1f5f9;font-size:13px;flex:1;">' + pfEscHtml(nm) + '</span>';
                    html += '</div>';
                    
                    // Stats grid
                    html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">';
                    html += '<div style="background:rgba(83,197,224,0.1);padding:8px;border-radius:6px;">';
                    html += '<div style="font-size:10px;color:#94a3b8;margin-bottom:2px;">Units Sold</div>';
                    html += '<div style="font-size:16px;font-weight:800;color:#53C5E0;">' + q.toLocaleString() + '</div>';
                    html += '</div>';
                    html += '<div style="background:rgba(16,185,129,0.1);padding:8px;border-radius:6px;">';
                    html += '<div style="font-size:10px;color:#94a3b8;margin-bottom:2px;">Revenue</div>';
                    html += '<div style="font-size:16px;font-weight:800;color:#10b981;">₱' + rev.toLocaleString(undefined, {maximumFractionDigits:0}) + '</div>';
                    html += '</div>';
                    html += '</div>';
                    
                    // Performance vs top
                    html += '<div style="background:rgba(255,255,255,0.05);padding:8px;border-radius:6px;margin-bottom:8px;">';
                    html += '<div style="font-size:11px;color:#cbd5e1;">Performance: <strong style="color:#fff;">' + pct + '%</strong> of top seller</div>';
                    html += '</div>';
                    
                    // Trend
                    if (trend) {
                        html += '<div style="padding-top:8px;border-top:1px solid rgba(255,255,255,0.1);">';
                        html += '<div style="font-size:11px;color:' + trendColor + ';font-weight:600;">' + pfEscHtml(trend) + '</div>';
                        html += '</div>';
                    }
                    
                    html += '</div>';
                    return html;
                }
            },
            grid:{
                borderColor:'#f1f5f9',
                strokeDashArray:3,
                padding:{left:8,right:16,top:8,bottom:8},
                xaxis:{lines:{show:true}},
                yaxis:{lines:{show:false}}
            }
        });
    })();
<?php endif; ?>

<?php if (!empty($rev_donut)): ?>
    (function(){
        const vals   = <?php echo json_encode(array_map(fn($p) => round((float)$p['revenue'], 2), $rev_donut)); ?>;
        const total  = vals.reduce(function(a,b){ return a+b; }, 0);
        const labels = <?php echo json_encode(array_map(fn($p) => (string)($p['product_name'] ?? ''), $rev_donut)); ?>;
        pfPushApexChart(document.getElementById('ch-donut'), {
            chart:{...PF_OPT, type:'donut', height:240},
            series:vals, labels:labels, colors:PF_PAL,
            plotOptions:{
                pie:{
                    donut:{
                        size:'68%',
                        labels:{
                            show:true,
                            name:{show:false},
                            value:{show:false},
                            total:{
                                show:true,
                                showAlways:true,
                                label:'Total Revenue',
                                color:'#6B7C85',
                                fontSize:'11px',
                                fontWeight:600,
                                formatter:function(){ return '₱'+Math.round(total).toLocaleString(undefined,{maximumFractionDigits:0}); }
                            }
                        }
                    }
                }
            },
            tooltip:{
                theme:'dark',
                fillSeriesColor:false,
                style:{fontSize:'12px'},
                y:{
                    formatter:function(v){
                        var pct = total > 0 ? ((Number(v)/total)*100).toFixed(1) : '0';
                        return '₱'+Number(v).toLocaleString(undefined,{minimumFractionDigits:0,maximumFractionDigits:0})+' ('+pct+'%)';
                    }
                }
            },
            legend:{show:false},
            dataLabels:{enabled:false}
        });
    })();
<?php endif; ?>


<?php if (!empty($custom_usage)): ?>
    (function(){
        const prods = <?php echo json_encode(array_map(fn($c) => (string)($c['product'] ?? ''), $custom_usage)); ?>;
        const cust  = <?php echo json_encode(array_map(fn($c) => (int)$c['custom_count'], $custom_usage)); ?>;
        const tmpl  = <?php echo json_encode(array_map(fn($c) => (int)$c['template_count'], $custom_usage)); ?>;
        
        const mount = document.getElementById('ch-custom');
        if (!mount) return;
        
        const totals = prods.map((p, i) => cust[i] + tmpl[i]);
        const maxTotal = Math.max(...totals, 1);
        
        // Calculate overall statistics
        const totalCustom = cust.reduce((a, b) => a + b, 0);
        const totalTemplate = tmpl.reduce((a, b) => a + b, 0);
        const grandTotal = totalCustom + totalTemplate;
        const overallCustomPct = grandTotal > 0 ? Math.round((totalCustom / grandTotal) * 100) : 0;
        const overallTemplatePct = grandTotal > 0 ? Math.round((totalTemplate / grandTotal) * 100) : 0;
        
        let html = '<div style="padding:20px;">';
        
        // Compact legend + insight summary at top
        html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid #e5e7eb;flex-wrap:wrap;gap:12px;">';
        
        // Legend
        html += '<div style="display:flex;gap:20px;font-size:11px;font-weight:600;color:#6b7280;font-family:inherit;">';
        html += '<span><span style="display:inline-block;width:10px;height:10px;background:#00232b;border-radius:2px;margin-right:5px;vertical-align:middle;"></span>Custom Upload</span>';
        html += '<span><span style="display:inline-block;width:10px;height:10px;background:#53C5E0;border-radius:2px;margin-right:5px;vertical-align:middle;"></span>Template / No Upload</span>';
        html += '</div>';
        
        // Smart insight badge
        html += '<div style="font-size:11px;font-weight:700;padding:4px 10px;border-radius:6px;white-space:nowrap;font-family:inherit;">';
        if (overallCustomPct === 0) {
            html += '<span style="color:#0e7490;background:#cffafe;">100% Template Usage</span>';
        } else if (overallCustomPct === 100) {
            html += '<span style="color:#0F4C5C;background:#E5EEF2;">100% Custom Upload</span>';
        } else if (overallCustomPct > 50) {
            html += '<span style="color:#0F4C5C;background:#E5EEF2;">Custom: ' + overallCustomPct + '%</span>';
        } else {
            html += '<span style="color:#0e7490;background:#cffafe;">Template: ' + overallTemplatePct + '%</span>';
        }
        html += '</div>';
        
        html += '</div>';
        
        // Unified layout: ALL labels outside, bars purely visual
        prods.forEach((prod, idx) => {
            const c = cust[idx];
            const t = tmpl[idx];
            const total = c + t;
            const custPct = total > 0 ? Math.round((c / total) * 100) : 0;
            const tmplPct = total > 0 ? Math.round((t / total) * 100) : 0;
            const barWidthPct = total > 0 ? (total / maxTotal) * 100 : 0;
            
            // Determine bar gradient
            let barBg = '';
            if (c > 0 && t > 0) {
                barBg = `linear-gradient(to right, #00232b 0%, #00232b ${custPct}%, #53C5E0 ${custPct}%, #53C5E0 100%)`;
            } else if (c > 0) {
                barBg = '#00232b';
            } else if (t > 0) {
                barBg = '#53C5E0';
            } else {
                barBg = '#e5e7eb';
            }
            
            // Truncate long names
            const displayName = prod.length > 36 ? prod.substring(0, 36) + '...' : prod;
            
            html += '<div style="margin-bottom:14px;" class="pf-cu-row" data-idx="' + idx + '">';
            
            // Labels OUTSIDE (consistent with other dashboard cards)
            html += '<div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px;">';
            html += '<span style="font-size:13px;font-weight:600;color:#374151;font-family:inherit;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:70%;">' + pfEscHtml(displayName) + '</span>';
            html += '<span style="font-size:13px;font-weight:700;color:#111827;font-family:inherit;white-space:nowrap;margin-left:12px;font-variant-numeric:tabular-nums;">' + total.toLocaleString() + '</span>';
            html += '</div>';
            
            // Bar ONLY (purely visual, no text inside)
            html += '<div style="position:relative;width:100%;height:28px;background:#f3f4f6;border-radius:6px;overflow:hidden;border:1px solid #e5e7eb;transition:all 0.2s ease;cursor:pointer;" class="pf-cu-bar">';
            
            if (total > 0) {
                html += '<div style="position:absolute;left:0;top:0;height:100%;width:' + barWidthPct + '%;background:' + barBg + ';transition:width 0.8s cubic-bezier(0.4, 0, 0.2, 1);border-radius:5px;">';
                html += '</div>';
            } else {
                // Empty state indicator
                html += '<span style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);font-size:11px;color:#9ca3af;font-style:italic;font-family:inherit;">No usage</span>';
            }
            
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        
        mount.innerHTML = html;
        
        // Create hover tooltip element
        let tooltip = document.getElementById('pf-cu-tooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.id = 'pf-cu-tooltip';
            tooltip.style.cssText = 'position:fixed;z-index:9999;pointer-events:none;visibility:hidden;opacity:0;background:#1e293b;color:#fff;padding:10px 14px;border-radius:8px;font-size:12px;box-shadow:0 10px 25px rgba(0,0,0,0.2);transition:opacity 0.15s ease,visibility 0.15s ease;border:1px solid #334155;min-width:220px;max-width:320px;font-family:inherit;';
            document.body.appendChild(tooltip);
        }
        
        // Add hover interactions
        const rows = mount.querySelectorAll('.pf-cu-row');
        rows.forEach((row, idx) => {
            const bar = row.querySelector('.pf-cu-bar');
            
            row.addEventListener('mouseenter', function(e) {
                // Highlight bar
                if (bar) {
                    bar.style.transform = 'translateY(-1px)';
                    bar.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
                    bar.style.filter = 'brightness(1.05)';
                }
                
                // Build tooltip content
                const c = cust[idx];
                const t = tmpl[idx];
                const total = c + t;
                const custPct = total > 0 ? ((c / total) * 100).toFixed(1) : '0.0';
                const tmplPct = total > 0 ? ((t / total) * 100).toFixed(1) : '0.0';
                
                let tooltipHTML = '<div style="font-weight:800;color:#f8fafc;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid rgba(255,255,255,0.1);font-family:inherit;">' + pfEscHtml(prods[idx]) + '</div>';
                
                if (total > 0) {
                    tooltipHTML += '<div style="display:flex;justify-content:space-between;margin-bottom:4px;font-family:inherit;">';
                    tooltipHTML += '<span style="color:#cbd5e1;">Custom Upload:</span>';
                    tooltipHTML += '<span style="color:#fff;font-weight:700;">' + c.toLocaleString() + ' <span style="color:#94a3b8;">(' + custPct + '%)</span></span>';
                    tooltipHTML += '</div>';
                    
                    tooltipHTML += '<div style="display:flex;justify-content:space-between;margin-bottom:8px;font-family:inherit;">';
                    tooltipHTML += '<span style="color:#cbd5e1;">Template / No Upload:</span>';
                    tooltipHTML += '<span style="color:#fff;font-weight:700;">' + t.toLocaleString() + ' <span style="color:#94a3b8;">(' + tmplPct + '%)</span></span>';
                    tooltipHTML += '</div>';
                    
                    tooltipHTML += '<div style="padding-top:6px;border-top:1px solid rgba(255,255,255,0.1);display:flex;justify-content:space-between;font-family:inherit;">';
                    tooltipHTML += '<span style="color:#94a3b8;font-size:11px;">Total Units:</span>';
                    tooltipHTML += '<span style="color:#53C5E0;font-weight:800;">' + total.toLocaleString() + '</span>';
                    tooltipHTML += '</div>';
                } else {
                    tooltipHTML += '<div style="color:#94a3b8;font-style:italic;font-size:11px;font-family:inherit;">No customization usage data yet</div>';
                }
                
                tooltip.innerHTML = tooltipHTML;
                tooltip.style.visibility = 'visible';
                tooltip.style.opacity = '1';
            });
            
            row.addEventListener('mousemove', function(e) {
                // Position tooltip near cursor
                let x = e.clientX + 15;
                let y = e.clientY + 15;
                
                const tooltipRect = tooltip.getBoundingClientRect();
                const winW = window.innerWidth;
                const winH = window.innerHeight;
                
                // Keep tooltip in viewport
                if (x + tooltipRect.width > winW) {
                    x = e.clientX - tooltipRect.width - 15;
                }
                if (y + tooltipRect.height > winH) {
                    y = e.clientY - tooltipRect.height - 15;
                }
                
                tooltip.style.left = x + 'px';
                tooltip.style.top = y + 'px';
            });
            
            row.addEventListener('mouseleave', function() {
                // Remove highlight
                if (bar) {
                    bar.style.transform = 'translateY(0)';
                    bar.style.boxShadow = 'none';
                    bar.style.filter = 'brightness(1)';
                }
                
                // Hide tooltip
                tooltip.style.visibility = 'hidden';
                tooltip.style.opacity = '0';
            });
        });
        
        // Remove loading state
        const box = mount.closest('.ch-box');
        if (box) {
            box.classList.remove('pf-chart-loading');
            box.removeAttribute('aria-busy');
            box.classList.add('pf-chart-reveal-done');
        }
    })();
<?php endif; ?>



<?php if (!empty($status_data)): ?>
    (function(){
        const statusColors = {
            'Completed':'#22c55e',
            'Processing':'#3b82f6',
            'Ready for Pickup':'#06b6d4',
            'Pending':'#f59e0b',
            'Pending Review':'#6b7280',
            'Downpayment Submitted':'#8b5cf6',
            'Cancelled':'#ef4444',
            'Design Approved':'#6366f1'
        };
        const labels = <?php echo json_encode(array_map(fn($d) => $d['status'], $status_data)); ?>;
        const vals   = <?php echo json_encode(array_map(fn($d) => (int)$d['cnt'], $status_data)); ?>;
        const total  = vals.reduce(function (a, b) { return a + b; }, 0);
        const colors = labels.map(function (l) { return statusColors[l] || '#94a3b8'; });
        pfPushApexChart(document.getElementById('ch-status'), {
            chart:{...PF_OPT, type:'donut', height:300, animations:{enabled:true, easing:'easeinout', speed:600}},
            series:vals, labels:labels, colors:colors,
            plotOptions:{
                pie:{
                    donut:{
                        size:'62%',
                        labels:{
                            show:true,
                            name:{show:false},
                            value:{show:false},
                            total:{
                                show:true,
                                showAlways:true,
                                label:'Total orders',
                                color:'#64748b',
                                fontSize:'12px',
                                fontWeight:600,
                                formatter:function () { return total.toLocaleString(); }
                            }
                        }
                    }
                }
            },
            legend:{position:'bottom', fontSize:'11px', fontWeight:600, itemMargin:{vertical:4}},
            dataLabels:{enabled:false},
            tooltip:{
                theme:'dark',
                fillSeriesColor:false,
                style:{fontSize:'12px'},
                y:{
                    formatter:function (v, opts) {
                        var n = Number(v) || 0;
                        var pct = total > 0 ? ((n / total) * 100).toFixed(1) : '0';
                        return n.toLocaleString() + ' (' + pct + '%)';
                    }
                }
            }
        });
    })();
<?php endif; ?>

    (function heatmapYearNav() {
        var sel = document.getElementById('pf-heatmap-year');
        if (!sel || sel.dataset.pfReportsBound === '1') return;
        sel.dataset.pfReportsBound = '1';
        var mount = document.getElementById('ch-heatmap-mount');
        var box = document.getElementById('pf-heatmap-chbox');
        var loadEl = document.getElementById('pf-heatmap-ajax-loading');
        var yearChip = document.getElementById('pf-heatmap-year-display');
        
        // Legend toggle functionality
        function initHeatmapLegendToggle() {
            var legend = document.getElementById('pf-heatmap-legend');
            if (!legend || legend.dataset.pfBound === '1') return;
            legend.dataset.pfBound = '1';
            
            var items = legend.querySelectorAll('.pf-hm-legend-item');
            items.forEach(function(item) {
                item.addEventListener('click', function() {
                    var kind = this.getAttribute('data-kind');
                    if (!kind) return;
                    
                    // Toggle hidden state on legend item
                    this.classList.toggle('pf-hm-hidden');
                    var isHidden = this.classList.contains('pf-hm-hidden');
                    
                    // Toggle all cells of this kind
                    var cells = document.querySelectorAll('.pf-hm-cell--' + kind);
                    cells.forEach(function(cell) {
                        if (isHidden) {
                            cell.classList.add('pf-hm-hidden');
                        } else {
                            cell.classList.remove('pf-hm-hidden');
                        }
                    });
                });
                
                // Keyboard support
                item.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.click();
                    }
                });
            });
        }
        
        // Initialize legend toggle on page load
        initHeatmapLegendToggle();
        
        function showLoading(on) {
            if (loadEl) loadEl.classList.toggle('hidden', !on);
            if (box) {
                box.classList.toggle('pf-heatmap-loading', !!on);
                if (on) box.setAttribute('aria-busy', 'true');
                else box.removeAttribute('aria-busy');
            }
        }
        function showHeatmapEmpty(yr, msg) {
            if (!mount) return;
            var text = msg && String(msg).trim() ? String(msg) : 'No data available for selected year';
            mount.innerHTML = '<div class="ch-empty pf-heatmap-empty" role="status"><svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>' + pfEscHtml(text) + '</div>';
        }
        sel.addEventListener('change', function () {
            var year = this.value;
            if (!year) return;
            
            if (yearChip) yearChip.textContent = year;
            showLoading(true);
            
            // Build URL with current branch context
            var url = PF_HEATMAP_API + '?year=' + encodeURIComponent(year);
            var branchId = '<?php echo $branchId === "all" ? "all" : (int)$branchId; ?>';
            if (branchId && branchId !== 'all') {
                url += '&branch_id=' + encodeURIComponent(branchId);
            }
            
            fetch(url, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { 
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json(); 
                })
                .then(function (data) {
                    if (!data || !data.ok) {
                        throw new Error(data && data.message ? data.message : 'Invalid response');
                    }
                    if (!data.yearValid || data.empty || !data.series || !data.series.length) {
                        showHeatmapEmpty(data.year || year, data.message);
                        showLoading(false);
                        return;
                    }
                    if (!mount || typeof window.pfReportsMountHeatmapFromApi !== 'function') {
                        showLoading(false);
                        return;
                    }
                    window.pfReportsMountHeatmapFromApi(mount, data.series, {
                        serverYear: data.serverYear,
                        serverMonth: data.serverMonth,
                        year: data.year
                    });
                    showLoading(false);
                    if (box) box.classList.add('pf-chart-reveal-done');
                    
                    // Re-initialize legend toggle after new content is loaded
                    setTimeout(function() {
                        var legend = document.getElementById('pf-heatmap-legend');
                        if (legend) legend.dataset.pfBound = '';
                        initHeatmapLegendToggle();
                    }, 100);
                })
                .catch(function (err) {
                    console.error('Heatmap fetch error:', err);
                    showHeatmapEmpty(year, 'Failed to load heatmap data. Please try again.');
                    showLoading(false);
                });
        });
    })();

<?php endif; /* !$branch_empty */ ?>

    printflowAttachReportsChartLayoutHooks();
};

window.addEventListener('turbo:before-render', function() {
    if (typeof window.printflowTeardownReportsCharts === 'function') {
        window.printflowTeardownReportsCharts();
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.printflowInitReportsCharts);
} else {
    window.printflowInitReportsCharts();
}
document.addEventListener('printflow:page-init', window.printflowInitReportsCharts);
</script>
