<?php
/**
 * Admin Reports & Analytics Page
 * PrintFlow - Printing Shop PWA
 * Professional reports with real data, charts, and print support
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';
require_once __DIR__ . '/../includes/branch_ui.php';

require_role(['Admin', 'Manager']);

$current_user = get_logged_in_user();

// Branch context (analytics mode — allow All)
$branchCtx = init_branch_context(false);
$branchId  = $branchCtx['selected_branch_id'];

// ── Date range ─────────────────────────────────────────
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');
$from = date('Y-m-d', strtotime($from));
$to   = date('Y-m-d', strtotime($to));

// Branch WHERE fragment for orders table
[$bSql, $bTypes, $bParams] = branch_where_parts('o', $branchId);

// ── Sales Summary ──────────────────────────────────────
try {
    [$bs2, $bt2, $bp2] = branch_where_parts('o', $branchId);
    $salesSql = "SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN o.payment_status='Paid' THEN o.total_amount ELSE 0 END) as revenue,
            SUM(CASE WHEN o.payment_status='Paid' THEN 1 ELSE 0 END) as paid,
            AVG(CASE WHEN o.payment_status='Paid' THEN o.total_amount ELSE NULL END) as avg_val
         FROM orders o WHERE o.order_date BETWEEN ? AND ?$bs2";
    $sales = db_query($salesSql, 'ss' . $bt2, array_merge([$from, $to . ' 23:59:59'], $bp2));
    $s = $sales[0] ?? [];
} catch (Exception $e) { $s = []; }

$total_orders = (int)($s['total_orders'] ?? 0);
$revenue      = (float)($s['revenue'] ?? 0);
$paid_orders  = (int)($s['paid'] ?? 0);
$avg_val      = (float)($s['avg_val'] ?? 0);

// ── Order Status Breakdown ─────────────────────────────
try {
    [$bsS, $btS, $bpS] = branch_where_parts('o', $branchId);
    $status_data = db_query(
        "SELECT o.status, COUNT(*) as cnt FROM orders o
         WHERE o.order_date BETWEEN ? AND ?$bsS GROUP BY o.status",
        'ss' . $btS, array_merge([$from, $to . ' 23:59:59'], $bpS)
    ) ?: [];
} catch (Exception $e) { $status_data = []; }

// Status color mapping (used by chart)
$statusColors = [
    'Pending' => '#f59e0b',
    'Processing' => '#3b82f6',
    'Ready for Pickup' => '#06b6d4',
    'Completed' => '#10b981',
    'Cancelled' => '#ef4444'
];

// ── Daily Revenue (for chart) ──────────────────────────
try {
    [$bsD, $btD, $bpD] = branch_where_parts('o', $branchId);
    $daily_rev = db_query(
        "SELECT DATE(o.order_date) as day, SUM(o.total_amount) as total, COUNT(*) as cnt
         FROM orders o WHERE o.order_date BETWEEN ? AND ? AND o.payment_status='Paid'$bsD
         GROUP BY DATE(o.order_date) ORDER BY day",
        'ss' . $btD, array_merge([$from, $to . ' 23:59:59'], $bpD)
    ) ?: [];
} catch (Exception $e) { $daily_rev = []; }

// ── Top Selling Products (filtered by date range, with all-time fallback) ───────
$top_products_alltime = false;
try {
    [$bsP, $btP, $bpP] = branch_where_parts('o', $branchId);
    $top_products = db_query(
        "SELECT p.name AS product_name, p.sku,
                SUM(oi.quantity) as qty_sold,
                SUM(oi.quantity * oi.unit_price) as revenue
         FROM order_items oi
         JOIN products p ON oi.product_id = p.product_id
         JOIN orders o ON oi.order_id = o.order_id
         WHERE o.order_date BETWEEN ? AND ?$bsP
         GROUP BY p.product_id, p.name, p.sku
         ORDER BY qty_sold DESC LIMIT 10",
        'ss' . $btP, array_merge([$from, $to . ' 23:59:59'], $bpP)
    ) ?: [];
    
    // Fallback: show all-time data if no results in selected period
    if (empty($top_products)) {
        $top_products = db_query(
            "SELECT p.name AS product_name, p.sku,
                    SUM(oi.quantity) as qty_sold,
                    SUM(oi.quantity * oi.unit_price) as revenue
             FROM order_items oi
             JOIN products p ON oi.product_id = p.product_id
             GROUP BY p.product_id, p.name, p.sku
             ORDER BY qty_sold DESC LIMIT 10"
        ) ?: [];
        if (!empty($top_products)) $top_products_alltime = true;
    }
} catch (Exception $e) { $top_products = []; }

// ── Top Customers ──────────────────────────────────────
try {
    [$bsC, $btC, $bpC] = branch_where_parts('o', $branchId);
    $top_customers = db_query(
        "SELECT CONCAT(c.first_name, ' ', c.last_name) as name, c.email,
                COUNT(o.order_id) as orders, SUM(o.total_amount) as spent
         FROM customers c
         JOIN orders o ON c.customer_id = o.customer_id
         WHERE o.order_date BETWEEN ? AND ?$bsC
         GROUP BY c.customer_id ORDER BY spent DESC LIMIT 10",
        'ss' . $btC, array_merge([$from, $to . ' 23:59:59'], $bpC)
    ) ?: [];
} catch (Exception $e) { $top_customers = []; }

// ── Customer Summary ───────────────────────────────────
try {
    $cust_total = db_query("SELECT COUNT(*) as cnt FROM customers")[0]['cnt'] ?? 0;
    $cust_active = db_query("SELECT COUNT(*) as cnt FROM customers WHERE status='Activated'")[0]['cnt'] ?? 0;
    $new_customers = db_query(
        "SELECT COUNT(*) as cnt FROM customers WHERE created_at BETWEEN ? AND ?",
        'ss', [$from, $to . ' 23:59:59']
    )[0]['cnt'] ?? 0;
} catch (Exception $e) { $cust_total = $cust_active = $new_customers = 0; }

// ── Inventory Alerts ───────────────────────────────────
try {
    $low_stock = db_query(
        "SELECT m.material_name, mc.category_name, m.unit, m.opening_stock, m.current_stock
         FROM materials m JOIN material_categories mc ON m.category_id = mc.category_id
         WHERE m.current_stock <= m.opening_stock * 0.2
         ORDER BY m.current_stock ASC LIMIT 10"
    ) ?: [];
    $total_materials = db_query("SELECT COUNT(*) as cnt FROM materials")[0]['cnt'] ?? 0;
    $out_of_stock = db_query("SELECT COUNT(*) as cnt FROM materials WHERE current_stock <= 0")[0]['cnt'] ?? 0;
} catch (Exception $e) { $low_stock = []; $total_materials = $out_of_stock = 0; }

// ── Recent Orders (paginated) ──────────────────────────
$txn_page = max(1, (int)($_GET['txn_page'] ?? 1));
$txn_per_page = 10;
try {
    [$bsR, $btR, $bpR] = branch_where_parts('o', $branchId);
    $txn_count = db_query(
        "SELECT COUNT(*) as cnt FROM orders o
         WHERE o.order_date BETWEEN ? AND ?$bsR",
        'ss' . $btR, array_merge([$from, $to . ' 23:59:59'], $bpR)
    )[0]['cnt'] ?? 0;
    $txn_total_pages = max(1, ceil($txn_count / $txn_per_page));
    $txn_page = min($txn_page, $txn_total_pages);
    $txn_offset = ($txn_page - 1) * $txn_per_page;
    [$bsR2, $btR2, $bpR2] = branch_where_parts('o', $branchId);
    $recent_orders = db_query(
        "SELECT o.order_id, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                o.order_date, o.total_amount, o.payment_status, o.status
         FROM orders o LEFT JOIN customers c ON o.customer_id = c.customer_id
         WHERE o.order_date BETWEEN ? AND ?$bsR2
         ORDER BY o.order_date DESC LIMIT $txn_per_page OFFSET $txn_offset",
        'ss' . $btR2, array_merge([$from, $to . ' 23:59:59'], $bpR2)
    ) ?: [];
} catch (Exception $e) { $recent_orders = []; $txn_count = 0; $txn_total_pages = 1; }

$page_title = 'Reports & Analytics - Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <?php render_branch_css(); ?>
    <style>
        /* ─── Report Styles ─── */
        .rpt-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
        @media (max-width:900px) { .rpt-grid { grid-template-columns:1fr; } }

        .rpt-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:0; overflow:hidden; }
        .rpt-card-header { display:flex; align-items:center; justify-content:space-between; padding:18px 22px 14px; border-bottom:1px solid #f3f4f6; flex-wrap:wrap; gap:12px; }
        #card-sales .rpt-card-header { flex-wrap:nowrap; }
        .rpt-card-header h3 { font-size:15px; font-weight:700; color:#1f2937; display:flex; align-items:center; gap:8px; margin:0; white-space:nowrap; flex-shrink:0; }
        .rpt-card-header .header-icon { width:18px; height:18px; color:#6366f1; flex-shrink:0; }
        #card-sales .rpt-card-header .chart-filters { display:flex; align-items:center; gap:10px; flex-shrink:1; min-width:0; }
        .rpt-card-body { padding:18px 22px 22px; }

        .print-section-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; font-size:12px; font-weight:600; color:#374151; cursor:pointer; transition:all .2s; text-decoration:none; white-space:nowrap; flex-shrink:0; }
        .print-section-btn:hover { background:#eef2ff; border-color:#c7d2fe; color:#4f46e5; }
        .print-section-btn svg { width:14px; height:14px; }

        /* ─── Date Filter (inline: title left, controls right) ─── */
        .date-filter-bar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .date-filter-bar h2 { font-size:16px; font-weight:700; color:#1f2937; margin:0; }
        .date-filter-controls { display:inline-flex; align-items:center; gap:10px; padding:8px 16px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; flex-wrap:wrap; }
        .date-filter-controls label { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#6b7280; }
        .date-filter-controls input[type=date] { padding:6px 10px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; background:#fff; }
        .date-filter-controls input[type=date]:focus { outline:none; border-color:#6366f1; }
        .date-filter-controls button { padding:7px 18px; background:#6366f1; color:#fff; border:none; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:background .2s; }
        .date-filter-controls button:hover { background:#4f46e5; }

        /* ─── KPI Row ─── */
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

        /* ─── Table ─── */
        .rpt-table { width:100%; border-collapse:collapse; }
        .rpt-table th { padding:10px 14px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#6b7280; background:#f9fafb; text-align:left; border-bottom:2px solid #e5e7eb; }
        .rpt-table td { padding:10px 14px; font-size:13px; border-bottom:1px solid #f3f4f6; color:#374151; }
        .rpt-table tr:hover td { background:#f9fafb; }
        .rpt-table .num { text-align:right; font-variant-numeric:tabular-nums; font-weight:600; }

        /* ─── Status Badges ─── */
        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
        .badge-green { background:#d1fae5; color:#059669; }
        .badge-yellow { background:#fef3c7; color:#d97706; }
        .badge-blue { background:#dbeafe; color:#2563eb; }
        .badge-red { background:#fee2e2; color:#dc2626; }
        .badge-gray { background:#f3f4f6; color:#6b7280; }

        /* ─── Stock Bar ─── */
        .stock-bar { width:100%; height:6px; background:#f3f4f6; border-radius:3px; overflow:hidden; }
        .stock-bar-fill { height:100%; border-radius:3px; transition:width .4s ease; }
        .stock-bar-fill.good { background:#059669; }
        .stock-bar-fill.warning { background:#f59e0b; }
        .stock-bar-fill.danger { background:#ef4444; }

        /* ─── Chart Canvas ─── */
        .chart-wrap { position:relative; width:100%; height:220px; }
        .chart-loading { position:absolute; inset:0; background:rgba(255,255,255,.9); display:flex; align-items:center; justify-content:center; z-index:2; border-radius:8px; }
        .chart-loading.hidden { display:none; }
        .chart-loading-spinner { width:28px; height:28px; border:3px solid #e5e7eb; border-top-color:#6366f1; border-radius:50%; animation:chart-spin .7s linear infinite; }
        @keyframes chart-spin { to { transform:rotate(360deg); } }
        .chart-nodata { position:absolute; inset:0; display:none; align-items:center; justify-content:center; flex-direction:column; gap:8px; color:#9ca3af; font-size:13px; z-index:1; }
        .chart-nodata.visible { display:flex; }
        .chart-wrap-tall { position:relative; width:100%; height:280px; }

        /* ─── Period Toggle Tabs ─── */
        .period-tabs { display:flex; gap:4px; flex-wrap:wrap; }
        .period-tab { padding:4px 11px; border-radius:6px; font-size:11px; font-weight:600; border:1px solid #e5e7eb; background:#f9fafb; color:#6b7280; cursor:pointer; transition:all .15s; }
        .period-tab:hover { border-color:#6366f1; color:#6366f1; }
        .period-tab.active { background:#6366f1; border-color:#6366f1; color:#fff; }
        .chart-select { padding:6px 10px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; font-weight:600; background:#fff; color:#374151; width:auto; min-width:4em; max-width:100%; }
        .chart-filters { display:flex; flex-wrap:wrap; align-items:center; gap:10px; }
        .chart-filter-label { font-size:12px; font-weight:600; color:#6b7280; white-space:nowrap; }
        .chart-filter-group { display:flex; gap:8px; align-items:center; }

        /* ─── Full Width ─── */
        .rpt-full { grid-column:1/-1; }

        .no-data { text-align:center; padding:32px 16px; color:#9ca3af; font-size:13px; }

        /* ─── Print Styles ─── */
        @media print {
            .sidebar, .mobile-header, header, .no-print, .date-filter-controls, .print-section-btn { display: none !important; }
            .main-content { margin-left: 0 !important; padding-top: 0 !important; }
            .dashboard-container { display: block !important; }
            .rpt-card { break-inside: avoid; border: 1px solid #ddd !important; margin-bottom: 16px; }
            .rpt-grid { display: block !important; }
            .rpt-grid > * { margin-bottom: 20px; }
            .kpi-row { grid-template-columns: repeat(4, 1fr) !important; }
            body { background: white !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
            .print-header h2 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
            .print-header p { font-size: 12px; color: #6b7280; }
            canvas { max-height: 200px !important; }
        }
        .print-header { display: none; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include defined('MANAGER_PANEL') ? __DIR__ . '/../includes/manager_sidebar.php' : __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <header>
            <h1 class="page-title">Reports & Analytics</h1>
            <?php render_branch_selector($branchCtx); ?>
            <button class="btn-secondary no-print" onclick="window.print()">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print Report
            </button>
        </header>

        <main>
            <?php render_branch_context_banner($branchCtx['branch_name']); ?>
            <!-- Print Header (visible only when printing) -->
            <div class="print-header">
                <h2>PrintFlow - Reports & Analytics</h2>
                <p>Period: <?php echo date('M d, Y', strtotime($from)); ?> – <?php echo date('M d, Y', strtotime($to)); ?> | Generated on <?php echo date('F j, Y g:i A'); ?></p>
            </div>

            <!-- Date Range Filter (inline) -->
            <div class="date-filter-bar no-print">
                <form method="GET" class="date-filter-controls">
                    <?php if ($branchId !== 'all'): ?>
                    <input type="hidden" name="branch_id" value="<?php echo (int)$branchId; ?>">
                    <?php endif; ?>
                    <label>From</label>
                    <input type="date" name="from" value="<?php echo $from; ?>">
                    <label>To</label>
                    <input type="date" name="to" value="<?php echo $to; ?>">
                    <button type="submit">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline; vertical-align:-2px; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Apply
                    </button>
                </form>
            </div>

            <!-- KPI Summary Row -->
            <div class="kpi-row">
                <div class="kpi-card indigo">
                    <div class="kpi-label">Total Revenue</div>
                    <div class="kpi-value">₱<?php echo number_format($revenue, 2); ?></div>
                    <div class="kpi-sub"><?php echo $paid_orders; ?> paid orders</div>
                </div>
                <div class="kpi-card emerald">
                    <div class="kpi-label">Total Orders</div>
                    <div class="kpi-value"><?php echo $total_orders; ?></div>
                    <div class="kpi-sub">Avg ₱<?php echo number_format($avg_val, 2); ?> per order</div>
                </div>
                <div class="kpi-card amber">
                    <div class="kpi-label">Customers</div>
                    <div class="kpi-value"><?php echo $cust_total; ?></div>
                    <div class="kpi-sub"><?php echo $new_customers; ?> new this period</div>
                </div>
                <div class="kpi-card rose">
                    <div class="kpi-label">Low Stock Items</div>
                    <div class="kpi-value"><?php echo count($low_stock); ?></div>
                    <div class="kpi-sub"><?php echo $out_of_stock; ?> out of stock</div>
                </div>
            </div>

            <!-- Report Cards Grid -->
            <div class="rpt-grid">

                <!-- ═══ SALES REVENUE CHART ═══ -->
                <div class="rpt-card" id="card-sales">
                    <div class="rpt-card-header">
                        <h3>
                            <svg class="header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            Sales Revenue
                        </h3>
                        <div class="chart-filters">
                            <label class="chart-filter-label">Period</label>
                            <select id="rpt-chart-period" class="chart-select">
                                <option value="today">Today</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="6months">Last 6 Months</option>
                                <option value="yearly">Yearly</option>
                            </select>
                            <span id="rpt-year-month" class="chart-filter-group">
                                <select id="rpt-chart-month" class="chart-select" style="display:none;" title="Month">
                                    <?php foreach (['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $i => $m): ?>
                                    <option value="<?php echo $i+1; ?>" <?php echo ($i+1)==date('n')?'selected':''; ?>><?php echo $m; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select id="rpt-chart-year" class="chart-select" title="Year">
                                    <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y==date('Y')?'selected':''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </span>
                        </div>
                        <button class="print-section-btn no-print" onclick="printCard('card-sales')">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print
                        </button>
                    </div>
                    <div class="rpt-card-body">
                        <div class="chart-wrap" id="rpt-sales-chart-wrap">
                            <div class="chart-loading hidden" id="rpt-sales-loading">
                                <div class="chart-loading-spinner"></div>
                            </div>
                            <div class="chart-nodata" id="rpt-sales-nodata">
                                <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24" opacity="0.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                <span>No sales data for this period</span>
                            </div>
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- ═══ ORDER STATUS BREAKDOWN ═══ -->
                <div class="rpt-card" id="card-status">
                    <div class="rpt-card-header">
                        <h3>
                            <svg class="header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Order Status
                        </h3>
                        <button class="print-section-btn no-print" onclick="printCard('card-status')">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print
                        </button>
                    </div>
                    <div class="rpt-card-body">
                        <div class="chart-wrap"><canvas id="statusChart"></canvas></div>
                    </div>
                </div>

                <!-- ═══ TOP CUSTOMERS (Horizontal Bar Chart + Table) ═══ -->
                <div class="rpt-card" id="card-customers">
                    <div class="rpt-card-header">
                        <h3>
                            <svg class="header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Top Customers
                        </h3>
                        <button class="print-section-btn no-print" onclick="printCard('card-customers')">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print
                        </button>
                    </div>
                    <div class="rpt-card-body">
                        <?php if (!empty($top_customers)): ?>
                            <div class="chart-wrap-tall"><canvas id="customersChart"></canvas></div>
                            <div style="overflow-x:auto; margin-top:16px;">
                                <table class="rpt-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Customer</th>
                                            <th class="num">Orders</th>
                                            <th class="num">Total Spent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_customers as $i => $tc): ?>
                                        <tr>
                                            <td style="font-weight:700; color:#9ca3af;"><?php echo $i + 1; ?></td>
                                            <td>
                                                <div style="font-weight:600;"><?php echo htmlspecialchars($tc['name']); ?></div>
                                                <div style="font-size:11px; color:#9ca3af;"><?php echo htmlspecialchars($tc['email']); ?></div>
                                            </td>
                                            <td class="num"><?php echo $tc['orders']; ?></td>
                                            <td class="num" style="color:#059669; font-weight:700;">₱<?php echo number_format((float)$tc['spent'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="no-data">No customer data for this period</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ═══ TOP SELLING PRODUCTS ═══ -->
                <div class="rpt-card" id="card-products">
                    <div class="rpt-card-header">
                        <h3>
                            <svg class="header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            Top Selling Products
                        </h3>
                        <button class="print-section-btn no-print" onclick="printCard('card-products')">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print
                        </button>
                    </div>
                    <div class="rpt-card-body">
                        <?php if (!empty($top_products)): ?>
                        <?php if ($top_products_alltime): ?>
                        <div style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;padding:8px 12px;border-radius:8px;font-size:12px;margin-bottom:12px;">
                            📊 Showing <strong>all-time</strong> top sellers (no sales data in selected period)
                        </div>
                        <?php endif; ?>
                        <div style="overflow-x:auto;">
                            <table class="rpt-table">
                                <thead>
                                    <tr><th>#</th><th>Product</th><th class="num">Qty Sold</th><th class="num">Revenue</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_products as $i => $tp): ?>
                                    <tr>
                                        <td style="font-weight:700; color:#9ca3af;"><?php echo $i + 1; ?></td>
                                        <td>
                                            <div style="font-weight:600;"><?php echo htmlspecialchars($tp['product_name']); ?></div>
                                            <div style="font-size:11px; color:#9ca3af;"><?php echo htmlspecialchars($tp['sku'] ?? ''); ?></div>
                                        </td>
                                        <td class="num"><?php echo (int)$tp['qty_sold']; ?></td>
                                        <td class="num" style="color:#059669; font-weight:700;">₱<?php echo number_format((float)$tp['revenue'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="no-data">No product sales for this period</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ═══ INVENTORY ALERTS (Bar Chart + Table) ═══ -->
                <div class="rpt-card" id="card-inventory">
                    <div class="rpt-card-header">
                        <h3>
                            <svg class="header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            Inventory Alerts
                        </h3>
                        <button class="print-section-btn no-print" onclick="printCard('card-inventory')">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print
                        </button>
                    </div>
                    <div class="rpt-card-body">
                        <?php if (empty($low_stock)): ?>
                            <div class="no-data" style="color:#059669;">
                                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 8px; display:block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                All stock levels are healthy!
                            </div>
                        <?php else: ?>
                            <div class="chart-wrap-tall"><canvas id="inventoryChart"></canvas></div>
                            <div style="overflow-x:auto; margin-top:16px;">
                                <table class="rpt-table">
                                    <thead>
                                        <tr>
                                            <th>Material</th>
                                            <th>Category</th>
                                            <th class="num">Stock</th>
                                            <th style="width:100px;">Level</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($low_stock as $ls):
                                            $pct = (float)$ls['opening_stock'] > 0 ? ((float)$ls['current_stock'] / (float)$ls['opening_stock']) * 100 : 0;
                                            $barClass = $pct <= 0 ? 'danger' : ($pct <= 20 ? 'warning' : 'good');
                                        ?>
                                        <tr>
                                            <td style="font-weight:600;" title="<?php echo htmlspecialchars($ls['material_name']); ?>">
                                                <?php echo mb_strlen($ls['material_name']) > 10 ? htmlspecialchars(mb_substr($ls['material_name'], 0, 10)) . '...' : htmlspecialchars($ls['material_name']); ?>
                                                <span style="font-size:10px; color:#9ca3af; font-weight:400;"><?php echo htmlspecialchars($ls['unit']); ?></span>
                                            </td>
                                            <td style="font-size:12px; color:#6b7280;"><?php echo htmlspecialchars($ls['category_name']); ?></td>
                                            <td class="num" style="color:<?php echo $pct <= 0 ? '#ef4444' : '#d97706'; ?>;">
                                                <?php echo number_format((float)$ls['current_stock'], 1); ?>
                                                <span style="color:#9ca3af; font-weight:400;">/<?php echo number_format((float)$ls['opening_stock'], 1); ?></span>
                                            </td>
                                            <td>
                                                <div class="stock-bar"><div class="stock-bar-fill <?php echo $barClass; ?>" style="width:<?php echo max($pct, 2); ?>%;"></div></div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ═══ RECENT TRANSACTIONS (FULL WIDTH) ═══ -->
                <div class="rpt-card rpt-full" id="card-transactions">
                    <div class="rpt-card-header">
                        <h3>
                            <svg class="header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Recent Transactions
                        </h3>
                        <button class="print-section-btn no-print" onclick="printCard('card-transactions')">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print
                        </button>
                    </div>
                    <div class="rpt-card-body" style="padding:0;">
                        <?php if (empty($recent_orders)): ?>
                            <div class="no-data">No transactions for this period</div>
                        <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="rpt-table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th class="num">Amount</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $ro): 
                                        // Payment badge
                                        $pBadge = match($ro['payment_status']) {
                                            'Paid' => 'badge-green',
                                            'Pending' => 'badge-yellow',
                                            default => 'badge-red'
                                        };
                                        // Status badge
                                        $sBadge = match($ro['status']) {
                                            'Completed' => 'badge-green',
                                            'Processing' => 'badge-blue',
                                            'Pending' => 'badge-yellow',
                                            'Ready for Pickup' => 'badge-blue',
                                            'Cancelled' => 'badge-red',
                                            default => 'badge-gray'
                                        };
                                    ?>
                                    <tr>
                                        <td style="font-weight:700; color:#6366f1;"><?php echo $ro['order_id']; ?></td>
                                        <td style="font-weight:500;"><?php echo htmlspecialchars($ro['customer_name']); ?></td>
                                        <td style="white-space:nowrap; color:#6b7280;"><?php echo date('M d, Y', strtotime($ro['order_date'])); ?></td>
                                        <td class="num" style="font-weight:700;">₱<?php echo number_format((float)$ro['total_amount'], 2); ?></td>
                                        <td><span class="badge <?php echo $pBadge; ?>"><?php echo $ro['payment_status']; ?></span></td>
                                        <td><span class="badge <?php echo $sBadge; ?>"><?php echo $ro['status']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php
                            $txn_params = ['from' => $from, 'to' => $to];
                            echo render_pagination($txn_page, $txn_total_pages, array_merge($txn_params, ['txn_page' => $txn_page]));
                        ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /.rpt-grid -->
        </main>
    </div>
</div>

<script>
// ─── Print Single Card Function ───────────────────────
function printCard(cardId) {
    const card = document.getElementById(cardId);
    if (!card) return;
    const printWin = window.open('', '_blank');
    // Get all stylesheets from the page
    const styles = Array.from(document.querySelectorAll('link[rel=stylesheet], style')).map(el => el.outerHTML).join('\n');
    printWin.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Print Report</title>${styles}
        <style>
            body { padding: 20px; background: white; }
            .rpt-card { border: none !important; box-shadow: none !important; }
            .rpt-card-header { border-bottom: 2px solid #e5e7eb !important; }
            .print-section-btn, .no-print { display: none !important; }
            @media print { body { padding: 0; } }
        </style>
    </head><body>${card.outerHTML}</body></html>`);
    printWin.document.close();
    printWin.onload = function() { printWin.print(); };
}

// ─── Sales Revenue Line Chart ─────────────────────────
const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(salesCtx, {
    type: 'line',
    data: { labels: [], datasets: [
        {
            label: 'Revenue (₱)',
            data: [],
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,.08)',
            borderWidth: 2.5,
            fill: true,
            tension: 0.35,
            pointBackgroundColor: '#6366f1',
            pointRadius: 3,
            pointHoverRadius: 5,
            yAxisID: 'y'
        }, {
            label: 'Orders',
            data: [],
            borderColor: '#10b981',
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [5, 3],
            tension: 0.35,
            pointRadius: 2,
            pointHoverRadius: 4,
            yAxisID: 'y1'
        }
    ]},
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 12, font: { size: 11 } } } },
        scales: {
            y:  { beginAtZero: true, ticks: { font: { size: 11 }, callback: v => '₱' + v.toLocaleString() }, grid: { color: '#f3f4f6' } },
            y1: { beginAtZero: true, position: 'right', ticks: { font: { size: 11 }, precision: 0 }, grid: { display: false } },
            x:  { ticks: { font: { size: 10 }, maxRotation: 45 }, grid: { display: false } }
        }
    }
});

async function loadRptSalesChart(period) {
    const loadingEl = document.getElementById('rpt-sales-loading');
    const noDataEl = document.getElementById('rpt-sales-nodata');
    const yearEl = document.getElementById('rpt-chart-year');
    const monthEl = document.getElementById('rpt-chart-month');
    if (loadingEl) loadingEl.classList.remove('hidden');
    if (noDataEl) noDataEl.classList.remove('visible');
    const year = yearEl ? yearEl.value : new Date().getFullYear();
    const month = monthEl ? monthEl.value : new Date().getMonth() + 1;
    let url = 'api_revenue_chart.php?period=' + period + '&year=' + year;
    if (period === 'monthly') url += '&month=' + month;
    try {
        const resp = await fetch(url, { credentials: 'same-origin' });
        const text = await resp.text();
        let data;
        try { data = JSON.parse(text); } catch(e) {
            console.error('Chart API returned non-JSON:', text.substring(0, 200));
            if (noDataEl) { noDataEl.querySelector('span').textContent = 'Failed to load chart data'; noDataEl.classList.add('visible'); }
            return;
        }
        if (data.error) console.warn('Chart API error:', data.error, data.message || '');
        const labels  = data.labels  || [];
        const revenue = data.revenue || [];
        const orders  = data.orders  || [];
        salesChart.data.labels           = labels;
        salesChart.data.datasets[0].data = revenue;
        salesChart.data.datasets[1].data = orders;
        salesChart.update();
        if (noDataEl) noDataEl.classList.toggle('visible', labels.length === 0);
    } catch(e) {
        console.error('loadRptSalesChart error:', e);
        if (noDataEl) { noDataEl.querySelector('span').textContent = 'Failed to load chart data'; noDataEl.classList.add('visible'); }
    } finally {
        if (loadingEl) loadingEl.classList.add('hidden');
    }
}

function updateRptChartYearMonthVisibility(period) {
    const wrap = document.getElementById('rpt-year-month');
    const monthEl = document.getElementById('rpt-chart-month');
    if (!wrap) return;
    wrap.style.display = ['monthly','6months','yearly'].includes(period) ? 'flex' : 'none';
    if (monthEl) monthEl.style.display = period === 'monthly' ? 'inline-block' : 'none';
}

function getRptChartPeriod() {
    const sel = document.getElementById('rpt-chart-period');
    return sel ? sel.value : 'monthly';
}

document.getElementById('rpt-chart-period')?.addEventListener('change', () => {
    const period = getRptChartPeriod();
    updateRptChartYearMonthVisibility(period);
    loadRptSalesChart(period);
});
document.getElementById('rpt-chart-year')?.addEventListener('change', () => loadRptSalesChart(getRptChartPeriod()));
document.getElementById('rpt-chart-month')?.addEventListener('change', () => loadRptSalesChart(getRptChartPeriod()));

updateRptChartYearMonthVisibility('monthly');
loadRptSalesChart('monthly');

// ─── Order Status Doughnut Chart ──────────────────────
// Best: Doughnut/Pie chart — shows proportional distribution of order statuses
<?php if (!empty($status_data)): ?>
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusColors = {
    'Pending': '#f59e0b', 'Processing': '#3b82f6', 'Ready for Pickup': '#06b6d4',
    'Completed': '#10b981', 'Cancelled': '#ef4444'
};
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(fn($d) => $d['status'], $status_data)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_map(fn($d) => (int)$d['cnt'], $status_data)); ?>,
            backgroundColor: <?php echo json_encode(array_map(fn($d) => $statusColors[$d['status']] ?? '#6b7280', $status_data)); ?>,
            borderWidth: 2,
            borderColor: '#fff',
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: { position: 'bottom', labels: { padding: 16, boxWidth: 12, font: { size: 12 } } }
        }
    }
});
<?php endif; ?>

// ─── Top Customers Horizontal Bar Chart ───────────────
// Best: Horizontal bar chart — clearly ranks customers by spending, easy label readability
<?php if (!empty($top_customers)): ?>
const custCtx = document.getElementById('customersChart').getContext('2d');
new Chart(custCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(fn($c) => mb_strlen($c['name']) > 10 ? mb_substr($c['name'], 0, 10) . '...' : $c['name'], $top_customers)); ?>,
        datasets: [{
            label: 'Total Spent (₱)',
            data: <?php echo json_encode(array_map(fn($c) => (float)$c['spent'], $top_customers)); ?>,
            backgroundColor: [
                'rgba(99,102,241,0.8)', 'rgba(139,92,246,0.8)', 'rgba(168,85,247,0.8)',
                'rgba(192,132,252,0.7)', 'rgba(196,181,253,0.7)', 'rgba(167,139,250,0.6)',
                'rgba(129,140,248,0.6)', 'rgba(165,180,252,0.5)', 'rgba(199,210,254,0.5)',
                'rgba(224,231,255,0.5)'
            ],
            borderRadius: 6,
            borderSkipped: false
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => '₱' + ctx.parsed.x.toLocaleString(undefined, {minimumFractionDigits: 2})
                }
            }
        },
        scales: {
            x: { beginAtZero: true, ticks: { font: { size: 11 }, callback: v => '₱' + v.toLocaleString() }, grid: { color: '#f3f4f6' } },
            y: { ticks: { font: { size: 11 } }, grid: { display: false } }
        }
    }
});
<?php endif; ?>

// ─── Inventory Alerts Bar Chart ───────────────────────
// Best: Grouped horizontal bar chart — compares current vs opening stock, highlights danger levels
<?php if (!empty($low_stock)): ?>
const invCtx = document.getElementById('inventoryChart').getContext('2d');
new Chart(invCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(fn($ls) => mb_strlen($ls['material_name']) > 10 ? mb_substr($ls['material_name'], 0, 10) . '...' : $ls['material_name'], $low_stock)); ?>,
        datasets: [{
            label: 'Current Stock',
            data: <?php echo json_encode(array_map(fn($ls) => (float)$ls['current_stock'], $low_stock)); ?>,
            backgroundColor: <?php echo json_encode(array_map(function($ls) {
                $pct = (float)$ls['opening_stock'] > 0 ? ((float)$ls['current_stock'] / (float)$ls['opening_stock']) * 100 : 0;
                if ($pct <= 0) return 'rgba(239,68,68,0.8)';
                if ($pct <= 20) return 'rgba(245,158,11,0.8)';
                return 'rgba(5,150,105,0.8)';
            }, $low_stock)); ?>,
            borderRadius: 6,
            borderSkipped: false
        }, {
            label: 'Opening Stock',
            data: <?php echo json_encode(array_map(fn($ls) => (float)$ls['opening_stock'], $low_stock)); ?>,
            backgroundColor: 'rgba(229,231,235,0.6)',
            borderRadius: 6,
            borderSkipped: false
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } }
        },
        scales: {
            x: { beginAtZero: true, ticks: { font: { size: 11 } }, grid: { color: '#f3f4f6' } },
            y: { ticks: { font: { size: 11 } }, grid: { display: false } }
        }
    }
});
<?php endif; ?>
</script>

</body>
</html>
