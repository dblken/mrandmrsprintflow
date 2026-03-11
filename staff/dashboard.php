<?php
/**
 * Staff Dashboard
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require staff access
require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

$current_user = get_logged_in_user();

// Get dashboard statistics
$pending_orders_result = db_query("SELECT COUNT(*) as count FROM orders WHERE status = 'Pending'");
$pending_orders = $pending_orders_result[0]['count'] ?? 0;

$processing_orders_result = db_query("SELECT COUNT(*) as count FROM orders WHERE status = 'Processing'");
$processing_orders = $processing_orders_result[0]['count'] ?? 0;

$ready_orders_result = db_query("SELECT COUNT(*) as count FROM orders WHERE status = 'Ready for Pickup'");
$ready_orders = $ready_orders_result[0]['count'] ?? 0;

// Get today's completed orders
$today_completed_result = db_query("
    SELECT COUNT(*) as count FROM orders 
    WHERE status = 'Completed' AND DATE(order_date) = CURDATE()
");
$today_completed = $today_completed_result[0]['count'] ?? 0;

// Get recent orders
$recent_orders = db_query("
    SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name 
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.customer_id 
    ORDER BY o.order_date DESC 
    LIMIT 10
");

// Get low-stock products for inventory alerts (view-only)
$low_stock = db_query("
    SELECT name, sku, stock_quantity, category 
    FROM products 
    WHERE status = 'Activated' AND stock_quantity < 10 
    ORDER BY stock_quantity ASC LIMIT 5
");

$page_title = 'Staff Dashboard - PrintFlow';
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
        .stat-label { font-size: 13px; color: #9ca3af; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .stat-value { font-size: 32px; font-weight: 700; color: #1f2937; margin-bottom: 4px; }
        .stat-sub { font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <span style="font-size:14px; color:#6b7280;">Welcome, <?php echo htmlspecialchars($current_user['first_name']); ?>!</span>
            </div>
            <a href="pos.php" class="btn-primary" style="display:flex; align-items:center; gap:8px; text-decoration:none;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Launch POS
            </a>
        </header>

        <main>
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">⏳ Pending Orders</div>
                    <div class="stat-value"><?php echo $pending_orders; ?></div>
                    <div class="stat-sub">Awaiting processing</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">🔄 Processing</div>
                    <div class="stat-value"><?php echo $processing_orders; ?></div>
                    <div class="stat-sub">Currently in progress</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">✅ Ready for Pickup</div>
                    <div class="stat-value"><?php echo $ready_orders; ?></div>
                    <div class="stat-sub">Ready for customers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">📦 Completed Today</div>
                    <div class="stat-value"><?php echo $today_completed; ?></div>
                    <div class="stat-sub">Orders finished today</div>
                </div>
            </div>

            <!-- Inventory Alerts (View-Only) -->
            <?php if (!empty($low_stock)): ?>
            <div class="card" style="border-left:4px solid #f59e0b;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <h2 style="display:flex; align-items:center; gap:8px;">⚠️ Low Stock Alerts</h2>
                    <a href="products" style="color:#10b981; font-size:13px; font-weight:500; text-decoration:none;">View All →</a>
                </div>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock as $item): ?>
                            <tr>
                                <td style="font-weight:500;"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td style="font-family:monospace; font-size:12px;"><?php echo htmlspecialchars($item['sku']); ?></td>
                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                <td><span style="color:#ef4444; font-weight:700;"><?php echo $item['stock_quantity']; ?></span> <span style="font-size:10px; background:#fef2f2; color:#ef4444; padding:2px 6px; border-radius:4px; font-weight:600;">CRITICAL</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Orders -->
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h2>Recent Orders</h2>
                    <a href="orders" style="color:#10b981; font-size:13px; font-weight:500; text-decoration:none;">View All →</a>
                </div>

                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td style="font-weight:500;">#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo format_date($order['order_date']); ?></td>
                                    <td style="font-weight:600;"><?php echo format_currency($order['total_amount']); ?></td>
                                    <td><?php echo status_badge($order['status'], 'order'); ?></td>
                                    <td>
                                        <a href="order_details.php?id=<?php echo $order['order_id']; ?>" style="color:#10b981; font-size:13px; font-weight:500; text-decoration:none;">Update</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

</body>
</html>
