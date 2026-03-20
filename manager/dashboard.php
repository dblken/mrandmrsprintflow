<?php
/**
 * Manager Dashboard - PrintFlow
 * Branch-filtered dashboard for Branch Managers.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';
require_once __DIR__ . '/../includes/branch_ui.php';

// Only Managers allowed here
require_role('Manager');

// ── Branch Context (Manager is always locked to their branch) ────
$branchCtx = init_branch_context(false);
$branchId  = $branchCtx['selected_branch_id']; // always an int for Manager

// Build reusable branch SQL parts
$bTypes = ''; $bParams = [];
$bSql = branch_where('o', $branchId, $bTypes, $bParams);

// ── KPI: Total Customers (branch-filtered via orders) ─────────
try {
    [$bSqlFrag, $bT, $bP] = branch_where_parts('o', $branchId);
    $total_customers = db_query(
        "SELECT COUNT(DISTINCT o.customer_id) as cnt FROM orders o WHERE 1=1" . $bSqlFrag,
        $bT ?: null, $bP ?: null
    )[0]['cnt'] ?? 0;
} catch (Exception $e) { $total_customers = 0; }

// ── KPI: Total Revenue (branch-filtered) ──────────────────────
try {
    [$bSqlFrag, $bT2, $bP2] = branch_where_parts('o', $branchId);
    $rev_sql    = "SELECT COALESCE(SUM(o.total_amount),0) as total FROM orders o WHERE o.payment_status = 'Paid'" . $bSqlFrag;
    $total_revenue = db_query($rev_sql, $bT2 ?: null, $bP2 ?: null)[0]['total'] ?? 0;
} catch (Exception $e) { $total_revenue = 0; }

// ── KPI: Total Orders (branch-filtered) ───────────────────────
try {
    [$bSqlFrag, $bT3, $bP3] = branch_where_parts('o', $branchId);
    $ord_sql = "SELECT COUNT(*) as cnt FROM orders o WHERE 1=1" . $bSqlFrag;
    $total_orders = db_query($ord_sql, $bT3 ?: null, $bP3 ?: null)[0]['cnt'] ?? 0;
} catch (Exception $e) { $total_orders = 0; }

// ── KPI: Pending Orders (branch-filtered) ────────────────────
try {
    [$bSqlFrag, $bT4, $bP4] = branch_where_parts('o', $branchId);
    $pending_orders = db_query(
        "SELECT COUNT(*) as cnt FROM orders o WHERE o.status = 'Pending'" . $bSqlFrag,
        $bT4 ?: null, $bP4 ?: null
    )[0]['cnt'] ?? 0;
} catch (Exception $e) { $pending_orders = 0; }

// ── Sales Revenue (Last 30 days, branch-filtered) ─────────────
try {
    [$bSqlFrag, $bT5, $bP5] = branch_where_parts('o', $branchId);
    $daily_sales = db_query(
        "SELECT DATE(o.order_date) as day, SUM(o.total_amount) as revenue, COUNT(*) as orders
         FROM orders o WHERE o.payment_status='Paid' AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         {$bSqlFrag}
         GROUP BY DATE(o.order_date) ORDER BY day",
        $bT5 ?: null, $bP5 ?: null
    ) ?: [];
} catch (Exception $e) { $daily_sales = []; }

// ── Order Status Breakdown (branch-filtered) ──────────────────
try {
    [$bSqlFrag, $bT6, $bP6] = branch_where_parts('o', $branchId);
    $order_status = db_query(
        "SELECT o.status, COUNT(*) as cnt FROM orders o WHERE 1=1 {$bSqlFrag} GROUP BY o.status",
        $bT6 ?: null, $bP6 ?: null
    ) ?: [];
} catch (Exception $e) { $order_status = []; }

$statusColors = [
    'Pending'          => '#f59e0b',
    'Processing'       => '#3b82f6',
    'Ready for Pickup' => '#06b6d4',
    'Completed'        => '#10b981',
    'Cancelled'        => '#ef4444'
];

// ── Recent Orders (last 5, branch-filtered) ──────────────────
try {
    [$bSqlFrag, $bT7, $bP7] = branch_where_parts('o', $branchId);
    $recent_orders = db_query(
        "SELECT o.order_id, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                o.order_date, o.total_amount, o.payment_status, o.status, b.branch_name
         FROM orders o
         LEFT JOIN customers c ON o.customer_id = c.customer_id
         LEFT JOIN branches b  ON o.branch_id  = b.id
         WHERE 1=1 {$bSqlFrag}
         ORDER BY o.order_date DESC LIMIT 5",
        $bT7 ?: null, $bP7 ?: null
    ) ?: [];
} catch (Exception $e) { $recent_orders = []; }

// ── Low Stock Alerts ──────────────────────────────────────────
try {
    require_once __DIR__ . '/../includes/InventoryManager.php';
    $all_items = db_query(
        "SELECT i.id, i.name as material_name, i.reorder_level as low_limit, i.unit_of_measure as unit,
                ic.name as category_name
         FROM inv_items i
         LEFT JOIN inv_categories ic ON i.category_id = ic.id
         WHERE i.status = 'ACTIVE' AND i.reorder_level > 0"
    ) ?: [];
    $low_stock = [];
    foreach ($all_items as $item) {
        $soh = InventoryManager::getStockOnHand($item['id']);
        if ($soh <= $item['low_limit']) {
            $item['current_stock'] = $soh;
            $item['ratio'] = $soh / $item['low_limit'];
            $low_stock[] = $item;
        }
    }
    usort($low_stock, fn($a, $b) => $a['ratio'] <=> $b['ratio']);
    $low_stock = array_slice($low_stock, 0, 5);
} catch (Exception $e) { $low_stock = []; }

$page_title = 'Dashboard - Manager | PrintFlow';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <script src="/printflow/public/assets/js/alpine.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <?php render_branch_css(); ?>
    <style>
        /* KPI Row */
        .kpi-row { display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:24px; }
        @media (max-width:768px) { .kpi-row { grid-template-columns:repeat(2, 1fr); } }
        .kpi-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px 20px; position:relative; overflow:hidden; }
        .kpi-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .kpi-card.indigo::before { background:linear-gradient(90deg,#6366f1,#818cf8); }
        .kpi-card.emerald::before { background:linear-gradient(90deg,#059669,#34d399); }
        .kpi-card.amber::before { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
        .kpi-card.rose::before { background:linear-gradient(90deg,#e11d48,#fb7185); }
        .kpi-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#9ca3af; margin-bottom:6px; }
        .kpi-value { font-size:26px; font-weight:800; color:#1f2937; font-variant-numeric:tabular-nums; }
        .kpi-sub { font-size:12px; color:#6b7280; margin-top:4px; }
        .dash-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
        @media (max-width:1024px) { .dash-grid { grid-template-columns:1fr; } }
        .dash-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px; }
        .dash-card-title { font-size:15px; font-weight:700; color:#1f2937; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
        .dash-card-title svg { width:18px; height:18px; color:#6366f1; }
        .dash-full { grid-column: 1 / -1; }
        .mini-table { width:100%; border-collapse:collapse; font-size:13px; }
        .mini-table th { text-align:left; padding:8px 10px; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.3px; color:#9ca3af; border-bottom:1px solid #f3f4f6; }
        .mini-table td { padding:8px 10px; border-bottom:1px solid #f9fafb; }
        .mini-table tr:hover { background:#f9fafb; }
        .chart-wrap { position:relative; height:250px; }
        .chart-loading { position:absolute; inset:0; background:rgba(255,255,255,.9); display:flex; align-items:center; justify-content:center; z-index:2; border-radius:8px; }
        .chart-loading.hidden { display:none; }
        .chart-loading-spinner { width:28px; height:28px; border:3px solid #e5e7eb; border-top-color:#6366f1; border-radius:50%; animation:chart-spin .7s linear infinite; }
        @keyframes chart-spin { to { transform:rotate(360deg); } }
        .chart-nodata { position:absolute; inset:0; display:none; align-items:center; justify-content:center; flex-direction:column; gap:8px; color:#9ca3af; font-size:13px; z-index:1; }
        .chart-nodata.visible { display:flex; }
        .chart-select { padding:6px 10px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; font-weight:600; background:#fff; color:#374151; }
        .chart-header-row { justify-content:space-between; align-items:center; flex-wrap:nowrap; gap:12px; margin-bottom:14px; }
        .chart-title-nowrap { white-space:nowrap; flex-shrink:0; display:flex; align-items:center; gap:8px; }
        .chart-filters { display:flex; flex-wrap:nowrap; align-items:center; gap:10px; flex-shrink:0; }
        .chart-filter-label { font-size:12px; font-weight:600; color:#6b7280; white-space:nowrap; }
        .badge { display:inline-block; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:600; }
        .badge-green { background:#d1fae5; color:#065f46; }
        .badge-yellow { background:#fef3c7; color:#92400e; }
        .badge-blue { background:#dbeafe; color:#1e40af; }
        .badge-red { background:#fee2e2; color:#991b1b; }
        .badge-gray { background:#f3f4f6; color:#374151; }
        .stock-bar { height:6px; background:#f3f4f6; border-radius:3px; overflow:hidden; width:80px; }
        .stock-bar-fill { height:100%; border-radius:3px; }
        .stock-bar-fill.danger { background:#ef4444; }
        .stock-bar-fill.warning { background:#f59e0b; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/manager_sidebar.php'; ?>

    <div class="main-content">
        <header>
            <h1 class="page-title">Dashboard</h1>
            <?php render_branch_selector($branchCtx); ?>
        </header>

        <main>
            <!-- Branch context banner -->
            <?php render_branch_context_banner($branchCtx['branch_name']); ?>

            <!-- KPI Summary Row -->
            <div class="kpi-row">
                <div class="kpi-card indigo">
                    <div class="kpi-label">Branch Customers</div>
                    <div class="kpi-value"><?php echo number_format($total_customers); ?></div>
                    <div class="kpi-sub">Distinct customers</div>
                </div>
                <div class="kpi-card emerald">
                    <div class="kpi-label">Branch Revenue</div>
                    <div class="kpi-value">₱<?php echo number_format((float)$total_revenue, 2); ?></div>
                    <div class="kpi-sub">From paid orders</div>
                </div>
                <div class="kpi-card amber">
                    <div class="kpi-label">Total Orders</div>
                    <div class="kpi-value"><?php echo number_format($total_orders); ?></div>
                    <div class="kpi-sub">This branch</div>
                </div>
                <div class="kpi-card rose">
                    <div class="kpi-label">Pending Orders</div>
                    <div class="kpi-value"><?php echo number_format($pending_orders); ?></div>
                    <div class="kpi-sub">Awaiting processing</div>
                </div>
            </div>

            <!-- Sales Chart + Order Status -->
            <div class="dash-grid">
                <!-- Sales Revenue -->
                <div class="dash-card">
                    <div class="dash-card-title chart-header-row">
                        <span class="chart-title-nowrap">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px;color:#6366f1;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            Branch Revenue
                        </span>
                        <div class="chart-filters">
                            <label class="chart-filter-label">Period</label>
                            <select id="dash-chart-period" class="chart-select">
                                <option value="today">Today</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="6months">Last 6 Months</option>
                                <option value="yearly">Yearly</option>
                            </select>
                            <span id="dash-year-month" style="display:flex; gap:8px; align-items:center;">
                                <select id="dash-chart-month" class="chart-select" style="display:none;">
                                    <?php foreach (['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $i => $m): ?>
                                    <option value="<?php echo $i+1; ?>" <?php echo ($i+1)==date('n')?'selected':''; ?>><?php echo $m; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select id="dash-chart-year" class="chart-select">
                                    <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y==date('Y')?'selected':''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </span>
                        </div>
                    </div>
                    <div class="chart-wrap" id="dash-sales-chart-wrap">
                        <div class="chart-loading hidden" id="dash-sales-loading">
                            <div class="chart-loading-spinner"></div>
                        </div>
                        <div class="chart-nodata" id="dash-sales-nodata">
                            <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24" opacity="0.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            <span>No sales data for this period</span>
                        </div>
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Order Status Breakdown -->
                <div class="dash-card">
                    <div class="dash-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Order Status Breakdown
                    </div>
                    <div class="chart-wrap"><canvas id="statusChart"></canvas></div>
                </div>
            </div>

            <!-- Recent Orders + Low Stock -->
            <div class="dash-grid">
                <!-- Recent Orders -->
                <div class="dash-card">
                    <div class="dash-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Recent Orders
                    </div>
                    <?php if (!empty($recent_orders)): ?>
                    <table class="mini-table">
                        <thead><tr><th>ID</th><th>Customer</th><th>Status</th><th style="text-align:right;">Amount</th></tr></thead>
                        <tbody>
                            <?php foreach ($recent_orders as $ro):
                                $sBadge = match($ro['status']) {
                                    'Completed'        => 'badge-green',
                                    'Processing'       => 'badge-blue',
                                    'Pending'          => 'badge-yellow',
                                    'Ready for Pickup' => 'badge-blue',
                                    'Cancelled'        => 'badge-red',
                                    default            => 'badge-gray'
                                };
                            ?>
                            <tr>
                                <td style="font-weight:700; color:#6366f1;"><?php echo $ro['order_id']; ?></td>
                                <td style="font-weight:500;"><?php echo htmlspecialchars($ro['customer_name'] ?? 'N/A'); ?></td>
                                <td><span class="badge <?php echo $sBadge; ?>"><?php echo $ro['status']; ?></span></td>
                                <td style="text-align:right; font-weight:700;">₱<?php echo number_format((float)$ro['total_amount'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align:center; color:#9ca3af; padding:40px 0; font-size:13px;">No orders yet</div>
                    <?php endif; ?>
                </div>

                <!-- Low Stock Alerts -->
                <div class="dash-card">
                    <div class="dash-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        Low Stock Alerts
                    </div>
                    <?php if (!empty($low_stock)): ?>
                    <table class="mini-table">
                        <thead><tr><th>Material</th><th>Stock</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($low_stock as $ls):
                                $stock = (float)$ls['current_stock'];
                                $limit = (float)$ls['low_limit'];
                                $pct   = $limit > 0 ? ($stock / $limit) * 100 : 0;
                                $barClass = $stock <= 0 ? 'danger' : 'warning';
                            ?>
                            <tr>
                                <td style="font-weight:600;">
                                    <?php echo mb_strlen($ls['material_name']) > 15 ? htmlspecialchars(mb_substr($ls['material_name'], 0, 15)) . '...' : htmlspecialchars($ls['material_name']); ?>
                                    <div style="font-size:10px; color:#9ca3af;"><?php echo htmlspecialchars($ls['category_name'] ?: 'General'); ?></div>
                                </td>
                                <td style="color:<?php echo $stock <= 0 ? '#ef4444' : '#d97706'; ?>; font-weight:700; white-space:nowrap;">
                                    <?php echo number_format($stock, 1); ?> <small><?php echo htmlspecialchars($ls['unit']); ?></small>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <div class="stock-bar" style="width:50px;"><div class="stock-bar-fill <?php echo $barClass; ?>" style="width:<?php echo min(100, max($pct, 10)); ?>%;"></div></div>
                                        <span style="font-size:10px; font-weight:700; color:#ef4444;">LOW</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align:center; color:#059669; padding:40px 0; font-size:13px;">
                        <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 6px; display:block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        All stock levels are healthy!
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
const DASH_BRANCH_ID = <?php echo $branchId !== 'all' ? (int)$branchId : 'null'; ?>;

const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(salesCtx, {
    type: 'line',
    data: { labels: [], datasets: [
        {
            label: 'Revenue (₱)', data: [],
            borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.08)',
            borderWidth: 2.5, fill: true, tension: 0.35,
            pointBackgroundColor: '#6366f1', pointRadius: 3, pointHoverRadius: 5, yAxisID: 'y'
        },
        {
            label: 'Orders', data: [],
            borderColor: '#10b981', backgroundColor: 'transparent',
            borderWidth: 2, borderDash: [5,3], tension: 0.35,
            pointRadius: 2, pointHoverRadius: 4, yAxisID: 'y1'
        }
    ]},
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 12, font: { size: 11 } } } },
        scales: {
            y:  { beginAtZero: true, ticks: { font: { size: 11 }, callback: v => '₱' + v.toLocaleString() }, grid: { color: '#f3f4f6' } },
            y1: { beginAtZero: true, position: 'right', ticks: { font: { size: 11 }, precision: 0 }, grid: { display: false } },
            x:  { ticks: { font: { size: 10 }, maxRotation: 45 }, grid: { display: false } }
        }
    }
});

async function loadSalesChart(period) {
    const loadingEl = document.getElementById('dash-sales-loading');
    const noDataEl  = document.getElementById('dash-sales-nodata');
    const yearEl    = document.getElementById('dash-chart-year');
    const monthEl   = document.getElementById('dash-chart-month');
    if (loadingEl) loadingEl.classList.remove('hidden');
    if (noDataEl)  noDataEl.classList.remove('visible');
    const year  = yearEl  ? yearEl.value  : new Date().getFullYear();
    const month = monthEl ? monthEl.value : new Date().getMonth() + 1;
    let url = '/printflow/admin/api_revenue_chart.php?period=' + period + '&year=' + year;
    if (period === 'monthly') url += '&month=' + month;
    if (DASH_BRANCH_ID) url += '&branch_id=' + DASH_BRANCH_ID;
    try {
        const resp = await fetch(url, { credentials: 'same-origin' });
        const text = await resp.text();
        let data;
        try { data = JSON.parse(text); } catch(e) {
            if (noDataEl) { noDataEl.querySelector('span').textContent = 'Failed to load chart data'; noDataEl.classList.add('visible'); }
            return;
        }
        salesChart.data.labels           = data.labels  || [];
        salesChart.data.datasets[0].data = data.revenue || [];
        salesChart.data.datasets[1].data = data.orders  || [];
        salesChart.update();
        if (noDataEl) noDataEl.classList.toggle('visible', (data.labels || []).length === 0);
    } catch(e) {
        if (noDataEl) { noDataEl.querySelector('span').textContent = 'Failed to load chart data'; noDataEl.classList.add('visible'); }
    } finally {
        if (loadingEl) loadingEl.classList.add('hidden');
    }
}

function updateChartYearMonthVisibility(period) {
    const wrap    = document.getElementById('dash-year-month');
    const monthEl = document.getElementById('dash-chart-month');
    if (!wrap) return;
    wrap.style.display  = ['monthly','6months','yearly'].includes(period) ? 'flex' : 'none';
    if (monthEl) monthEl.style.display = period === 'monthly' ? 'inline-block' : 'none';
}

document.getElementById('dash-chart-period')?.addEventListener('change', () => {
    const period = document.getElementById('dash-chart-period').value;
    updateChartYearMonthVisibility(period);
    loadSalesChart(period);
});
document.getElementById('dash-chart-year')?.addEventListener('change',  () => loadSalesChart(document.getElementById('dash-chart-period').value));
document.getElementById('dash-chart-month')?.addEventListener('change', () => loadSalesChart(document.getElementById('dash-chart-period').value));
updateChartYearMonthVisibility('monthly');
loadSalesChart('monthly');

<?php if (!empty($order_status)): ?>
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(fn($d) => $d['status'], $order_status)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_map(fn($d) => (int)$d['cnt'], $order_status)); ?>,
            backgroundColor: <?php echo json_encode(array_map(fn($d) => $statusColors[$d['status']] ?? '#6b7280', $order_status)); ?>,
            borderWidth: 2, borderColor: '#fff', hoverOffset: 6
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, cutout: '60%',
        plugins: { legend: { position: 'bottom', labels: { padding: 16, boxWidth: 12, font: { size: 12 } } } }
    }
});
<?php endif; ?>
</script>

</body>
</html>
