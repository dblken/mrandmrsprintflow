<?php
/**
 * Admin Dashboard - PrintFlow
 * Real-time data from the database  (branch-aware)
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';
require_once __DIR__ . '/../includes/branch_ui.php';
require_role(['Admin', 'Manager']);

// Managers must use their own panel — redirect them away from /admin/
if (is_manager()) {
    $mgr_branch = $_SESSION['selected_branch_id'] ?? $_SESSION['branch_id'] ?? null;
    $mgr_qs = $mgr_branch && $mgr_branch !== 'all' ? '?branch_id=' . (int)$mgr_branch : '';
    header('Location: ' . AUTH_REDIRECT_BASE . '/manager/dashboard.php' . $mgr_qs);
    exit();
}

// ── Branch Context (analytics page — allows "All") ────
$branchCtx = init_branch_context(false);
$branchId  = $branchCtx['selected_branch_id']; // 'all' | int

// Build reusable branch SQL parts
$bTypes = ''; $bParams = [];
$bSql = branch_where('o', $branchId, $bTypes, $bParams);

// ── KPI: Total Customers ──────────────────────────────
try {
    $total_customers = db_query("SELECT COUNT(*) as cnt FROM customers")[0]['cnt'] ?? 0;
} catch (Exception $e) { $total_customers = 0; }

// ── KPI: Total Revenue (Paid orders, branch-filtered) ─
try {
    $rev_sql    = "SELECT COALESCE(SUM(o.total_amount),0) as total FROM orders o WHERE o.payment_status = 'Paid'" . $bSql;
    $total_revenue = db_query($rev_sql, $bTypes ?: null, $bParams ?: null)[0]['total'] ?? 0;
} catch (Exception $e) { $total_revenue = 0; }

// ── KPI: Total Orders (branch-filtered) ───────────────
try {
    $ord_sql = "SELECT COUNT(*) as cnt FROM orders o WHERE 1=1" . $bSql;
    $total_orders = db_query($ord_sql, $bTypes ?: null, $bParams ?: null)[0]['cnt'] ?? 0;
} catch (Exception $e) { $total_orders = 0; }

// ── KPI: Pending Orders (branch-filtered) ────────────
try {
    $pend_types = $bTypes; $pend_params = $bParams;
    $pend_sql = "SELECT COUNT(*) as cnt FROM orders o WHERE o.status = 'Pending'" . branch_where('o', $branchId, $pend_types, $pend_params);
    // Re-build cleanly to avoid double-appending
    [$bSqlFrag, $bT, $bP] = branch_where_parts('o', $branchId);
    $pending_orders = db_query(
        "SELECT COUNT(*) as cnt FROM orders o WHERE o.status = 'Pending'" . $bSqlFrag,
        $bT ?: null, $bP ?: null
    )[0]['cnt'] ?? 0;
} catch (Exception $e) { $pending_orders = 0; }

// ── Sales Revenue (Last 30 days, branch-filtered) ─────
try {
    [$bSqlFrag, $bT2, $bP2] = branch_where_parts('o', $branchId);
    $daily_sales = db_query(
        "SELECT DATE(o.order_date) as day, SUM(o.total_amount) as revenue, COUNT(*) as orders
         FROM orders o WHERE o.payment_status='Paid' AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         {$bSqlFrag}
         GROUP BY DATE(o.order_date) ORDER BY day",
        $bT2 ?: null, $bP2 ?: null
    ) ?: [];
} catch (Exception $e) { $daily_sales = []; }

// ── Order Status Breakdown ────────────────────────────
try {
    [$bSqlFrag_os, $bT_os, $bP_os] = branch_where_parts('o', $branchId);
    $order_status = db_query(
        "SELECT o.status, COUNT(*) as cnt FROM orders o WHERE 1=1 {$bSqlFrag_os} GROUP BY o.status",
        $bT_os ?: null, $bP_os ?: null
    ) ?: [];
} catch (Exception $e) { $order_status = []; }

$statusColors = [
    'Pending' => '#F39C12',
    'Processing' => '#3498DB',
    'Ready for Pickup' => '#53C5E0',
    'Completed' => '#2ECC71',
    'Cancelled' => '#E74C3C',
    'Design Approved' => '#6C5CE7',
];

// ── Sales by Product Category ─────────────────────────
try {
    [$bSqlFrag_cs, $bT_cs, $bP_cs] = branch_where_parts('o', $branchId);
    $category_sales = db_query(
        "SELECT p.category, COUNT(oi.order_item_id) as items_sold, SUM(oi.quantity * oi.unit_price) as total
         FROM order_items oi
         JOIN products p ON oi.product_id = p.product_id
         JOIN orders o ON oi.order_id = o.order_id
         WHERE o.payment_status = 'Paid' {$bSqlFrag_cs}
         GROUP BY p.category ORDER BY total DESC",
        $bT_cs ?: null, $bP_cs ?: null
    ) ?: [];
} catch (Exception $e) { $category_sales = []; }

$cat_total_sum = array_sum(array_map(fn($c) => (float)$c['total'], $category_sales));

// ── Recent Orders (last 5, branch-filtered) ──────────
try {
    [$bSqlFrag3, $bT3, $bP3] = branch_where_parts('o', $branchId);
    $recent_orders = db_query(
        "SELECT o.order_id, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                o.order_date, o.total_amount, o.payment_status, o.status, b.branch_name
         FROM orders o
         LEFT JOIN customers c ON o.customer_id = c.customer_id
         LEFT JOIN branches b  ON o.branch_id  = b.id
         WHERE 1=1 {$bSqlFrag3}
         ORDER BY o.order_date DESC LIMIT 5",
        $bT3 ?: null, $bP3 ?: null
    ) ?: [];
} catch (Exception $e) { $recent_orders = []; }

// ── Low Stock Alerts (NEW SYSTEM) ─────────────────────
try {
    // Requires InventoryManager to get real-time SOH
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
            // Calculate ratio so we can sort (lowest relative stock first)
            $item['ratio'] = $soh / $item['low_limit'];
            $low_stock[] = $item;
        }
    }
    // Sort by ratio ASC
    usort($low_stock, fn($a, $b) => $a['ratio'] <=> $b['ratio']);
    $low_stock = array_slice($low_stock, 0, 5);
} catch (Exception $e) { $low_stock = []; }

// ── Top Customers (by spending) ───────────────────────
try {
    [$bSqlFrag_c, $bT_c, $bP_c] = branch_where_parts('o', $branchId);
    [$bSqlFrag_j, $bT_j, $bP_j] = branch_where_parts('j', $branchId);
    $types = ($bT_c ?: '') . ($bT_j ?: '');
    $params = array_merge($bP_c ?: [], $bP_j ?: []);
    $top_customers = db_query(
        "SELECT customer_name as name, COUNT(id) as orders, SUM(spent) as spent
         FROM (
             SELECT CONCAT(c.first_name, ' ', c.last_name) COLLATE utf8mb4_unicode_ci as customer_name, o.order_id as id, o.total_amount as spent
             FROM customers c JOIN orders o ON c.customer_id = o.customer_id
             WHERE o.payment_status = 'Paid' {$bSqlFrag_c}
             UNION ALL
             SELECT j.customer_name COLLATE utf8mb4_unicode_ci, j.id, j.amount_paid as spent
             FROM job_orders j
             WHERE j.payment_status = 'PAID' AND j.customer_name IS NOT NULL AND j.customer_name != '' {$bSqlFrag_j}
         ) as all_orders
         GROUP BY customer_name ORDER BY spent DESC LIMIT 5",
        $types ?: null, $params ?: null
    ) ?: [];
} catch (Exception $e) { $top_customers = []; }

// ── Top Selling Products (by quantity sold) ────────────
try {
    [$bSqlFrag_tp, $bT_tp, $bP_tp] = branch_where_parts('o', $branchId);
    $top_products = db_query(
        "SELECT p.name as product_name, p.sku,
                SUM(oi.quantity) as qty_sold,
                SUM(oi.quantity * oi.unit_price) as revenue
         FROM order_items oi
         JOIN products p ON oi.product_id = p.product_id
         JOIN orders o ON oi.order_id = o.order_id
         WHERE o.payment_status = 'Paid' {$bSqlFrag_tp}
         GROUP BY p.product_id, p.name, p.sku
         ORDER BY qty_sold DESC LIMIT 5",
        $bT_tp ?: null, $bP_tp ?: null
    ) ?: [];
} catch (Exception $e) { $top_products = []; }

$page_title = 'Dashboard - Admin | PrintFlow';
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
        .kpi-card.indigo::before { background:linear-gradient(90deg,#00232b,#53C5E0); }
        .kpi-card.emerald::before { background:linear-gradient(90deg,#059669,#34d399); }
        .kpi-card.amber::before { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
        .kpi-card.rose::before { background:linear-gradient(90deg,#e11d48,#fb7185); }
        .kpi-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#9ca3af; margin-bottom:6px; }
        .kpi-sub { font-size:12px; color:#6b7280; margin-top:4px; }

        /* Dashboard Grid */
        .dash-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
        @media (max-width:1024px) { .dash-grid { grid-template-columns:1fr; } }
        .dash-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px; }
        .dash-card-title { font-size:15px; font-weight:700; color:#1f2937; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
        .dash-card-title svg { width:18px; height:18px; color:#53C5E0; }

        /* Full width card */
        .dash-full { grid-column: 1 / -1; }

        /* Mini table */
        .mini-table { width:100%; border-collapse:collapse; font-size:13px; }
        .mini-table th { text-align:left; padding:8px 10px; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.3px; color:#9ca3af; border-bottom:1px solid #f3f4f6; }
        .mini-table td { padding:8px 10px; border-bottom:1px solid #f9fafb; }
        .mini-table tr:hover { background:#f9fafb; }

        /* Chart containers */
        .chart-wrap { position:relative; height:250px; transform:translateZ(0); }
        .chart-loading { position:absolute; inset:0; background:rgba(255,255,255,.9); display:flex; align-items:center; justify-content:center; z-index:2; border-radius:8px; }
        .chart-loading.hidden { display:none; }
        .chart-loading-spinner { width:28px; height:28px; border:3px solid #e5e7eb; border-top-color:#53C5E0; border-radius:50%; animation:chart-spin .7s linear infinite; }
        @keyframes chart-spin { to { transform:rotate(360deg); } }
        .chart-nodata { position:absolute; inset:0; display:none; align-items:center; justify-content:center; flex-direction:column; gap:8px; color:#9ca3af; font-size:13px; z-index:1; }
        .chart-nodata.visible { display:flex; }

        /* Period dropdown (legacy tab class removed) */
        .chart-select { padding:6px 10px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; font-weight:600; background:#fff; color:#374151; width:auto; min-width:4em; max-width:100%; }
        .chart-header-row { justify-content:space-between; align-items:center; flex-wrap:nowrap; gap:12px; margin-bottom:14px; }
        .chart-title-nowrap { white-space:nowrap; flex-shrink:0; display:flex; align-items:center; gap:8px; }
        .chart-filters { display:flex; flex-wrap:nowrap; align-items:center; gap:10px; flex-shrink:0; }
        .chart-filter-label { font-size:12px; font-weight:600; color:#6b7280; white-space:nowrap; }
        .chart-filter-group { display:flex; gap:8px; align-items:center; flex-shrink:0; }
        .period-tab { padding:5px 12px; border-radius:6px; font-size:12px; font-weight:600; border:1px solid #e5e7eb; background:#f9fafb; color:#6b7280; cursor:pointer; transition:all .15s; }
        .period-tab:hover { border-color:#53C5E0; color:#00232b; }
        .period-tab.active { background:#00232b; border-color:#00232b; color:#fff; }

        /* Status badge */
        .badge { display:inline-block; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:600; }
        .badge-green { background:#d1fae5; color:#065f46; }
        .badge-yellow { background:#fef3c7; color:#92400e; }
        .badge-blue { background:#dbeafe; color:#1e40af; }
        .badge-red { background:#fee2e2; color:#991b1b; }
        .badge-gray { background:#f3f4f6; color:#374151; }

        /* Stock bar */
        .stock-bar { height:6px; background:#f3f4f6; border-radius:3px; overflow:hidden; width:80px; }
        .stock-bar-fill { height:100%; border-radius:3px; }
        .stock-bar-fill.danger { background:#ef4444; }
        .stock-bar-fill.warning { background:#f59e0b; }
        .stock-bar-fill.good { background:#10b981; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

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
                    <div class="kpi-label">Total Customers</div>
                    <div class="kpi-value"><?php echo number_format($total_customers); ?></div>
                    <div class="kpi-sub">Registered accounts</div>
                </div>
                <div class="kpi-card emerald">
                    <div class="kpi-label">Total Revenue</div>
                    <div class="kpi-value">₱<?php echo number_format((float)$total_revenue, 2); ?></div>
                    <div class="kpi-sub">From paid orders</div>
                </div>
                <div class="kpi-card amber">
                    <div class="kpi-label">Total Orders</div>
                    <div class="kpi-value"><?php echo number_format($total_orders); ?></div>
                    <div class="kpi-sub">All time</div>
                </div>
                <div class="kpi-card rose">
                    <div class="kpi-label">Pending Orders</div>
                    <div class="kpi-value"><?php echo number_format($pending_orders); ?></div>
                    <div class="kpi-sub">Awaiting processing</div>
                </div>
            </div>

            <!-- Sales Chart + Order Status -->
            <div class="dash-grid">
                <!-- Sales Revenue (Period Toggle) -->
                <div class="dash-card">
                    <div class="dash-card-title chart-header-row">
                        <span class="chart-title-nowrap">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px;color:#53C5E0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            Sales Revenue
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
                        <span id="dash-year-month" class="chart-filter-group">
                            <select id="dash-chart-month" class="chart-select" style="display:none;" title="Month">
                                <?php foreach (['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $i => $m): ?>
                                <option value="<?php echo $i+1; ?>" <?php echo ($i+1)==date('n')?'selected':''; ?>><?php echo $m; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="dash-chart-year" class="chart-select" title="Year">
                                <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y==date('Y')?'selected':''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </span>
                        </div>
                    </div>
                    <div class="chart-wrap" id="dash-sales-chart-wrap">
                        <div class="chart-loading" id="dash-sales-loading">
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

            <!-- Category Sales + Top Performers -->
            <div class="dash-grid">
                <!-- Sales by Product Category -->
                <div class="dash-card">
                    <div class="dash-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        Sales by Product Category
                    </div>
                    <?php if (!empty($category_sales)): ?>
                    <div style="position:relative; height:200px; margin-bottom:16px;"><canvas id="categoryChart"></canvas></div>
                    <div style="font-size:13px;">
                        <?php foreach ($category_sales as $cs):
                            $pct = $cat_total_sum > 0 ? round(((float)$cs['total'] / $cat_total_sum) * 100) : 0;
                        ?>
                        <div style="display:flex; justify-content:space-between; padding:6px 8px; border-radius:6px; margin-bottom:2px;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                            <span><?php echo htmlspecialchars($cs['category'] ?? 'Uncategorized'); ?></span>
                            <span style="font-weight:600;">₱<?php echo number_format((float)$cs['total'], 2); ?> (<?php echo $pct; ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div style="text-align:center; color:#9ca3af; padding:40px 0; font-size:13px;">No product sales data yet</div>
                    <?php endif; ?>
                </div>

                <!-- Top Performers (Interchangeable) -->
                <div class="dash-card" x-data="{ tab: 'products' }">
                    <div class="dash-card-title" style="justify-content: space-between;">
                        <span style="display: flex; align-items: center; gap: 8px;">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            Top Performers
                        </span>
                        <div style="display: flex; gap: 4px; background: #f3f4f6; padding: 4px; border-radius: 8px;">
                            <button @click="tab = 'products'" :style="tab === 'products' ? 'background:#fff; box-shadow:0 1px 2px rgba(0,0,0,0.05); color:#00232b;' : 'color:#6b7280;'" style="padding:4px 12px; font-size:12px; font-weight:600; border-radius:6px; border:none; cursor:pointer; transition:all 0.2s;">Products</button>
                            <button @click="tab = 'customers'" :style="tab === 'customers' ? 'background:#fff; box-shadow:0 1px 2px rgba(0,0,0,0.05); color:#00232b;' : 'color:#6b7280;'" style="padding:4px 12px; font-size:12px; font-weight:600; border-radius:6px; border:none; cursor:pointer; transition:all 0.2s;">Customers</button>
                        </div>
                    </div>

                    <!-- Products Tab -->
                    <div x-show="tab === 'products'">
                        <?php if (!empty($top_products)): ?>
                        <table class="mini-table">
                            <thead><tr><th>#</th><th>Product</th><th>Qty Sold</th><th style="text-align:right;">Revenue</th></tr></thead>
                            <tbody>
                                <?php foreach ($top_products as $i => $tp): ?>
                                <tr>
                                    <td style="font-weight:700; color:#9ca3af;"><?php echo $i + 1; ?></td>
                                    <td style="font-weight:600;" title="<?php echo htmlspecialchars($tp['product_name']); ?>">
                                        <?php echo mb_strlen($tp['product_name']) > 25 ? htmlspecialchars(mb_substr($tp['product_name'], 0, 25)) . '...' : htmlspecialchars($tp['product_name']); ?>
                                        <div style="font-size:10px; color:#9ca3af;"><?php echo htmlspecialchars($tp['sku'] ?? ''); ?></div>
                                    </td>
                                    <td><?php echo (int)$tp['qty_sold']; ?></td>
                                    <td style="text-align:right; font-weight:700; color:#059669;">₱<?php echo number_format((float)$tp['revenue'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div style="text-align:center; color:#9ca3af; padding:40px 0; font-size:13px;">No product sales data yet</div>
                        <?php endif; ?>
                    </div>

                    <!-- Customers Tab -->
                    <div x-show="tab === 'customers'" style="display: none;">
                        <?php if (!empty($top_customers)): ?>
                        <table class="mini-table">
                            <thead><tr><th>#</th><th>Customer</th><th>Orders</th><th style="text-align:right;">Spent</th></tr></thead>
                            <tbody>
                                <?php foreach ($top_customers as $i => $tc): ?>
                                <tr>
                                    <td style="font-weight:700; color:#9ca3af;"><?php echo $i + 1; ?></td>
                                    <td style="font-weight:600;"><?php echo htmlspecialchars($tc['name']); ?></td>
                                    <td><?php echo $tc['orders']; ?></td>
                                    <td style="text-align:right; font-weight:700; color:#059669;">₱<?php echo number_format((float)$tc['spent'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div style="text-align:center; color:#9ca3af; padding:40px 0; font-size:13px;">No customer data yet</div>
                        <?php endif; ?>
                    </div>
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
                                    'Completed' => 'badge-green',
                                    'Processing' => 'badge-blue',
                                    'Pending' => 'badge-yellow',
                                    'Ready for Pickup' => 'badge-blue',
                                    'Cancelled' => 'badge-red',
                                    default => 'badge-gray'
                                };
                            ?>
                            <tr>
                                <td style="font-weight:700; color:#00232b;"><?php echo $ro['order_id']; ?></td>
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
                                $pct = $limit > 0 ? ($stock / $limit) * 100 : 0;
                                $barClass = $stock <= 0 ? 'danger' : 'warning';
                            ?>
                            <tr>
                                <td style="font-weight:600;" title="<?php echo htmlspecialchars($ls['material_name']); ?>">
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
(function () {
    var dashCtrl = null;
    var salesFirstFetch = true;
    window.printflowTeardownDashboardCharts = function () {
        if (window.__pfDashRevealIOs && window.__pfDashRevealIOs.length) {
            window.__pfDashRevealIOs.forEach(function (io) {
                try { io.disconnect(); } catch (e) {}
            });
            window.__pfDashRevealIOs = [];
        }
        if (window.__pfDashChartIO) {
            try { window.__pfDashChartIO.disconnect(); } catch (e) {}
            window.__pfDashChartIO = null;
        }
        if (window.__pfDashMainRO) {
            try { window.__pfDashMainRO.disconnect(); } catch (e) {}
            window.__pfDashMainRO = null;
        }
        if (window.__pfDashScrollKick) {
            try { window.removeEventListener('resize', window.__pfDashScrollKick); } catch (e) {}
            window.__pfDashScrollKick = null;
        }
        if (window.__pfDashScrollSettledHandler) {
            var mc0 = document.querySelector('.main-content');
            if (mc0) {
                try { mc0.removeEventListener('scroll', window.__pfDashScrollSettledHandler); } catch (e) {}
            }
            window.__pfDashScrollSettledHandler = null;
        }
        if (window.__pfDashScrollSettleTimer) {
            try { clearTimeout(window.__pfDashScrollSettleTimer); } catch (e) {}
            window.__pfDashScrollSettleTimer = null;
        }
        if (window.__pfDashLayoutTimer) {
            try { clearTimeout(window.__pfDashLayoutTimer); } catch (e) {}
            window.__pfDashLayoutTimer = null;
        }
        if (dashCtrl) {
            try { dashCtrl.abort(); } catch (e) {}
            dashCtrl = null;
        }
        salesFirstFetch = true;
        if (window.__pfDashSalesChart) {
            try { window.__pfDashSalesChart.destroy(); } catch (e) {}
            window.__pfDashSalesChart = null;
        }
        if (window.__pfDashStatusChart) {
            try { window.__pfDashStatusChart.destroy(); } catch (e) {}
            window.__pfDashStatusChart = null;
        }
        if (window.__pfDashCategoryChart) {
            try { window.__pfDashCategoryChart.destroy(); } catch (e) {}
            window.__pfDashCategoryChart = null;
        }
    };
    window.printflowInitDashboardCharts = function () {
        if (!document.getElementById('salesChart')) return;
        if (typeof Chart === 'undefined') {
            setTimeout(function () {
                if (typeof window.printflowInitDashboardCharts === 'function') window.printflowInitDashboardCharts();
            }, 40);
            return;
        }
        window.printflowTeardownDashboardCharts();
        window.__pfDashRevealIOs = [];
        dashCtrl = new AbortController();
        var sig = { signal: dashCtrl.signal };
        var DASH_BRANCH_ID = <?php echo $branchId !== 'all' ? (int)$branchId : 'null'; ?>;

        var dashAnimLong = 1750;
        var dashAnimShort = 680;
        var doughnutAnim = { animateRotate: true, animateScale: true, duration: 1500 };

        function bindWhenVisible(target, onFirst) {
            if (!target || typeof onFirst !== 'function') return;
            if (typeof IntersectionObserver === 'undefined') {
                requestAnimationFrame(onFirst);
                return;
            }
            var root = document.querySelector('.main-content');
            var fired = false;
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (!en.isIntersecting || fired) return;
                    fired = true;
                    try { io.disconnect(); } catch (e) {}
                    onFirst();
                });
            }, { root: root || null, threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
            io.observe(target);
            window.__pfDashRevealIOs.push(io);
        }

        async function loadSalesChart(period) {
            if (!window.__pfDashSalesChart) return;
            var loadingEl = document.getElementById('dash-sales-loading');
            var noDataEl = document.getElementById('dash-sales-nodata');
            var yearEl = document.getElementById('dash-chart-year');
            var monthEl = document.getElementById('dash-chart-month');
            if (loadingEl) loadingEl.classList.remove('hidden');
            if (noDataEl) noDataEl.classList.remove('visible');
            var year = yearEl ? yearEl.value : new Date().getFullYear();
            var month = monthEl ? monthEl.value : new Date().getMonth() + 1;
            var url = 'api_revenue_chart.php?period=' + encodeURIComponent(period) + '&year=' + encodeURIComponent(year);
            if (period === 'monthly') url += '&month=' + encodeURIComponent(month);
            if (DASH_BRANCH_ID) url += '&branch_id=' + DASH_BRANCH_ID;
            try {
                var resp = await fetch(url, { credentials: 'same-origin', signal: dashCtrl.signal });
                var text = await resp.text();
                var data;
                try { data = JSON.parse(text); } catch (e) {
                    console.error('Chart API returned non-JSON:', text.substring(0, 200));
                    if (noDataEl) { noDataEl.querySelector('span').textContent = 'Failed to load chart data'; noDataEl.classList.add('visible'); }
                    return;
                }
                if (data.error) console.warn('Chart API error:', data.error, data.message || '');
                var labels = data.labels || [];
                var revenue = data.revenue || [];
                var orders = data.orders || [];
                if (!window.__pfDashSalesChart) return;
                window.__pfDashSalesChart.data.labels = labels;
                window.__pfDashSalesChart.data.datasets[0].data = revenue;
                window.__pfDashSalesChart.data.datasets[1].data = orders;
                var dur = salesFirstFetch ? dashAnimLong : dashAnimShort;
                salesFirstFetch = false;
                if (window.__pfDashSalesChart.options && window.__pfDashSalesChart.options.animation) {
                    window.__pfDashSalesChart.options.animation.duration = dur;
                }
                window.__pfDashSalesChart.update();
                requestAnimationFrame(function () {
                    try {
                        if (window.__pfDashSalesChart && typeof window.__pfDashSalesChart.resize === 'function') {
                            window.__pfDashSalesChart.resize();
                        }
                    } catch (e2) {}
                });
                if (noDataEl) noDataEl.classList.toggle('visible', labels.length === 0);
            } catch (e) {
                if (e && e.name === 'AbortError') return;
                console.error('loadSalesChart error:', e);
                if (noDataEl) { noDataEl.querySelector('span').textContent = 'Failed to load chart data'; noDataEl.classList.add('visible'); }
            } finally {
                if (loadingEl) loadingEl.classList.add('hidden');
            }
        }

        function updateChartYearMonthVisibility(period) {
            var wrap = document.getElementById('dash-year-month');
            var monthEl = document.getElementById('dash-chart-month');
            if (!wrap) return;
            wrap.style.display = ['monthly', '6months', 'yearly'].includes(period) ? 'flex' : 'none';
            if (monthEl) monthEl.style.display = period === 'monthly' ? 'inline-block' : 'none';
        }

        function getChartPeriod() {
            var sel = document.getElementById('dash-chart-period');
            return sel ? sel.value : 'monthly';
        }

        document.getElementById('dash-chart-period')?.addEventListener('change', function () {
            var period = getChartPeriod();
            updateChartYearMonthVisibility(period);
            if (window.__pfDashSalesChart) loadSalesChart(period);
        }, sig);
        document.getElementById('dash-chart-year')?.addEventListener('change', function () {
            if (window.__pfDashSalesChart) loadSalesChart(getChartPeriod());
        }, sig);
        document.getElementById('dash-chart-month')?.addEventListener('change', function () {
            if (window.__pfDashSalesChart) loadSalesChart(getChartPeriod());
        }, sig);

        updateChartYearMonthVisibility('monthly');

        bindWhenVisible(document.getElementById('dash-sales-chart-wrap'), function () {
            salesFirstFetch = true;
            window.__pfDashSalesChart = new Chart(document.getElementById('salesChart').getContext('2d'), {
                type: 'line',
                data: { labels: [], datasets: [
                    {
                        label: 'Revenue (₱)',
                        data: [],
                        borderColor: '#00232b',
                        backgroundColor: 'rgba(0,35,43,.08)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#00232b',
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Orders',
                        data: [],
                        borderColor: '#53C5E0',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        tension: 0.35,
                        pointBackgroundColor: '#3A86A8',
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        yAxisID: 'y1'
                    }
                ]},
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: dashAnimLong, easing: 'easeOutQuart' },
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: true, position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: { animation: { duration: 180 }, padding: 10, cornerRadius: 8, displayColors: true }
                    },
                    scales: {
                        y:  { beginAtZero: true, ticks: { font: { size: 11 }, callback: function (v) { return '₱' + v.toLocaleString(); } }, grid: { color: '#f3f4f6' } },
                        y1: { beginAtZero: true, position: 'right', ticks: { font: { size: 11 }, precision: 0 }, grid: { display: false } },
                        x:  { ticks: { font: { size: 10 }, maxRotation: 45 }, grid: { display: false } }
                    }
                }
            });
            loadSalesChart(getChartPeriod());
        });

        <?php if (!empty($order_status)): ?>
        (function () {
            var w = document.getElementById('statusChart') && document.getElementById('statusChart').closest('.chart-wrap');
            bindWhenVisible(w, function () {
                window.__pfDashStatusChart = new Chart(document.getElementById('statusChart').getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(array_map(fn($d) => $d['status'], $order_status)); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_map(fn($d) => (int)$d['cnt'], $order_status)); ?>,
                            backgroundColor: <?php echo json_encode(array_map(fn($d) => $statusColors[$d['status']] ?? '#6B7C85', $order_status)); ?>,
                            borderWidth: 2,
                            borderColor: '#fff',
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        animation: doughnutAnim,
                        plugins: {
                            legend: { position: 'bottom', labels: { padding: 16, boxWidth: 12, font: { size: 12 } } },
                            tooltip: { animation: { duration: 160 }, cornerRadius: 8 }
                        }
                    }
                });
            });
        })();
        <?php endif; ?>

        <?php if (!empty($category_sales)): ?>
        (function () {
            var cv = document.getElementById('categoryChart');
            var w = cv ? cv.parentElement : null;
            var catColors = ['#00232b', '#53C5E0', '#0F4C5C', '#3498DB', '#6C5CE7', '#3A86A8', '#F39C12', '#2ECC71'];
            bindWhenVisible(w, function () {
                window.__pfDashCategoryChart = new Chart(document.getElementById('categoryChart').getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(array_map(fn($c) => $c['category'] ?? 'Uncategorized', $category_sales)); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_map(fn($c) => (float)$c['total'], $category_sales)); ?>,
                            backgroundColor: catColors.slice(0, <?php echo count($category_sales); ?>),
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%',
                        animation: doughnutAnim,
                        plugins: { legend: { display: false }, tooltip: { animation: { duration: 160 }, cornerRadius: 8 } }
                    }
                });
            });
        })();
        <?php endif; ?>

        (function attachDashboardChartLayout() {
            var mainEl = document.querySelector('.main-content');
            function runDashResize() {
                ['__pfDashSalesChart', '__pfDashStatusChart', '__pfDashCategoryChart'].forEach(function (k) {
                    var c = window[k];
                    if (c && typeof c.resize === 'function') {
                        try { c.resize(); } catch (e) {}
                    }
                });
            }
            function debouncedDashResize() {
                if (window.__pfDashLayoutTimer) clearTimeout(window.__pfDashLayoutTimer);
                window.__pfDashLayoutTimer = setTimeout(function () {
                    window.__pfDashLayoutTimer = null;
                    runDashResize();
                }, 240);
            }
            window.__pfDashScrollKick = debouncedDashResize;
            window.addEventListener('resize', debouncedDashResize);
            if (mainEl && typeof ResizeObserver !== 'undefined') {
                window.__pfDashMainRO = new ResizeObserver(function () {
                    debouncedDashResize();
                });
                window.__pfDashMainRO.observe(mainEl);
            }
        })();
    };
})();
</script>
</body>
</html>
