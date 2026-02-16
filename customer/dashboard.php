<?php
/**
 * Customer Dashboard
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require customer access
require_role('Customer');

$customer_id = get_user_id();

// Get order statistics
$total_orders_result = db_query("SELECT COUNT(*) as count FROM orders WHERE customer_id = ?", 'i', [$customer_id]);
$total_orders = $total_orders_result[0]['count'] ?? 0;

$pending_orders_result = db_query("SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status = 'Pending'", 'i', [$customer_id]);
$pending_orders = $pending_orders_result[0]['count'] ?? 0;

$processing_orders_result = db_query("SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status = 'Processing'", 'i', [$customer_id]);
$processing_orders = $processing_orders_result[0]['count'] ?? 0;

$ready_orders_result = db_query("SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status = 'Ready for Pickup'", 'i', [$customer_id]);
$ready_orders = $ready_orders_result[0]['count'] ?? 0;

// Get recent orders
$recent_orders = db_query("
    SELECT * FROM orders 
    WHERE customer_id = ? 
    ORDER BY order_date DESC 
    LIMIT 5
", 'i', [$customer_id]);

$page_title = 'Customer Dashboard - PrintFlow';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4">
        <!-- Welcome Section -->
        <div class="card mb-8">
            <h1 class="text-3xl font-bold mb-2">Welcome back, <?php echo htmlspecialchars($current_user['first_name']); ?>!</h1>
            <p class="text-gray-600">Track your orders and manage your account</p>
        </div>

        <!-- Order Status Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="card border-l-4 border-yellow-500">
                <p class="text-gray-600 text-sm mb-1">Pending</p>
                <p class="text-3xl font-bold text-yellow-600"><?php echo $pending_orders; ?></p>
            </div>

            <div class="card border-l-4 border-blue-500">
                <p class="text-gray-600 text-sm mb-1">Processing</p>
                <p class="text-3xl font-bold text-blue-600"><?php echo $processing_orders; ?></p>
            </div>

            <div class="card border-l-4 border-green-500">
                <p class="text-gray-600 text-sm mb-1">Ready for Pickup</p>
                <p class="text-3xl font-bold text-green-600"><?php echo $ready_orders; ?></p>
            </div>

            <div class="card border-l-4 border-gray-500">
                <p class="text-gray-600 text-sm mb-1">Total Orders</p>
                <p class="text-3xl font-bold text-gray-600"><?php echo $total_orders; ?></p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <a href="products.php" class="card text-center hover:shadow-lg transition cursor-pointer">
                <div class="text-4xl mb-3">🛍️</div>
                <h3 class="font-bold text-lg mb-2">Browse Products</h3>
                <p class="text-gray-600 text-sm">Explore our printing services</p>
            </a>

            <a href="orders.php" class="card text-center hover:shadow-lg transition cursor-pointer">
                <div class="text-4xl mb-3">📦</div>
                <h3 class="font-bold text-lg mb-2">Track Orders</h3>
                <p class="text-gray-600 text-sm">View your order history</p>
            </a>

            <a href="upload_design.php" class="card text-center hover:shadow-lg transition cursor-pointer">
                <div class="text-4xl mb-3">🎨</div>
                <h3 class="font-bold text-lg mb-2">Upload Design</h3>
                <p class="text-gray-600 text-sm">Submit your custom designs</p>
            </a>
        </div>

        <!-- Recent Orders -->
        <div class="card">
            <h2 class="text-2xl font-bold mb-4">Recent Orders</h2>

            <?php if (empty($recent_orders)): ?>
                <div class="text-center py-8">
                    <p class="text-gray-600 mb-4">You haven't placed any orders yet</p>
                    <a href="products.php" class="btn-primary">Start Shopping</a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-3">Order #</th>
                                <th class="text-left py-3">Date</th>
                                <th class="text-left py-3">Amount</th>
                                <th class="text-left py-3">Payment</th>
                                <th class="text-left py-3">Status</th>
                                <th class="text-left py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 font-medium">#<?php echo $order['order_id']; ?></td>
                                    <td class="py-3"><?php echo format_date($order['order_date']); ?></td>
                                    <td class="py-3"><?php echo format_currency($order['total_amount']); ?></td>
                                    <td class="py-3"><?php echo status_badge($order['payment_status'], 'payment'); ?></td>
                                    <td class="py-3"><?php echo status_badge($order['status'], 'order'); ?></td>
                                    <td class="py-3">
                                        <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 text-center">
                    <a href="orders.php" class="text-indigo-600 hover:text-indigo-700 font-medium">View All Orders →</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
