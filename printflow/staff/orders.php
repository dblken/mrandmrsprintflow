<?php
/**
 * Staff Orders Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

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

$page_title = 'Orders - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Orders Management</h1>
        </header>

        <main>
            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4" style="background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px;">
                    Order status updated successfully!
                </div>
            <?php endif; ?>

            <!-- Filter -->
            <div class="card">
                <form method="GET" style="display:flex; gap:16px; align-items:flex-end;">
                    <div style="flex:1;">
                        <label>Filter by Status</label>
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
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td style="font-weight:500;">#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo format_date($order['order_date']); ?></td>
                                    <td style="font-weight:600;"><?php echo format_currency($order['total_amount']); ?></td>
                                    <td><?php echo status_badge($order['status'], 'order'); ?></td>
                                    <td style="text-align:right;">
                                        <a href="order_details.php?id=<?php echo $order['order_id']; ?>" style="color:#10b981; font-size:13px; font-weight:500; text-decoration:none;">
                                            View/Update
                                        </a>
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
