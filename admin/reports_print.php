<?php
/**
 * PrintFlow — Print-Optimized Report Page
 * Clean, professional layout for printing (Orders Status, Sales, Customers)
 */

error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

require_role(['Admin', 'Manager']);

$report   = $_GET['report'] ?? 'orders';
$from     = $_GET['from'] ?? date('Y-m-01');
$to       = $_GET['to'] ?? date('Y-m-d');

$branchCtx = init_branch_context(false);
$branchId  = $branchCtx['selected_branch_id'];
$branchName = $branchCtx['branch_name'];

$from = date('Y-m-d', strtotime($from));
$to   = date('Y-m-d', strtotime($to));
$toEnd = $to . ' 23:59:59';

[$bSql, $bTypes, $bParams] = branch_where_parts('o', $branchId);

$reportTitle = $report === 'customers' ? 'Customers Report' : ($report === 'sales' ? 'Sales Report' : 'Orders Status Report');

$isCustomers = ($report === 'customers');

if ($isCustomers) {
    if ($branchId !== 'all') {
        [$totalCust, $activeCust] = branch_customers_summary_for_branch((int)$branchId);
        $customers = branch_customers_report_list((int)$branchId);
    } else {
        $cust_summary = db_query("SELECT COUNT(*) as total, SUM(CASE WHEN status='Activated' THEN 1 ELSE 0 END) as active FROM customers");
        $cs = $cust_summary[0] ?? [];
        $totalCust = (int)($cs['total'] ?? 0);
        $activeCust = (int)($cs['active'] ?? 0);
        $customers = db_query(
            "SELECT c.customer_id, CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,'')) as name,
                    COALESCE(c.email,'') as email, COALESCE(c.contact_number,'') as contact_number, c.status, c.created_at,
                    COUNT(o.order_id) as order_count, COALESCE(SUM(o.total_amount), 0) as total_spent
             FROM customers c
             LEFT JOIN orders o ON c.customer_id = o.customer_id
             GROUP BY c.customer_id
             ORDER BY total_spent DESC"
        ) ?: [];
    }
    $totalSpentSum = array_sum(array_map(fn($c)=>(float)($c['total_spent']??0), $customers));
} else {
    $summary = db_query(
        "SELECT COUNT(*) as total_orders, SUM(o.total_amount) as total_revenue,
                AVG(o.total_amount) as avg_order_value
         FROM orders o WHERE o.order_date BETWEEN ? AND ?$bSql",
        'ss'.$bTypes, array_merge([$from, $toEnd], $bParams)
    );
    $sum = $summary[0] ?? [];
    $grandTotalOrd = (int)($sum['total_orders'] ?? 0);
    $grandTotalRev = (float)($sum['total_revenue'] ?? 0);
    $avgOrderVal = (float)($sum['avg_order_value'] ?? 0);
    $status_counts = db_query(
        "SELECT o.status, COUNT(*) as cnt, SUM(o.total_amount) as total
         FROM orders o WHERE o.order_date BETWEEN ? AND ?$bSql
         GROUP BY o.status ORDER BY cnt DESC",
        'ss'.$bTypes, array_merge([$from, $toEnd], $bParams)
    ) ?: [];
    $daily = db_query(
        "SELECT DATE(o.order_date) as day, COUNT(*) as cnt, SUM(o.total_amount) as total
         FROM orders o WHERE o.order_date BETWEEN ? AND ?$bSql
         GROUP BY DATE(o.order_date) ORDER BY day DESC",
        'ss'.$bTypes, array_merge([$from, $toEnd], $bParams)
    ) ?: [];
    $dayAvgOrd = count($daily) > 0 ? array_sum(array_column($daily, 'cnt')) / count($daily) : 0;
    $dayAvgRev = count($daily) > 0 ? array_sum(array_map(fn($d)=>(float)$d['total'], $daily)) / count($daily) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PrintFlow <?php echo htmlspecialchars($reportTitle); ?></title>
<style>
* { box-sizing: border-box; }
body { font-family: Arial, 'Segoe UI', sans-serif; font-size: 13px; color: #222; line-height: 1.4; margin: 0; padding: 24px 32px; background: #fff; }
.report-header { margin-bottom: 28px; padding-bottom: 20px; border-bottom: 2px solid #333; }
.report-title { font-size: 22px; font-weight: 800; color: #111; margin: 0 0 6px; }
.report-meta { font-size: 13px; color: #444; }
.report-meta span { display: inline-block; margin-right: 24px; }
.report-meta strong { color: #333; }
.section { margin-bottom: 28px; }
.section-title { font-size: 14px; font-weight: 700; color: #222; margin: 0 0 12px; text-transform: uppercase; letter-spacing: 0.5px; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
th { background: #f9fafb; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; color: #555; }
th.num, td.num { text-align: right; font-variant-numeric: tabular-nums; }
th.center, td.center { text-align: center; }
th.left, td.left { text-align: left; }
tr.total-row td { font-weight: 700; background: #f3f4f6; border-top: 2px solid #333; padding: 12px 14px; }
tr.avg-row td { font-weight: 600; color: #555; background: #fafafa; }
tr.zebra:nth-child(even) { background: #f9fafb; }
.footer { margin-top: 32px; padding-top: 16px; border-top: 1px solid #d1d5db; font-size: 11px; color: #6b7280; text-align: center; }
@media print {
    body { padding: 16px 24px; font-size: 11px; }
    .no-print { display: none !important; }
    .report-header { break-after: avoid; }
    .section { margin-bottom: 20px; }
    table { font-size: 10px; }
    table thead { display: table-header-group; }
    th, td { padding: 6px 10px; }
    @page { margin: 1.5cm; size: A4; }
}
</style>
</head>
<body>
<div class="report-header">
    <h1 class="report-title">PrintFlow Sales &amp; Analytics Report</h1>
    <p class="report-meta" style="margin: 4px 0 0;">
        <span><strong>Report Type:</strong> <?php echo htmlspecialchars($reportTitle); ?></span><br>
        <span><strong>Branch:</strong> <?php echo htmlspecialchars($branchName); ?></span><br>
        <span><strong>Date Range:</strong> <?php echo date('F j, Y', strtotime($from)); ?> – <?php echo date('F j, Y', strtotime($to)); ?></span><br>
        <span><strong>Generated On:</strong> <?php echo date('F j, Y, g:i A'); ?></span>
    </p>
</div>

<?php if ($isCustomers): ?>
<div class="section">
    <h2 class="section-title">Summary</h2>
    <table class="table-wrap" style="max-width: 400px;">
        <tr><td>Total Customers</td><td class="num"><?php echo number_format($totalCust); ?></td></tr>
        <tr><td>Active Customers</td><td class="num"><?php echo number_format($activeCust); ?></td></tr>
    </table>
</div>
<div class="section">
    <h2 class="section-title">Customer List</h2>
    <table>
        <thead>
            <tr>
                <th>Customer ID</th><th>Name</th><th>Email</th><th>Contact</th>
                <th class="center">Status</th><th class="center">Registered</th><th class="num">Total Orders</th><th class="num">Total Spent (₱)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($customers as $c): ?>
            <tr class="zebra">
                <td class="left"><?php echo (int)$c['customer_id']; ?></td>
                <td class="left"><?php echo htmlspecialchars(trim($c['name'] ?? '')); ?></td>
                <td class="left"><?php echo htmlspecialchars(trim($c['email'] ?? '')); ?></td>
                <td class="left"><?php echo htmlspecialchars(trim($c['contact_number'] ?? '')); ?></td>
                <td class="center"><?php echo htmlspecialchars($c['status'] ?? ''); ?></td>
                <td class="center"><?php echo date('M j, Y', strtotime($c['created_at'])); ?></td>
                <td class="num"><?php echo number_format((int)($c['order_count'] ?? 0)); ?></td>
                <td class="num"><?php echo number_format((float)($c['total_spent'] ?? 0), 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="7" class="left">TOTAL</td>
                <td class="num">₱<?php echo number_format($totalSpentSum, 2); ?></td>
            </tr>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="section">
    <h2 class="section-title">Summary</h2>
    <table class="table-wrap" style="max-width: 400px;">
        <tr><td>Total Orders</td><td class="num"><?php echo number_format($grandTotalOrd); ?></td></tr>
        <tr><td>Total Revenue</td><td class="num">₱<?php echo number_format($grandTotalRev, 2); ?></td></tr>
        <tr><td>Average Order Value</td><td class="num">₱<?php echo number_format($avgOrderVal, 2); ?></td></tr>
    </table>
</div>

<div class="section">
    <h2 class="section-title">Status Breakdown</h2>
    <table>
        <thead>
            <tr><th>Status</th><th class="num">Total Orders</th><th class="num">Total Amount (₱)</th></tr>
        </thead>
        <tbody>
            <?php foreach ($status_counts as $sc): ?>
            <tr>
                <td><?php echo htmlspecialchars($sc['status']); ?></td>
                <td class="num"><?php echo number_format((int)$sc['cnt']); ?></td>
                <td class="num"><?php echo number_format((float)$sc['total'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td>TOTAL</td>
                <td class="num"><?php echo number_format($grandTotalOrd); ?></td>
                <td class="num">₱<?php echo number_format($grandTotalRev, 2); ?></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="section">
    <h2 class="section-title">Daily Order Summary</h2>
    <table>
        <thead>
            <tr><th>Date</th><th class="num">Number of Orders</th><th class="num">Revenue (₱)</th></tr>
        </thead>
        <tbody>
            <?php foreach ($daily as $d): ?>
            <tr>
                <td><?php echo date('M j, Y', strtotime($d['day'])); ?></td>
                <td class="num"><?php echo (int)$d['cnt']; ?></td>
                <td class="num"><?php echo number_format((float)$d['total'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (count($daily) > 0): ?>
            <tr class="avg-row">
                <td>Daily Average</td>
                <td class="num"><?php echo number_format($dayAvgOrd, 1); ?></td>
                <td class="num">₱<?php echo number_format($dayAvgRev, 2); ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="footer">
    Generated by PrintFlow System &nbsp;·&nbsp; <?php echo date('F j, Y g:i A'); ?>
</div>

<div class="no-print" style="margin-top: 24px; text-align: center;">
    <button onclick="window.print()" style="padding: 10px 24px; font-size: 14px; font-weight: 600; background: #0d9488; color: #fff; border: none; border-radius: 8px; cursor: pointer;">Print Report</button>
</div>

<script>
window.onload = function() {
    if (window.location.search.includes('autoprint=1')) window.print();
};
</script>
</body>
</html>
