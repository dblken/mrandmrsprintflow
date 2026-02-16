<?php
/**
 * Customer Orders Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$customer_id = get_user_id();

// Get filter parameters
$status_filter = $_GET['status'] ?? '';

// Build query
$sql = "SELECT * FROM orders WHERE customer_id = ?";
$params = [$customer_id];
$types = 'i';

if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$sql .= " ORDER BY order_date DESC";

$orders = db_query($sql, $types, $params);

$page_title = 'My Orders - PrintFlow';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">My Orders</h1>

        <!-- Filter -->
        <div class="card mb-6">
            <form method="GET" class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Status</label>
                    <select name="status" class="input-field">
                        <option value="">All Orders</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="Ready for Pickup" <?php echo $status_filter === 'Ready for Pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                        <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary">Apply Filter</button>
            </form>
        </div>

        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="card text-center py-12">
                <p class="text-gray-600 mb-4">No orders found</p>
                <a href="products.php" class="btn-primary">Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($orders as $order): ?>
                    <div class="card">
                        <div class="flex flex-wrap items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-bold">Order #<?php echo $order['order_id']; ?></h3>
                                <p class="text-sm text-gray-600"><?php echo format_datetime($order['order_date']); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold text-indigo-600"><?php echo format_currency($order['total_amount']); ?></p>
                                <?php echo status_badge($order['status'], 'order'); ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm border-t pt-4">
                            <div>
                                <p class="text-gray-600">Payment Status</p>
                                <p class="font-semibold"><?php echo status_badge($order['payment_status'], 'payment'); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Estimated Completion</p>
                                <p class="font-semibold"><?php echo $order['estimated_completion'] ? format_date($order['estimated_completion']) : 'TBD'; ?></p>
                            </div>
                            <div class="text-right">
                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="text-indigo-600 hover:text-indigo-700 font-medium">
                                    View Details →
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
