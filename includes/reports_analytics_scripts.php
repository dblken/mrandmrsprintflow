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
var __pfReportsChartRootIds = ['ch-trend','ch-forecast','ch-products','ch-donut','ch-heatmap','ch-locs','ch-custom','ch-branches','ch-status'];
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
    const PF_HEATMAP_ANIM = {
        enabled:true,
        easing:'easeinout',
        speed:2800,
        animateGradually:{enabled:true,delay:52},
        dynamicAnimation:{enabled:true,speed:1300}
    };

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
        const names = <?php
            $names = [];
            $i = 1;
            foreach ($top_products as $p) {
                $trend = '';
                if (!empty($top_products_prev) && isset($p['product_id'])) {
                    $prev = $top_products_prev[(int)$p['product_id']] ?? 0;
                    $curr = (int)$p['qty_sold'];
                    if ($prev > 0) {
                        $chg = round((($curr - $prev) / $prev) * 100);
                        $trend = $chg != 0 ? ($chg > 0 ? "+{$chg}%" : "{$chg}%") : '';
                    }
                }
                $label = '#'.$i.' '.mb_substr($p['product_name'], 0, 20);
                if ($trend) {
                    $label .= ' · '.$trend;
                }
                $names[] = $label;
                $i++;
            }
            echo json_encode($names);
            ?>;
        const qtys  = <?php echo json_encode(array_map(fn($p) => (int)$p['qty_sold'], $top_products)); ?>;
        const barColors = names.map(function(_, i) { return PF_BAR_RANK[Math.min(i, PF_BAR_RANK.length - 1)]; });
        pfPushApexChart(document.getElementById('ch-products'), {
            chart:{...PF_OPT, type:'bar', height:280},
            plotOptions:{bar:{horizontal:true, borderRadius:6, barHeight:'70%', distributed:true}},
            series:[{name:'Units Sold', data:qtys}],
            xaxis:{categories:names, labels:{style:{fontSize:'11px'}}},
            colors: barColors, legend:{show:false},
            dataLabels:{enabled:true, offsetX:4, style:{fontSize:'10px',colors:['#fff']}},
            tooltip:{theme:'dark', fillSeriesColor:false, style:{fontSize:'12px'}, y:{formatter:v=>v+' units'}},
            grid:{borderColor:'#f1f5f9', strokeDashArray:2, xaxis:{lines:{show:true}}}
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

<?php if (!empty($heatmap_products)): ?>
    (function(){
        const series = <?php
            $hm = [];
            foreach ($heatmap_products as $prod => $mo) {
                $row = [];
                for ($m = 1; $m <= 12; $m++) {
                    $row[] = ['x' => date('M', mktime(0, 0, 0, $m, 1)), 'y' => (int)($mo[$m] ?? 0)];
                }
                $hm[] = ['name' => $prod, 'data' => $row];
            }
            echo json_encode($hm);
            ?>;
        pfPushApexChart(document.getElementById('ch-heatmap'), {
            chart:{
                ...PF_OPT,
                type:'heatmap',
                height:<?php echo max(200, count($heatmap_products) * 46 + 50); ?>,
                animations:PF_HEATMAP_ANIM
            },
            series:series, colors:[PF_SECONDARY],
            states:{hover:{filter:{type:'lighten',value:0.06}},normal:{filter:{type:'none',value:0}}},
            plotOptions:{heatmap:{
                enableShades:true,
                shadeIntensity:.72,
                radius:5,
                useFillColorAsStroke:false,
                dataLabels:{useFillColor:true},
                colorScale:{ranges:[
                    {from:0,to:0,   color:'#E5EEF2', name:'None'},
                    {from:1,to:5,   color:'#8ED6E6', name:'Low'},
                    {from:6,to:15,  color:'#3A86A8', name:'Medium'},
                    {from:16,to:999,color:'#00232b', name:'High'}
                ]}
            }},
            dataLabels:{enabled:true, style:{fontSize:'10px'}},
            tooltip:{
                theme:'dark',
                style:{fontSize:'12px'},
                fillSeriesColor:false,
                y:{formatter:function(v){ return v + ' units'; }}
            },
            xaxis:{labels:{style:{fontSize:'10px'}}},
            yaxis:{labels:{style:{fontSize:'10px'}}},
            legend:{position:'bottom', fontSize:'11px'}
        });
    })();
<?php endif; ?>

<?php if (!empty($customer_locations)): ?>
    (function(){
        const cities = <?php echo json_encode(array_map(fn($l) => trim($l['city']), $customer_locations)); ?>;
        const cnts   = <?php echo json_encode(array_map(fn($l) => (int)$l['orders'], $customer_locations)); ?>;
        const locColors = cnts.map(function(_, i) { return PF_BAR_RANK[Math.min(i, PF_BAR_RANK.length - 1)]; });
        pfPushApexChart(document.getElementById('ch-locs'), {
            chart:{...PF_OPT, type:'bar', height:280},
            series:[{name:'Orders', data:cnts}],
            xaxis:{categories:cities, labels:{style:{fontSize:'10px'}, rotate:-30}},
            colors: locColors,
            plotOptions:{bar:{borderRadius:6, columnWidth:'55%', distributed:true}},
            legend:{show:false},
            dataLabels:{enabled:true, style:{fontSize:'10px',colors:['#fff']}},
            tooltip:{theme:'dark', fillSeriesColor:false, style:{fontSize:'12px'}, y:{formatter:function(v){ return v + ' orders'; }}},
            grid:{borderColor:'#f3f4f6'}
        });
    })();
<?php endif; ?>

<?php if (!empty($custom_usage)): ?>
    (function(){
        const prods = <?php echo json_encode(array_map(fn($c) => mb_substr($c['product'], 0, 22), $custom_usage)); ?>;
        const cust  = <?php echo json_encode(array_map(fn($c) => (int)$c['custom_count'], $custom_usage)); ?>;
        const tmpl  = <?php echo json_encode(array_map(fn($c) => (int)$c['template_count'], $custom_usage)); ?>;
        pfPushApexChart(document.getElementById('ch-custom'), {
            chart:{...PF_OPT, type:'bar', height:280, stacked:true},
            series:[{name:'Custom Upload',data:cust},{name:'Template / No Upload',data:tmpl}],
            xaxis:{categories:prods, labels:{style:{fontSize:'10px'}}},
            colors:[PF_PRIMARY,'#8ED6E6'],
            plotOptions:{bar:{horizontal:true, borderRadius:4, barHeight:'60%'}},
            legend:{position:'bottom', fontSize:'11px'},
            tooltip:{theme:'dark', shared:true, intersect:false, fillSeriesColor:false, style:{fontSize:'12px'}},
            grid:{borderColor:'#f3f4f6'}
        });
    })();
<?php endif; ?>

<?php if (count($branch_perf) > 1): ?>
    (function(){
        const branches = <?php echo json_encode(array_map(fn($b) => $b['branch_name'], $branch_perf)); ?>;
        const revs     = <?php echo json_encode(array_map(fn($b) => round((float)$b['revenue'], 2), $branch_perf)); ?>;
        const ords     = <?php echo json_encode(array_map(fn($b) => (int)$b['orders'], $branch_perf)); ?>;
        pfPushApexChart(document.getElementById('ch-branches'), {
            chart:{...PF_OPT, type:'bar', height:270},
            series:[{name:'Revenue (₱)',data:revs},{name:'Orders',data:ords}],
            xaxis:{categories:branches, labels:{style:{fontSize:'11px'}}},
            yaxis:[
                {title:{text:'Revenue',style:{color:'#6B7C85'}}, labels:{formatter:v=>'₱'+v.toLocaleString()}},
                {opposite:true, title:{text:'Orders',style:{color:'#6B7C85'}}, labels:{formatter:v=>Math.round(v)}}
            ],
            colors:[PF_PRIMARY, PF_SECONDARY],
            plotOptions:{bar:{borderRadius:4, columnWidth:'45%'}},
            legend:{position:'top', fontSize:'12px'},
            tooltip:{theme:'dark', shared:true, intersect:false, fillSeriesColor:false, style:{fontSize:'12px'},
                y:[{formatter:v=>'₱'+Number(v).toLocaleString(undefined,{minimumFractionDigits:2})},{formatter:v=>v+' orders'}]},
            grid:{borderColor:'#f3f4f6'}
        });
    })();
<?php endif; ?>

<?php if (!empty($status_data)): ?>
    (function(){
        const statusColors = {
            'Pending':'#F39C12','Processing':'#3498DB','Ready for Pickup':'#53C5E0',
            'Completed':'#2ECC71','Cancelled':'#E74C3C','Design Approved':'#6C5CE7'
        };
        const labels = <?php echo json_encode(array_map(fn($d) => $d['status'], $status_data)); ?>;
        const vals   = <?php echo json_encode(array_map(fn($d) => (int)$d['cnt'], $status_data)); ?>;
        const colors = labels.map(l=>statusColors[l]||'#6B7C85');
        pfPushApexChart(document.getElementById('ch-status'), {
            chart:{...PF_OPT, type:'donut', height:280},
            series:vals, labels:labels, colors:colors,
            plotOptions:{pie:{donut:{size:'60%'}}},
            legend:{position:'bottom', fontSize:'11px', itemMargin:{vertical:3}},
            dataLabels:{enabled:true, formatter:v=>v.toFixed(1)+'%', style:{fontSize:'10px'}},
            tooltip:{theme:'dark', fillSeriesColor:false, style:{fontSize:'12px'}, y:{formatter:v=>v+' orders'}}
        });
    })();
<?php endif; ?>

    (function heatmapYearNav() {
        var sel = document.getElementById('pf-heatmap-year');
        if (!sel || sel.dataset.pfReportsBound === '1') return;
        sel.dataset.pfReportsBound = '1';
        var base = <?php echo json_encode($reports_href_base ?? ''); ?>;
        var qs = <?php echo json_encode(reports_page_query([])); ?>;
        sel.addEventListener('change', function () {
            if (!base) return;
            var p = new URLSearchParams(qs);
            p.set('heatmap_year', this.value);
            var url = base + '?' + p.toString();
            if (window.Turbo && typeof window.Turbo.visit === 'function') {
                window.Turbo.visit(url);
            } else {
                window.location.href = url;
            }
        });
    })();

<?php endif; /* !$branch_empty */ ?>

    printflowAttachReportsChartLayoutHooks();
};
</script>
