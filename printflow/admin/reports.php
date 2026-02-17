<?php
/**
 * Admin Reports & Analytics Page
 * PrintFlow - Printing Shop PWA
 * Professional reports with real data, charts, and per-report CSV export
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$current_user = get_logged_in_user();

// ── Date range ─────────────────────────────────────────
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');
$from = date('Y-m-d', strtotime($from));
$to   = date('Y-m-d', strtotime($to));

// ── Sales Summary ──────────────────────────────────────
try {
    $sales = db_query(
        "SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN payment_status='Paid' THEN total_amount ELSE 0 END) as revenue,
            SUM(CASE WHEN payment_status='Paid' THEN 1 ELSE 0 END) as paid,
            AVG(CASE WHEN payment_status='Paid' THEN total_amount ELSE NULL END) as avg_val
         FROM orders WHERE order_date BETWEEN ? AND ?",
        'ss', [$from, $to . ' 23:59:59']
    );
    $s = $sales[0] ?? [];
} catch (Exception $e) { $s = []; }

$total_orders = (int)($s['total_orders'] ?? 0);
$revenue      = (float)($s['revenue'] ?? 0);
$paid_orders  = (int)($s['paid'] ?? 0);
$avg_val      = (float)($s['avg_val'] ?? 0);

// ── Order Status Breakdown ─────────────────────────────
try {
    $status_data = db_query(
        "SELECT status, COUNT(*) as cnt FROM orders 
         WHERE order_date BETWEEN ? AND ? GROUP BY status",
        'ss', [$from, $to . ' 23:59:59']
    ) ?: [];
} catch (Exception $e) { $status_data = []; }

// ── Daily Revenue (for chart) ──────────────────────────
try {
    $daily_rev = db_query(
        "SELECT DATE(order_date) as day, SUM(total_amount) as total, COUNT(*) as cnt
         FROM orders WHERE order_date BETWEEN ? AND ? AND payment_status='Paid'
         GROUP BY DATE(order_date) ORDER BY day",
        'ss', [$from, $to . ' 23:59:59']
    ) ?: [];
} catch (Exception $e) { $daily_rev = []; }

// ── Top Customers ──────────────────────────────────────
try {
    $top_customers = db_query(
        "SELECT CONCAT(c.first_name, ' ', c.last_name) as name, c.email,
                COUNT(o.order_id) as orders, SUM(o.total_amount) as spent
         FROM customers c
         JOIN orders o ON c.customer_id = o.customer_id
         WHERE o.order_date BETWEEN ? AND ?
         GROUP BY c.customer_id ORDER BY spent DESC LIMIT 10",
        'ss', [$from, $to . ' 23:59:59']
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

// ── Recent Orders ──────────────────────────────────────
try {
    $recent_orders = db_query(
        "SELECT o.order_id, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                o.order_date, o.total_amount, o.payment_status, o.status
         FROM orders o LEFT JOIN customers c ON o.customer_id = c.customer_id
         WHERE o.order_date BETWEEN ? AND ?
         ORDER BY o.order_date DESC LIMIT 15",
        'ss', [$from, $to . ' 23:59:59']
    ) ?: [];
} catch (Exception $e) { $recent_orders = []; }

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
    <style>
        /* ─── Report Styles ─── */
        .rpt-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
        @media (max-width:900px) { .rpt-grid { grid-template-columns:1fr; } }

        .rpt-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:0; overflow:hidden; }
        .rpt-card-header { display:flex; align-items:center; justify-content:space-between; padding:18px 22px 14px; border-bottom:1px solid #f3f4f6; }
        .rpt-card-header h3 { font-size:15px; font-weight:700; color:#1f2937; display:flex; align-items:center; gap:8px; margin:0; }
        .rpt-card-header .header-icon { width:18px; height:18px; color:#6366f1; flex-shrink:0; }
        .rpt-card-body { padding:18px 22px 22px; }

        .export-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; font-size:12px; font-weight:600; color:#374151; cursor:pointer; transition:all .2s; text-decoration:none; }
        .export-btn:hover { background:#eef2ff; border-color:#c7d2fe; color:#4f46e5; }
        .export-btn svg { width:14px; height:14px; }

        /* ─── Date Filter ─── */
        .date-filter { display:inline-flex; align-items:center; gap:10px; padding:10px 18px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; margin-bottom:24px; flex-wrap:wrap; }
        .date-filter label { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#6b7280; }
        .date-filter input[type=date] { padding:6px 10px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; background:#fff; }
        .date-filter input[type=date]:focus { outline:none; border-color:#6366f1; }
        .date-filter button { padding:7px 18px; background:#6366f1; color:#fff; border:none; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:background .2s; }
        .date-filter button:hover { background:#4f46e5; }

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

        /* ─── Full Width ─── */
        .rpt-full { grid-column:1/-1; }

        .no-data { text-align:center; padding:32px 16px; color:#9ca3af; font-size:13px; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <header>
            <h1 class="page-title">Reports & Analytics</h1>
        </header>

        <main>
            <!-- Date Range Filter -->
            <form method="GET" class="date-filter">
                <label>From</label>
                <input type="date" name="from" value="<?php echo $from; ?>">
                <label>To</label>
                <input type="date" name="to" value="<?php echo $to; ?>">
                <button type="submit">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline; vertical-align:-2px; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Apply
                </button>
            </form>

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
                <div class="rpt-card">
                    <div class="rpt-card-header">
                        <h3>
                            <svg class="header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            Sales Revenue
                        </h3>
                        <a href="reports_export?report=sales&from=<?php echo $from; ?>&to=<?php echo $to; ?>" class="export-btn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            CSV
                        </a>
                    </div>
                    <div class="rpt-card-body">
                        <?php if (empty($daily_rev)): ?>
                            <div class="no-data">No sales data for this period</div>
                        <?php else: ?>
                            <div class="chart-wrap"><canvas id="salesChart"></canvas></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ═══ ORDER STATUS BREAKDOWN ═══ -->
                <div class="rpt-card">
                    <div class="rpt-card-header">
                        <h3>
                            <svg class="header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Order Status
                        </h3>
                        <a href="reports_export?report=orders&from=<?php echo $from; ?>&to=<?php echo $to; ?>" class="export-btn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            CSV
                        </a>
                    </div>
                    <div class="rpt-card-body">
                        <?php if (empty($status_data)): ?>
                            <div class="no-data">No orders in this period</div>
                        <?php else: ?>
                            <div class="chart-wrap"><canvas id="statusChart"></canvas></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ═══ TOP CUSTOMERS ═══ -->
                <div class="rpt-card">
                    <div class="rpt-card-header">
                        <h3>
                            <svg class="header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Top Customers
                        </h3>
                        <a href="reports_export?report=customers&from=<?php echo $from; ?>&to=<?php echo $to; ?>" class="export-btn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            CSV
                        </a>
                    </div>
                    <div class="rpt-card-body">
                        <?php if (empty($top_customers)): ?>
                            <div class="no-data">No customer data for this period</div>
                        <?php else: ?>
                        <div style="overflow-x:auto;">
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
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ═══ INVENTORY ALERTS ═══ -->
                <div class="rpt-card">
                    <div class="rpt-card-header">
                        <h3>
                            <svg class="header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            Inventory Alerts
                        </h3>
                        <a href="reports_export?report=inventory&from=<?php echo $from; ?>&to=<?php echo $to; ?>" class="export-btn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            CSV
                        </a>
                    </div>
                    <div class="rpt-card-body">
                        <?php if (empty($low_stock)): ?>
                            <div class="no-data" style="color:#059669;">
                                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 8px; display:block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                All stock levels are healthy!
                            </div>
                        <?php else: ?>
                        <div style="overflow-x:auto;">
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
                                        <td style="font-weight:600;">
                                            <?php echo htmlspecialchars($ls['material_name']); ?>
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
                <div class="rpt-card rpt-full">
                    <div class="rpt-card-header">
                        <h3>
                            <svg class="header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Recent Transactions
                        </h3>
                        <a href="reports_export?report=sales&from=<?php echo $from; ?>&to=<?php echo $to; ?>" class="export-btn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Export All
                        </a>
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
                                        <td style="font-weight:700; color:#6366f1;">#<?php echo $ro['order_id']; ?></td>
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
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /.rpt-grid -->
        </main>
    </div>
</div>

<script>
// ─── Sales Revenue Line Chart ─────────────────────────
<?php if (!empty($daily_rev)): ?>
const salesCtx = document.getElementById('salesChart').getContext('2d');
new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(fn($d) => date('M d', strtotime($d['day'])), $daily_rev)); ?>,
        datasets: [{
            label: 'Revenue (₱)',
            data: <?php echo json_encode(array_map(fn($d) => (float)$d['total'], $daily_rev)); ?>,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,.08)',
            borderWidth: 2.5,
            fill: true,
            tension: 0.35,
            pointBackgroundColor: '#6366f1',
            pointRadius: 3,
            pointHoverRadius: 5
        }, {
            label: 'Orders',
            data: <?php echo json_encode(array_map(fn($d) => (int)$d['cnt'], $daily_rev)); ?>,
            borderColor: '#10b981',
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [5, 3],
            tension: 0.35,
            pointRadius: 2,
            pointHoverRadius: 4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 12, font: { size: 11 } } } },
        scales: {
            y: { beginAtZero: true, ticks: { font: { size: 11 }, callback: v => '₱' + v.toLocaleString() }, grid: { color: '#f3f4f6' } },
            y1: { beginAtZero: true, position: 'right', ticks: { font: { size: 11 }, precision: 0 }, grid: { display: false } },
            x: { ticks: { font: { size: 10 }, maxRotation: 45 }, grid: { display: false } }
        }
    }
});
<?php endif; ?>

// ─── Order Status Doughnut Chart ──────────────────────
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
</script>

</body>
</html>
