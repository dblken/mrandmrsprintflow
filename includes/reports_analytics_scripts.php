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
var __pfReportsChartRootIds = ['ch-forecast','ch-products','ch-donut','ch-locs','ch-custom','ch-status'];
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

    function pfHmValueTier(v) {
        v = Number(v) || 0;
        if (v <= 5) return 'low';
        if (v <= 15) return 'med';
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
                    cell.className = 'pf-hm-cell pf-hm-cell--' + pfHmValueTier(v);
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
            const histData = [...p.hist, null, null, null];
            const foreData = [...new Array(5).fill(null), p.hist[p.hist.length-1], ...p.fore];
            series.push({name: p.name + ' (actual)',   data: histData});
            series.push({name: p.name + ' (forecast)', data: foreData});
            colors.push(cAct, cFore);
            dashes.push(0, 6);
            idx++;
        });

        function pfFcPushForecastChart() {
            var fcMount = document.getElementById('ch-forecast');
            if (!fcMount || !fcMount.parentElement) return;
            var wrap = fcMount.parentElement;
            var h = wrap.clientHeight || wrap.getBoundingClientRect().height;
            if (h < 160) h = 320;
            var fcChartH = Math.max(288, Math.min(480, Math.round(h)));

            pfPushApexChart(fcMount, {
            chart: {...PF_OPT, type:'line', height: fcChartH},
            series: series,
            xaxis: {
                categories: shortCats,
                tickPlacement: 'on',
                labels: {
                    style: { fontSize: '8px' },
                    rotate: -52,
                    rotateAlways: true,
                    trim: false,
                    hideOverlappingLabels: false,
                    offsetX: 2,
                    offsetY: 18,
                    maxHeight: 110
                },
                axisBorder: { show: false },
                axisTicks: { show: true, height: 4, color: '#e5e7eb' }
            },
            yaxis: {
                show: true,
                floating: false,
                labels: {
                    formatter: function (v) { return v != null ? Math.round(v) : ''; },
                    offsetX: -14,
                    padding: 4,
                    style: { fontSize: '10px', colors: '#6b7280' }
                },
                axisBorder: { show: true, color: '#e5e7eb', width: 1, offsetX: 0 },
                axisTicks: { show: true, color: '#e5e7eb', width: 4 }
            },
            colors: colors,
            stroke: {curve:'smooth', width:2, dashArray:dashes},
            markers:{size:2, hover:{size:4}},
            tooltip:{
                theme:'dark', shared:false, intersect:true, fillSeriesColor:false, style:{fontSize:'12px'},
                x: {
                    formatter: function (val, opts) {
                        var i = opts && typeof opts.dataPointIndex === 'number' ? opts.dataPointIndex : -1;
                        if (i >= 0 && allLabels[i] != null) return allLabels[i];
                        return val;
                    }
                },
                y:{formatter:v=>v!=null?v+' orders':'-'}
            },
            annotations:{xaxis:[{x:fcastStart, borderColor:PF_SECONDARY, strokeDashArray:5,
                label:{text:'Forecast', offsetY:-6, offsetX:4, style:{color:'#fff',background:PF_PRIMARY,fontSize:'9px'}}}]},
            legend: { show: false, floating: true },
            grid: {
                borderColor: '#f3f4f6',
                strokeDashArray: 4,
                padding: { left: 76, right: 2, top: 4, bottom: 44 }
            },
            responsive: [
                {
                    breakpoint: 960,
                    options: {
                        chart: { height: 300 },
                        xaxis: {
                            tickPlacement: 'on',
                            labels: {
                                rotate: -56,
                                style: { fontSize: '7px' },
                                offsetX: 0,
                                offsetY: 14,
                                maxHeight: 100,
                                hideOverlappingLabels: false
                            }
                        },
                        yaxis: { labels: { offsetX: -12, padding: 4 } },
                        grid: { padding: { left: 68, right: 2, bottom: 40 } }
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
        const shortNames = fullNames.map(function (nm) {
            var t = String(nm || '').trim();
            return t.length > 24 ? (t.slice(0, 24) + '...') : t;
        });
        const mergeKeys = <?php
            $mk = [];
            foreach ($top_products as $p) {
                $mk[] = !empty($p['product_id']) ? 'p:' . (int) $p['product_id'] : 's:' . mb_strtolower((string) ($p['product_name'] ?? ''));
            }
            echo json_encode($mk);
            ?>;
        const prevMap = <?php echo json_encode($top_products_prev ?? []); ?>;
        const qtys  = <?php echo json_encode(array_map(fn($p) => (int)$p['qty_sold'], $top_products)); ?>;
        var maxQty = 0;
        for (var __i = 0; __i < qtys.length; __i++) maxQty = Math.max(maxQty, Number(qtys[__i]) || 0);
        var xMax = Math.max(100, Math.ceil(maxQty / 100) * 100);
        const names = shortNames.map(function (nm, i) { return '#' + (i + 1) + ' ' + nm; });
        const barColors = names.map(function(_, i) { return PF_BAR_RANK[Math.min(i, PF_BAR_RANK.length - 1)]; });
        const productSeriesData = names.map(function (nm, i) {
            return { x: nm, y: qtys[i] || 0, fillColor: barColors[i] };
        });
        const productsMount = document.getElementById('ch-products');
        var productsWrap = productsMount ? productsMount.closest('.ch-box') : null;
        // Use the actual mount height so the chart occupies the whole card.
        // Avoid forcing a larger height that then gets clipped.
        var productsWrapH = productsWrap
            ? Math.max(260, Math.round(productsWrap.getBoundingClientRect().height || productsWrap.clientHeight || 0))
            : 360;
        pfPushApexChart(productsMount, {
            chart:{
                ...PF_OPT,
                id:'pf-ch-products-bar',
                redrawOnParentResize:true,
                type:'bar',
                height: productsWrapH,
                width: '100%',
                offsetX: 0,
                animations:{enabled:true, easing:'easeinout', speed:520}
            },
            plotOptions:{
                bar:{
                    horizontal:true,
                    borderRadius:4,
                    barHeight:'88%',
                    distributed:true
                }
            },
            series:[{name:'Units Sold', data:productSeriesData}],
            xaxis:{
                min: 0,
                max: xMax,
                tickAmount: 4,
                labels:{
                    style:{fontSize:'10px', fontWeight:600, colors:'#6b7280'},
                    offsetX: 0,
                    formatter:function (v) { return Number(v || 0).toLocaleString(); }
                }
            },
            yaxis:{
                labels:{
                    style:{fontSize:'11px', colors:'#0f172a', fontWeight:700},
                    minWidth: 150,
                    maxWidth: 230,
                    offsetX: 18
                }
            },
            colors: barColors, legend:{show:false},
            dataLabels:{
                enabled:true,
                offsetX: 6,
                style:{fontSize:'10px',colors:['#6b7280']},
                formatter:function (v) { return Number(v || 0).toLocaleString(); }
            },
            tooltip:{
                theme:'dark',
                fillSeriesColor:false,
                style:{fontSize:'12px'},
                custom:function (ctx) {
                    var i = ctx.dataPointIndex;
                    if (i < 0) return '';
                    var nm = fullNames[i] || '';
                    var q = qtys[i];
                    var k = mergeKeys[i];
                    var prev = prevMap[k];
                    var trend = '';
                    if (typeof prev === 'number' && prev > 0) {
                        var chg = Math.round(((q - prev) / prev) * 100);
                        if (chg !== 0) trend = (chg > 0 ? '+' : '') + chg + '% vs prior month';
                    }
                    return '<div class="pf-bar-tip" style="padding:8px 10px;">' +
                        '<div style="font-weight:700;color:#e2e8f0;">' + pfEscHtml(nm) + '</div>' +
                        '<div style="color:#53C5E0;font-weight:700;margin-top:4px;">' + q.toLocaleString() + ' units</div>' +
                        (trend ? '<div style="color:#94a3b8;font-size:11px;margin-top:4px;">' + pfEscHtml(trend) + '</div>' : '') +
                        '</div>';
                }
            },
            grid:{
                borderColor:'#f1f5f9',
                strokeDashArray:2,
                padding:{left:52,right:8,top:6,bottom:8},
                xaxis:{lines:{show:true}},
                yaxis:{lines:{show:true}}
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
        const chH = Math.max(300, prods.length * 52);
        pfPushApexChart(document.getElementById('ch-custom'), {
            chart:{
                ...PF_OPT,
                type:'bar',
                height:chH,
                stacked:true,
                selection:{enabled:false},
                zoom:{enabled:false},
                offsetX: 0,
                offsetY: 6
            },
            series:[{name:'Custom Upload',data:cust},{name:'Template / No Upload',data:tmpl}],
            colors:[PF_PRIMARY, PF_SECONDARY],
            plotOptions:{
                bar:{
                    horizontal:true,
                    borderRadius:5,
                    barHeight:'72%',
                    dataLabels:{
                        position:'center',
                        hideOverflowingLabels:false
                    }
                }
            },
            states:{hover:{filter:{type:'none',value:0}},active:{filter:{type:'none',value:0}}},
            xaxis:{
                categories:prods,
                labels:{
                    style:{fontSize:'11px',fontWeight:600,colors:'#0f172a'},
                    trim:true,
                    maxHeight:200,
                    offsetY:2
                },
                axisBorder:{show:true, color:'#e2e8f0'},
                axisTicks:{show:true, color:'#e2e8f0'}
            },
            yaxis:{
                min:0,
                forceNiceScale:true,
                tickAmount:6,
                decimalsInFloat:0,
                title:{text:'Units', style:{color:'#64748b', fontSize:'11px', fontWeight:700}, offsetX:-4},
                labels:{
                    style:{fontSize:'11px',colors:'#64748b',fontWeight:600},
                    formatter:function (v) { return Math.round(v).toLocaleString(); },
                    offsetX:-6
                }
            },
            dataLabels:{
                enabled:true,
                textAnchor:'middle',
                formatter:function (val) {
                    var n = Math.round(Number(val) || 0);
                    return n > 0 ? String(n) : '';
                },
                style:{fontSize:'10px',fontWeight:800,colors:['#fff','#0f172a']},
                dropShadow:{enabled:false}
            },
            legend:{position:'bottom', fontSize:'11px', fontWeight:600, offsetY:6},
            tooltip:{
                theme:'dark',
                shared:true,
                intersect:false,
                fillSeriesColor:false,
                style:{fontSize:'12px'},
                custom:function (ctx) {
                    var i = ctx.dataPointIndex;
                    if (i < 0 || !prods[i]) return '';
                    var c = cust[i], t = tmpl[i], tot = c + t;
                    var pct = function (n) { return tot > 0 ? ((n / tot) * 100).toFixed(1) : '0'; };
                    return '<div class="pf-cu-tip" style="padding:10px 12px;min-width:200px;">' +
                        '<div style="font-weight:800;color:#f8fafc;margin-bottom:8px;">' + pfEscHtml(prods[i]) + '</div>' +
                        '<div style="color:#e2e8f0;">Custom Upload: <strong style="color:#fff;">' + c.toLocaleString() + '</strong> <span style="color:#94a3b8;">(' + pct(c) + '%)</span></div>' +
                        '<div style="color:#e2e8f0;margin-top:4px;">Template / No Upload: <strong style="color:#fff;">' + t.toLocaleString() + '</strong> <span style="color:#94a3b8;">(' + pct(t) + '%)</span></div>' +
                        '<div style="margin-top:8px;padding-top:8px;border-top:1px solid #334155;color:#94a3b8;font-size:11px;">Total units: <strong style="color:#53C5E0;">' + tot.toLocaleString() + '</strong></div></div>';
                }
            },
            grid:{
                borderColor:'#e8ecf1',
                strokeDashArray:4,
                padding:{top:12,right:-24,bottom:10,left:4},
                xaxis:{lines:{show:true}},
                yaxis:{lines:{show:false}}
            }
        });
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
            if (yearChip) yearChip.textContent = year;
            showLoading(true);
            fetch(PF_HEATMAP_API + '?year=' + encodeURIComponent(year), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) throw new Error('heatmap');
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
                })
                .catch(function () {
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
