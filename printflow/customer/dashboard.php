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
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:1100px;">

        <!-- Welcome Banner -->
        <div class="ct-welcome">
            <h1>Welcome back, <?php echo htmlspecialchars($current_user['first_name']); ?>!</h1>
            <p>Track your orders and manage your account</p>
        </div>

        <!-- Stats -->
        <div class="ct-stats">
            <div class="ct-stat-card yellow">
                <p class="ct-stat-label">Pending</p>
                <p class="ct-stat-value"><?php echo $pending_orders; ?></p>
            </div>
            <div class="ct-stat-card blue">
                <p class="ct-stat-label">Processing</p>
                <p class="ct-stat-value"><?php echo $processing_orders; ?></p>
            </div>
            <div class="ct-stat-card green">
                <p class="ct-stat-label">Ready for Pickup</p>
                <p class="ct-stat-value"><?php echo $ready_orders; ?></p>
            </div>
            <div class="ct-stat-card gray">
                <p class="ct-stat-label">Total Orders</p>
                <p class="ct-stat-value"><?php echo $total_orders; ?></p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="ct-actions">
            <a href="products.php" class="ct-action-card">
                <div class="ct-action-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:1.5rem;height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
                <h3 class="ct-action-title">Browse Products</h3>
                <p class="ct-action-desc">Explore our printing services</p>
            </a>
            <a href="orders.php" class="ct-action-card">
                <div class="ct-action-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:1.5rem;height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <h3 class="ct-action-title">Track Orders</h3>
                <p class="ct-action-desc">View your order history</p>
            </a>
            <a href="upload_design.php" class="ct-action-card">
                <div class="ct-action-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:1.5rem;height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                </div>
                <h3 class="ct-action-title">Upload Design</h3>
                <p class="ct-action-desc">Submit your custom designs</p>
            </a>
        </div>

        <!-- Recent Orders -->
        <div class="card" style="border-radius:1rem;">
            <h2 class="ct-section-title">Recent Orders</h2>

            <?php if (empty($recent_orders)): ?>
                <div class="ct-empty">
                    <div class="ct-empty-icon">📦</div>
                    <p>You haven't placed any orders yet</p>
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
                                <tr class="border-b">
                                    <td class="py-3 font-medium">#<?php echo $order['order_id']; ?></td>
                                    <td class="py-3"><?php echo format_date($order['order_date']); ?></td>
                                    <td class="py-3 font-bold" style="color:#7c3aed;"><?php echo format_currency($order['total_amount']); ?></td>
                                    <td class="py-3"><?php echo status_badge($order['payment_status'], 'payment'); ?></td>
                                    <td class="py-3"><?php echo status_badge($order['status'], 'order'); ?></td>
                                    <td class="py-3">
                                        <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="ct-view-link">View →</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 text-center">
                    <a href="orders.php" class="ct-view-link">View All Orders →</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
