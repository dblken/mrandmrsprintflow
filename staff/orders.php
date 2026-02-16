<?php
/**
 * Staff Orders Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');

// Get filter parameters
$status_filter = $_GET['status'] ?? '';

// Build query
$sql = "SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name FROM orders o LEFT JOIN customers c ON o.customer_id = c.customer_id WHERE 1=1";
$params = [];
$types = '';

if (!empty($status_filter)) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$sql .= " ORDER BY o.order_date DESC LIMIT 50";

$orders = db_query($sql, $types, $params);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $order_id = (int)$_POST['order_id'];
        $new_status = $_POST['status'];
        $staff_id = get_user_id();
        
        db_execute("UPDATE orders SET status = ? WHERE order_id = ?", 'si', [$new_status, $order_id]);
        
        // Log activity
        log_activity($staff_id, 'Order Status Update', "Updated Order #{$order_id} to {$new_status}");
        
        // Notify customer
        $order_data = db_query("SELECT customer_id FROM orders WHERE order_id = ?", 'i', [$order_id]);
        if (!empty($order_data)) {
            create_notification($order_data[0]['customer_id'], 'Customer', "Your order #{$order_id} status: {$new_status}", 'Order', true, false);
        }
        
        redirect('/printflow/staff/orders.php?success=1');
    }
}

$page_title = 'Orders - Staff';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Orders Management</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                Order status updated successfully!
            </div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="card mb-6">
            <form method="GET" class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Status</label>
                    <select name="status" class="input-field">
                        <option value="">All Statuses</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="Ready for Pickup" <?php echo $status_filter === 'Ready for Pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                        <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary">Apply Filter</button>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2">
                            <th class="text-left py-3">Order #</th>
                            <th class="text-left py-3">Customer</th>
                            <th class="text-left py-3">Date</th>
                            <th class="text-left py-3">Total</th>
                            <th class="text-left py-3">Status</th>
                            <th class="text-right py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 font-medium">#<?php echo $order['order_id']; ?></td>
                                <td class="py-3"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td class="py-3"><?php echo format_date($order['order_date']); ?></td>
                                <td class="py-3 font-semibold"><?php echo format_currency($order['total_amount']); ?></td>
                                <td class="py-3"><?php echo status_badge($order['status'], 'order'); ?></td>
                                <td class="py-3 text-right">
                                    <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="text-indigo-600 hover:text-indigo-700 font-medium">
                                        View/Update
                                    </a>
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
