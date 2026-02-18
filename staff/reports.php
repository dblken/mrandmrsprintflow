<?php
/**
 * Staff Reports (Limited)
 * PrintFlow - Printing Shop PWA
 * Daily order summary + inventory list (no CSV export)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

// Get selected date (default: today)
$report_date = $_GET['date'] ?? date('Y-m-d');

// ---- Daily Order Summary ----
$daily_orders = db_query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'Ready for Pickup' THEN 1 ELSE 0 END) as ready,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE 0 END), 0) as paid_revenue
    FROM orders 
    WHERE DATE(order_date) = ?
", 's', [$report_date]);
$summary = $daily_orders[0] ?? [];

// Get orders for the day
$day_orders = db_query("
    SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    WHERE DATE(o.order_date) = ?
    ORDER BY o.order_date DESC
", 's', [$report_date]);

// ---- Simple Inventory List ----
$low_stock_products = db_query("
    SELECT name, sku, category, stock_quantity, price 
    FROM products 
    WHERE status = 'Activated' AND stock_quantity < 20
    ORDER BY stock_quantity ASC
    LIMIT 30
");

$all_products_summary = db_query("
    SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN stock_quantity < 10 THEN 1 ELSE 0 END) as critical_stock,
        SUM(CASE WHEN stock_quantity >= 10 AND stock_quantity < 20 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN stock_quantity >= 20 THEN 1 ELSE 0 END) as in_stock
    FROM products WHERE status = 'Activated'
");
$inv_summary = $all_products_summary[0] ?? [];

$page_title = 'Reports - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .report-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        @media (max-width: 900px) { .report-summary { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 540px) { .report-summary { grid-template-columns: 1fr; } }
        .summary-box { background: #f9fafb; border-radius: 10px; padding: 16px; text-align: center; border: 1px solid #f3f4f6; }
        .summary-box .label { font-size: 12px; color: #9ca3af; margin-bottom: 6px; }
        .summary-box .value { font-size: 24px; font-weight: 700; color: #1f2937; }
        .summary-box.warn .value { color: #f59e0b; }
        .summary-box.danger .value { color: #ef4444; }
        .summary-box.success .value { color: #10b981; }
        .section-title { font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <div class="main-content">
        <header>
            <h1 class="page-title">Reports</h1>
        </header>

        <main>
            <!-- Daily Order Summary -->
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                    <h2 class="section-title" style="margin-bottom:0;">📋 Daily Order Summary</h2>
                    <form method="GET" style="display:flex; gap:10px; align-items:center;">
                        <input type="date" name="date" class="input-field" value="<?php echo htmlspecialchars($report_date); ?>" style="width:auto;">
                        <button type="submit" class="btn-primary" style="padding:8px 16px;">View</button>
                    </form>
                </div>

                <div class="report-summary">
                    <div class="summary-box">
                        <div class="label">Total Orders</div>
                        <div class="value"><?php echo $summary['total_orders'] ?? 0; ?></div>
                    </div>
                    <div class="summary-box success">
                        <div class="label">Completed</div>
                        <div class="value"><?php echo $summary['completed'] ?? 0; ?></div>
                    </div>
                    <div class="summary-box warn">
                        <div class="label">Pending / Processing</div>
                        <div class="value"><?php echo ($summary['pending'] ?? 0) + ($summary['processing'] ?? 0); ?></div>
                    </div>
                    <div class="summary-box">
                        <div class="label">Revenue (Paid)</div>
                        <div class="value"><?php echo format_currency($summary['paid_revenue'] ?? 0); ?></div>
                    </div>
                </div>

                <!-- Orders Table -->
                <?php if (!empty($day_orders)): ?>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Time</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($day_orders as $do): ?>
                            <tr>
                                <td style="font-weight:500;">#<?php echo $do['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($do['customer_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('h:i A', strtotime($do['order_date'])); ?></td>
                                <td style="font-weight:600;"><?php echo format_currency($do['total_amount']); ?></td>
                                <td><?php echo status_badge($do['status'], 'order'); ?></td>
                                <td><?php echo status_badge($do['payment_status'], 'order'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="text-align:center; padding:32px; color:#9ca3af; font-size:14px;">
                    No orders found for <?php echo date('M j, Y', strtotime($report_date)); ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Inventory Summary -->
            <div class="card">
                <h2 class="section-title">📦 Inventory Summary</h2>

                <div class="report-summary">
                    <div class="summary-box">
                        <div class="label">Total Products</div>
                        <div class="value"><?php echo $inv_summary['total_products'] ?? 0; ?></div>
                    </div>
                    <div class="summary-box success">
                        <div class="label">In Stock (20+)</div>
                        <div class="value"><?php echo $inv_summary['in_stock'] ?? 0; ?></div>
                    </div>
                    <div class="summary-box warn">
                        <div class="label">Low Stock (10-19)</div>
                        <div class="value"><?php echo $inv_summary['low_stock'] ?? 0; ?></div>
                    </div>
                    <div class="summary-box danger">
                        <div class="label">Critical (&lt;10)</div>
                        <div class="value"><?php echo $inv_summary['critical_stock'] ?? 0; ?></div>
                    </div>
                </div>

                <?php if (!empty($low_stock_products)): ?>
                <h3 style="font-size:14px; font-weight:600; margin-bottom:12px; color:#6b7280;">⚠️ Products Below 20 Units</h3>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Stock</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock_products as $p): ?>
                            <tr>
                                <td style="font-weight:500;"><?php echo htmlspecialchars($p['name']); ?></td>
                                <td style="font-family:monospace; font-size:12px;"><?php echo htmlspecialchars($p['sku']); ?></td>
                                <td><?php echo htmlspecialchars($p['category']); ?></td>
                                <td>
                                    <?php if ($p['stock_quantity'] < 10): ?>
                                        <span style="color:#ef4444; font-weight:700;"><?php echo $p['stock_quantity']; ?></span>
                                        <span style="font-size:10px; background:#fef2f2; color:#ef4444; padding:2px 6px; border-radius:4px; font-weight:600;">CRITICAL</span>
                                    <?php else: ?>
                                        <span style="color:#f59e0b; font-weight:600;"><?php echo $p['stock_quantity']; ?></span>
                                        <span style="font-size:10px; background:#fffbeb; color:#f59e0b; padding:2px 6px; border-radius:4px; font-weight:600;">LOW</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:600;"><?php echo format_currency($p['price']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="text-align:center; padding:24px; color:#10b981; font-size:14px;">
                    ✅ All products are well-stocked
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

</body>
</html>
