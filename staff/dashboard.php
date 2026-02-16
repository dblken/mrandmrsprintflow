<?php
/**
 * Staff Dashboard
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require staff access
require_role('Staff');

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

$page_title = 'Staff Dashboard - PrintFlow';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4">
        <!-- Welcome Section -->
        <div class="card mb-8">
            <h1 class="text-3xl font-bold mb-2">Staff Dashboard</h1>
            <p class="text-gray-600">Welcome, <?php echo htmlspecialchars($current_user['first_name']); ?>!</p>
        </div>

        <!-- Order Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="card border-l-4 border-yellow-500">
                <p class="text-gray-600 text-sm mb-1">Pending Orders</p>
                <p class="text-3xl font-bold text-yellow-600"><?php echo $pending_orders; ?></p>
                <p class="text-xs text-gray-500 mt-2">Awaiting processing</p>
            </div>

            <div class="card border-l-4 border-blue-500">
                <p class="text-gray-600 text-sm mb-1">Processing</p>
                <p class="text-3xl font-bold text-blue-600"><?php echo $processing_orders; ?></p>
                <p class="text-xs text-gray-500 mt-2">Currently in progress</p>
            </div>

            <div class="card border-l-4 border-green-500">
                <p class="text-gray-600 text-sm mb-1">Ready for Pickup</p>
                <p class="text-3xl font-bold text-green-600"><?php echo $ready_orders; ?></p>
                <p class="text-xs text-gray-500 mt-2">Ready for customers</p>
            </div>

            <div class="card border-l-4 border-gray-500">
                <p class="text-gray-600 text-sm mb-1">Completed Today</p>
                <p class="text-3xl font-bold text-gray-600"><?php echo $today_completed; ?></p>
                <p class="text-xs text-gray-500 mt-2">Orders finished today</p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <a href="orders.php" class="card text-center hover:shadow-lg transition cursor-pointer">
                <div class="text-4xl mb-3">📦</div>
                <h3 class="font-bold text-lg mb-2">Manage Orders</h3>
                <p class="text-gray-600 text-sm">View and update order status</p>
            </a>

            <a href="products.php" class="card text-center hover:shadow-lg transition cursor-pointer">
                <div class="text-4xl mb-3">📦</div>
                <h3 class="font-bold text-lg mb-2">View Products</h3>
                <p class="text-gray-600 text-sm">Check inventory levels</p>
            </a>

            <a href="notifications.php" class="card text-center hover:shadow-lg transition cursor-pointer">
                <div class="text-4xl mb-3">🔔</div>
                <h3 class="font-bold text-lg mb-2">Notifications</h3>
                <p class="text-gray-600 text-sm">View system alerts</p>
            </a>
        </div>

        <!-- Recent Orders -->
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold">Recent Orders</h2>
                <a href="orders.php" class="text-indigo-600 hover:text-indigo-700 font-medium text-sm">View All →</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3">Order #</th>
                            <th class="text-left py-3">Customer</th>
                            <th class="text-left py-3">Date</th>
                            <th class="text-left py-3">Amount</th>
                            <th class="text-left py-3">Status</th>
                            <th class="text-left py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 font-medium">#<?php echo $order['order_id']; ?></td>
                                <td class="py-3"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td class="py-3"><?php echo format_date($order['order_date']); ?></td>
                                <td class="py-3"><?php echo format_currency($order['total_amount']); ?></td>
                                <td class="py-3"><?php echo status_badge($order['status'], 'order'); ?></td>
                                <td class="py-3">
                                    <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">Update</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
