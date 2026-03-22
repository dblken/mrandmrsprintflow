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
var __pfReportsChartRootIds = ['ch-trend','ch-forecast','ch-products','ch-donut','ch-locs','ch-custom','ch-status'];
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
    entry.rendered = true;
    var host = entry.host;
    var el = entry.el;
    var options = pfNormalizeApexChartOptions(entry.options);
    var run = function () {
        var ch = new ApexCharts(el, options);
        window.__pfReportsApexCharts.push(ch);
        var done = function () {
            if (host) {
                host.classList.remove('pf-chart-loading');
                host.removeAttribute('aria-busy');
                host.classList.add('pf-chart-reveal-done');
            }
            requestAnimationFrame(function () {
                try {
                    if (ch && typeof ch.resize === 'function') ch.resize();
                } catch (e) {}
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
            if (host.getAttribute('data-pf-chart-revealed') === '1') return;
            host.setAttribute('data-pf-chart-revealed', '1');
            var item = pendingByHost.get(host);
            if (item && !item.rendered) {
                pfExecuteApexReveal(item, local * 135);
                local += 1;
            }
        });
    }, { root: scrollRoot || null, threshold: 0, rootMargin: '0px 0px -6% 0px' });
    pendingByHost.forEach(function (item, host) {
        window.__pfReportsRevealIO.observe(host);
    });
}
function pfPushApexChart(el, options) {
    if (!el) return;
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
        const fcast    = <?php echo json_encode($next_month_label); ?>;
        const curMon   = <?php echo json_encode($trend_annotation_current_month ?? ''); ?>;
<?php if ($trend_metric === 'revenue'): ?>
        const trendSeries = [{ name:'Revenue (₱)', data: <?php echo json_encode(array_merge($trend12_revenues, [$forecast_revenue])); ?>, type:'area' }];
        const trendColors = [PF_PRIMARY];
        const trendYAxisFmt = function (v) { return '₱' + Number(v).toLocaleString(); };
        const trendTipFmt = function (v) { return '₱' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 0 }); };
        const trendForeStroke = PF_SECONDARY;
<?php else: ?>
        const trendSeries = [{ name:'Orders', data: <?php echo json_encode(array_merge($trend12_orders, [$forecast_orders])); ?>, type:'area' }];
        const trendColors = ['#0F4C5C'];
        const trendYAxisFmt = function (v) { return Math.round(v); };
        const trendTipFmt = function (v) { return Math.round(v) + ' orders'; };
        const trendForeStroke = '#3A86A8';
<?php endif; ?>

        pfPushApexChart(document.getElementById('ch-trend'), {
            chart: {
                ...PF_OPT,
                id:'pf-reports-trend-<?php echo htmlspecialchars($trend_metric, ENT_QUOTES, 'UTF-8'); ?>',
                type:'area',
                height:300,
                zoom:{enabled:false},
                selection:{enabled:false},
                toolbar:{show:false, tools:{download:false, selection:false, zoom:false, zoomin:false, zoomout:false, pan:false, reset:false}}
            },
            series: trendSeries,
            xaxis:{
                categories:labels,
                labels:{rotate:-30, style:{fontSize:'11px'}},
                crosshairs:{show:true, position:'front', stroke:{color:'#001018', width:2, dashArray:0}}
            },
            yaxis:{labels:{formatter:trendYAxisFmt}},
            colors: trendColors,
            fill:{type:'gradient', gradient:{shadeIntensity:.55, opacityFrom:.32, opacityTo:.04}},
            stroke:{curve:'smooth', width:3},
            markers:{size:0, hover:{size:7, fillColor:'#00232b', strokeColor:'#53C5E0', strokeWidth:3}},
            dataLabels:{enabled:false},
            tooltip:{
                theme:'dark',
                shared:true,
                intersect:false,
                style:{fontSize:'12px'},
                fillSeriesColor:false,
                y:{formatter:trendTipFmt}
            },
            annotations:{xaxis:[].concat(
                curMon ? [{x:curMon, borderColor:'#6B7C85', strokeDashArray:4, label:{text:'Current Month',style:{color:'#374151',background:'#E5EEF2',fontSize:'10px'}}}] : [],
                [{x:fcast, borderColor:trendForeStroke, strokeDashArray:5, label:{text:'Forecast',style:{color:'#fff',background:PF_PRIMARY,fontSize:'10px'}}}]
            )},
            legend:{show:false},
            grid:{borderColor:'#f1f5f9', strokeDashArray:2, xaxis:{lines:{show:true}}, yaxis:{lines:{show:true}}}
        });
    })();

<?php if ($can_forecast && !empty($fc_series_data)): ?>
    (function(){
        const allLabels = <?php echo json_encode($fc_all_labels); ?>;
        const fcastStart = <?php echo json_encode(reset($fc_fore_labels)); ?>;
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

        pfPushApexChart(document.getElementById('ch-forecast'), {
            chart: {...PF_OPT, type:'line', height:290},
            series: series,
            xaxis: {categories: allLabels, labels:{style:{fontSize:'10px'}, rotate:-30}},
            yaxis: {labels:{formatter:v=>v!=null?Math.round(v):''}},
            colors: colors,
            stroke: {curve:'smooth', width:2, dashArray:dashes},
            markers:{size:2, hover:{size:4}},
            tooltip:{theme:'dark', shared:false, intersect:true, fillSeriesColor:false, style:{fontSize:'12px'}, y:{formatter:v=>v!=null?v+' orders':'-'}},
            annotations:{xaxis:[{x:fcastStart, borderColor:PF_SECONDARY, strokeDashArray:5,
                label:{text:'Forecast →',style:{color:'#fff',background:PF_PRIMARY,fontSize:'10px'}}}]},
            legend:{show:false},
            grid:{borderColor:'#f3f4f6',strokeDashArray:4}
        });
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
        const names = fullNames.map(function (nm, i) { return '#' + (i + 1) + ' ' + nm; });
        const barH = Math.max(360, names.length * 44);
        const barColors = names.map(function(_, i) { return PF_BAR_RANK[Math.min(i, PF_BAR_RANK.length - 1)]; });
        pfPushApexChart(document.getElementById('ch-products'), {
            chart:{...PF_OPT, type:'bar', height:barH, animations:{enabled:true, easing:'easeinout', speed:520}},
            plotOptions:{bar:{horizontal:true, borderRadius:8, barHeight:'68%', distributed:true}},
            series:[{name:'Units Sold', data:qtys}],
            xaxis:{
                categories:names,
                labels:{
                    style:{fontSize:'11px', fontWeight:600, colors:'#0f172a'},
                    maxHeight: 280,
                    trim: false
                }
            },
            colors: barColors, legend:{show:false},
            dataLabels:{enabled:true, offsetX:6, style:{fontSize:'10px',colors:['#fff']}, formatter:function (v) { return v; }},
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
            grid:{borderColor:'#f1f5f9', strokeDashArray:2, padding:{left:8, right:12}, xaxis:{lines:{show:true}}}
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
                offsetX:44,
                offsetY:6
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
                padding:{top:12,right:24,bottom:10,left:4},
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
