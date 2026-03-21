<?php
/**
 * Staff Reports
 * PrintFlow - Printing Shop PWA
 * Daily Order Summary + Service Orders + Inventory
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

// Get selected date (default: today)
$report_date = $_GET['date'] ?? date('Y-m-d');

// ---- Daily Order Summary (Standard Orders) ----
$daily_orders = db_query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'Pending' OR status = 'Pending Review' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE 0 END), 0) as paid_revenue
    FROM orders 
    WHERE DATE(order_date) = ?
", 's', [$report_date]);
$summary = $daily_orders[0] ?? [];

// ---- Daily Service Orders Summary ----
$service_summary_res = db_query("
    SELECT 
        COUNT(*) as total_sorders,
        SUM(CASE WHEN status = 'Pending Review' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        COALESCE(SUM(total_price), 0) as total_val
    FROM service_orders
    WHERE DATE(created_at) = ?
", 's', [$report_date]);
$s_summary = $service_summary_res[0] ?? [];

// Combine Revenues for Top KPI
$total_paid_revenue = (float)($summary['paid_revenue'] ?? 0) + (float)($s_summary['completed'] > 0 ? $s_summary['total_val'] : 0); // Simplified assumption: completed service orders are paid

// Pagination settings for daily orders list
$items_per_page = 10;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

// Get combined orders for the day with pagination (Standard + Service)
// We'll use a UNION to show both in one list for the day
$day_orders = db_query("
    (SELECT 'Standard' as type, o.order_id as id, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
           o.order_date as created_at, o.total_amount as amount, o.status, o.payment_status
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    WHERE DATE(o.order_date) = ?)
    UNION ALL
    (SELECT 'Service' as type, so.id as id, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
           so.created_at as created_at, so.total_price as amount, so.status, 'N/A' as payment_status
    FROM service_orders so
    LEFT JOIN customers c ON so.customer_id = c.customer_id
    WHERE DATE(so.created_at) = ?)
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
", 'ssii', [$report_date, $report_date, $items_per_page, $offset]);

$total_items_res = db_query("
    SELECT 
        (SELECT COUNT(*) FROM orders WHERE DATE(order_date) = ?) + 
        (SELECT COUNT(*) FROM service_orders WHERE DATE(created_at) = ?) as total
", 'ss', [$report_date, $report_date]);
$total_items = $total_items_res[0]['total'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);

// ---- Raw Materials Inventory Summary ----
$inv_summary_res = db_query("
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN 
            (SELECT SUM(IF(direction='IN', quantity, -quantity)) FROM inventory_transactions WHERE item_id = i.id) < 50 
            THEN 1 ELSE 0 END) as low_stock
    FROM inv_items i
");
$raw_inv_summary = $inv_summary_res[0] ?? [];

// Get Low Stock Raw Materials
$low_stock_raw = db_query("
    SELECT i.name, ic.name as category_name, i.unit_of_measure,
           (SELECT SUM(IF(direction='IN', quantity, -quantity)) FROM inventory_transactions WHERE item_id = i.id) as current_stock
    FROM inv_items i
    LEFT JOIN inv_categories ic ON i.category_id = ic.id
    HAVING current_stock < 50
    ORDER BY current_stock ASC
    LIMIT 10
");

$page_title = 'Reports - Staff';
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
<<<<<<< HEAD
        .rpt-kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        @media (max-width: 1024px) { .rpt-kpi-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px) { .rpt-kpi-grid { grid-template-columns: 1fr; } }
        
        .kpi-box { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; position: relative; overflow: hidden; }
        .kpi-box::after { content: ''; position: absolute; left: 0; bottom: 0; height: 4px; width: 100%; }
        .kpi-indigo::after { background: #6366f1; }
        .kpi-emerald::after { background: #10b981; }
        .kpi-amber::after { background: #f59e0b; }
        .kpi-rose::after { background: #ef4444; }
        
        .kpi-label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #9ca3af; margin-bottom: 8px; letter-spacing: 0.05em; }
        .kpi-value { font-size: 24px; font-weight: 800; color: #1f2937; }
        .kpi-sub { font-size: 12px; color: #6b7280; margin-top: 4px; }

        .chart-container { height: 250px; width: 100%; margin-top: 20px; }
        
        .badge-type { padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .badge-std { background: #eef2ff; color: #4f46e5; }
        .badge-srv { background: #fdf2f7; color: #be185d; }
=======
        .report-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        @media (max-width: 900px) { .report-summary { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 540px) { .report-summary { grid-template-columns: 1fr; } }
        .summary-box { background: #f9fafb; border-radius: 10px; padding: 16px; text-align: center; border: 1px solid #f3f4f6; }
        .summary-box .label { font-size: 12px; color: #9ca3af; margin-bottom: 6px; }
        .summary-box.warn .value { color: #f59e0b; }
        .summary-box.danger .value { color: #ef4444; }
        .summary-box.success .value { color: #10b981; }
        .section-title { font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
>>>>>>> 84f1e77e8bd269bab68461aac6f0ecbbb79114f3
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <div class="main-content">
        <header style="display:flex; justify-content:space-between; align-items:center;">
            <h1 class="page-title">Reports</h1>
            <div style="display:flex; gap:10px;">
                <button onclick="window.print()" class="btn-secondary no-print">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print
                </button>
                <div class="dropdown no-print">
                    <button class="btn-primary" onclick="toggleExportMenu()">Export CSV v</button>
                    <div id="export-menu" style="display:none; position:absolute; right:0; top:45px; background:white; border:1px solid #e5e7eb; border-radius:8px; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1); z-index:100; min-width:180px;">
                        <a href="api/reports_export.php?report=daily_sales&date=<?php echo $report_date; ?>" style="display:block; padding:10px 16px; font-size:13px; color:#374151; text-decoration:none;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">Daily Sales CSV</a>
                        <a href="api/reports_export.php?report=inventory" style="display:block; padding:10px 16px; font-size:13px; color:#374151; text-decoration:none;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">Full Inventory CSV</a>
                    </div>
                </div>
            </div>
        </header>

        <main>
            <!-- Date Filter -->
            <div class="card no-print" style="padding:16px 24px; margin-bottom:24px;">
                <form method="GET" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <label style="margin:0; font-weight:600;">Report Date:</label>
                    <input type="date" name="date" class="input-field" value="<?php echo $report_date; ?>" style="width:auto;">
                    <button type="submit" class="btn-primary">Generate Report</button>
                </form>
            </div>

            <!-- Top KPIs -->
            <div class="rpt-kpi-grid">
                <div class="kpi-box kpi-indigo">
                    <div class="kpi-label">Paid Revenue</div>
                    <div class="kpi-value"><?php echo format_currency($total_paid_revenue); ?></div>
                    <div class="kpi-sub">Total of all paid jobs for today</div>
                </div>
                <div class="kpi-box kpi-emerald">
                    <div class="kpi-label">Completed Jobs</div>
                    <div class="kpi-value"><?php echo (int)$summary['completed'] + (int)$s_summary['completed']; ?></div>
                    <div class="kpi-sub"><?php echo (int)$summary['total_orders'] + (int)$s_summary['total_sorders']; ?> total received</div>
                </div>
                <div class="kpi-box kpi-amber">
                    <div class="kpi-label">Low Stock (Raw)</div>
                    <div class="kpi-value"><?php echo $raw_inv_summary['low_stock'] ?? 0; ?></div>
                    <div class="kpi-sub">Materials below threshold</div>
                </div>
                <div class="kpi-box kpi-rose">
                    <div class="kpi-label">Pending Review</div>
                    <div class="kpi-value"><?php echo (int)$summary['pending'] + (int)$s_summary['pending']; ?></div>
                    <div class="kpi-sub">Needs attention</div>
                </div>
            </div>

            <div class="grid grid-cols-3">
                <!-- Daily Orders List -->
                <div class="card grid-column-span-2" style="grid-column: span 2;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h2 style="font-size:16px; font-weight:700;">Daily Transactions - <?php echo date('M d, Y', strtotime($report_date)); ?></h2>
                    </div>
                    
                    <?php if (!empty($day_orders)): ?>
                    <div class="overflow-x-auto">
                        <table class="text-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Time</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($day_orders as $o): ?>
                                <tr>
                                    <td><span class="badge-type <?php echo $o['type'] === 'Standard' ? 'badge-std' : 'badge-srv'; ?>"><?php echo $o['type']; ?></span></td>
                                    <td style="font-weight:600;">#<?php echo $o['id']; ?></td>
                                    <td><?php echo htmlspecialchars($o['customer_name'] ?: 'Walk-in'); ?></td>
                                    <td style="color:#6b7280;"><?php echo date('h:i A', strtotime($o['created_at'])); ?></td>
                                    <td style="font-weight:700;"><?php echo format_currency($o['amount']); ?></td>
                                    <td><?php echo status_badge($o['status'], 'order'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php echo render_pagination($current_page, $total_pages, ['date' => $report_date]); ?>
                    <?php else: ?>
                    <div style="text-align:center; padding:48px 0; color:#9ca3af;">
                        <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 12px; display:block; opacity:0.3;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        No orders recorded for this date.
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Distribution Chart -->
                <div class="card">
                    <h2 style="font-size:16px; font-weight:700; margin-bottom:20px;">Order Distribution</h2>
                    <div class="chart-container">
                        <canvas id="distChart"></canvas>
                    </div>
                    <div style="margin-top:20px; font-size:12px; color:#6b7280;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                            <span>Standard Orders</span>
                            <span style="font-weight:600; color:#1f2937;"><?php echo (int)$summary['total_orders']; ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span>Service Orders</span>
                            <span style="font-weight:600; color:#1f2937;"><?php echo (int)$s_summary['total_sorders']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Insights -->
            <div class="grid grid-cols-2">
                <!-- Low Stock Products -->
                <div class="card">
                    <h2 style="font-size:16px; font-weight:700; margin-bottom:16px;">Low Stock Finished Products</h2>
                    <?php 
                    $low_products = db_query("SELECT name, stock_quantity FROM products WHERE status = 'Activated' AND stock_quantity < 20 ORDER BY stock_quantity ASC LIMIT 5");
                    if ($low_products):
                    ?>
                    <div class="overflow-x-auto">
                        <table>
                            <thead><tr><th>Product</th><th style="text-align:right;">Stock</th></tr></thead>
                            <tbody>
                                <?php foreach ($low_products as $p): ?>
                                <tr>
                                    <td style="font-weight:500;"><?php echo htmlspecialchars($p['name']); ?></td>
                                    <td style="text-align:right;"><span style="color:#ef4444; font-weight:700;"><?php echo $p['stock_quantity']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p style="text-align:center; padding:20px; color:#10b981; font-size:13px;">✅ All products are well-stocked.</p>
                    <?php endif; ?>
                </div>

                <!-- Low Stock Raw Materials -->
                <div class="card">
                    <h2 style="font-size:16px; font-weight:700; margin-bottom:16px;">Critical Raw Materials</h2>
                    <?php if ($low_stock_raw): ?>
                    <div class="overflow-x-auto">
                        <table>
                            <thead><tr><th>Material</th><th style="text-align:right;">Stock</th></tr></thead>
                            <tbody>
                                <?php foreach ($low_stock_raw as $m): ?>
                                <tr>
                                    <td style="font-weight:500;">
                                        <?php echo htmlspecialchars($m['name']); ?>
                                        <div style="font-size:10px; color:#9ca3af;"><?php echo htmlspecialchars($m['category_name']); ?></div>
                                    </td>
                                    <td style="text-align:right;">
                                        <span style="color:#ef4444; font-weight:700;"><?php echo number_format($m['current_stock'], 1); ?></span>
                                        <span style="font-size:10px; color:#9ca3af;"><?php echo $m['unit_of_measure']; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p style="text-align:center; padding:20px; color:#10b981; font-size:13px;">✅ All materials are well-stocked.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function toggleExportMenu() {
    const menu = document.getElementById('export-menu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

// Close dropdown when clicking outside
window.onclick = function(event) {
    if (!event.target.matches('.btn-primary')) {
        const menu = document.getElementById('export-menu');
        if (menu && menu.style.display === 'block') menu.style.display = 'none';
    }
}

// Distribution Chart
const ctx = document.getElementById('distChart').getContext('2d');
new Chart(ctx, {
    type: 'pie',
    data: {
        labels: ['Standard', 'Service'],
        datasets: [{
            data: [<?php echo (int)$summary['total_orders']; ?>, <?php echo (int)$s_summary['total_sorders']; ?>],
            backgroundColor: ['#6366f1', '#ec4899'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { boxWidth: 12, padding: 20, font: { size: 12, weight: '600' } }
            }
        }
    }
});
</script>

</body>
</html>
