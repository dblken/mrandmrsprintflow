<?php
/**
 * Staff Order Details Page
 * PrintFlow - Printing Shop PWA
 * View order details + customer info (read-only) + update status
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) {
    redirect('/printflow/staff/orders.php');
}

// Handle status update
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $new_status = $_POST['status'];
        $staff_id = get_user_id();
        
        db_execute("UPDATE orders SET status = ? WHERE order_id = ?", 'si', [$new_status, $order_id]);
        log_activity($staff_id, 'Order Status Update', "Updated Order #{$order_id} to {$new_status}");
        
        // Notify customer
        $order_data = db_query("SELECT customer_id FROM orders WHERE order_id = ?", 'i', [$order_id]);
        if (!empty($order_data)) {
            create_notification($order_data[0]['customer_id'], 'Customer', "Your order #{$order_id} status: {$new_status}", 'Order', true, false);
        }
        $success = "Order status updated to {$new_status}";
    } else {
        $error = 'Invalid request. Please try again.';
    }
}

// Get order with customer info
$order_result = db_query("
    SELECT o.*, 
           c.first_name as cust_first, c.last_name as cust_last, 
           c.email as cust_email, c.contact_number as cust_phone,
           c.customer_id as cust_id
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.customer_id 
    WHERE o.order_id = ?
", 'i', [$order_id]);

if (empty($order_result)) {
    redirect('/printflow/staff/orders.php');
}
$order = $order_result[0];

// Get order items
$items = db_query("
    SELECT oi.*, p.name as product_name, p.sku, p.category
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
", 'i', [$order_id]);

// Get other orders from this customer
$customer_orders = db_query("
    SELECT order_id, order_date, total_amount, status 
    FROM orders 
    WHERE customer_id = ? AND order_id != ?
    ORDER BY order_date DESC LIMIT 5
", 'ii', [$order['cust_id'], $order_id]);

$page_title = "Order #{$order_id} - Staff";
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
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 900px) { .detail-grid { grid-template-columns: 1fr; } }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #6b7280; }
        .detail-value { font-weight: 600; color: #1f2937; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; color: #6b7280; font-size: 13px; text-decoration: none; transition: color 0.15s; }
        .back-link:hover { color: #1f2937; }
        .alert-success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px; }
        .alert-error { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px; }
        .customer-card { background: #f9fafb; border-radius: 12px; padding: 20px; border: 1px solid #f3f4f6; }
        .customer-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .customer-avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 16px; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div>
                <a href="orders" class="back-link">← Back to Orders</a>
                <h1 class="page-title" style="margin-top:4px;">Order #<?php echo $order_id; ?></h1>
            </div>
        </header>

        <main>
            <?php if ($success): ?>
                <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="detail-grid">
                <!-- Order Information -->
                <div class="card">
                    <h2 style="font-size:18px; font-weight:600; margin-bottom:20px;">Order Information</h2>
                    <div class="detail-row">
                        <span class="detail-label">Order Date</span>
                        <span class="detail-value"><?php echo format_datetime($order['order_date']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Total Amount</span>
                        <span class="detail-value"><?php echo format_currency($order['total_amount']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Current Status</span>
                        <span class="detail-value"><?php echo status_badge($order['status'], 'order'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Status</span>
                        <span class="detail-value"><?php echo status_badge($order['payment_status'], 'order'); ?></span>
                    </div>
                    <?php if ($order['payment_reference']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Payment Reference</span>
                        <span class="detail-value"><?php echo htmlspecialchars($order['payment_reference']); ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Update Status Form -->
                    <div style="margin-top:20px; padding-top:20px; border-top:1px solid #f3f4f6;">
                        <h3 style="font-size:14px; font-weight:600; margin-bottom:12px;">Update Status</h3>
                        <form method="POST" style="display:flex; gap:10px;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="update_status" value="1">
                            <select name="status" class="input-field" style="flex:1;">
                                <option value="Pending" <?php echo $order['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Processing" <?php echo $order['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="Ready for Pickup" <?php echo $order['status'] === 'Ready for Pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                <option value="Completed" <?php echo $order['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Cancelled" <?php echo $order['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            <button type="submit" class="btn-primary">Update</button>
                        </form>
                    </div>
                </div>

                <!-- Customer Information (Read-Only) -->
                <div class="card">
                    <h2 style="font-size:18px; font-weight:600; margin-bottom:20px;">Customer Information</h2>
                    <div class="customer-card">
                        <div class="customer-header">
                            <div class="customer-avatar"><?php echo strtoupper(substr($order['cust_first'] ?? 'C', 0, 1)); ?></div>
                            <div>
                                <div style="font-weight:600; font-size:15px;"><?php echo htmlspecialchars(($order['cust_first'] ?? '') . ' ' . ($order['cust_last'] ?? '')); ?></div>
                                <div style="font-size:12px; color:#9ca3af;">Customer</div>
                            </div>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Email</span>
                            <span class="detail-value"><?php echo htmlspecialchars($order['cust_email'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Contact Number</span>
                            <span class="detail-value"><?php echo htmlspecialchars($order['cust_phone'] ?? 'N/A'); ?></span>
                        </div>
                    </div>

                    <?php if (!empty($customer_orders)): ?>
                    <div style="margin-top:20px;">
                        <h3 style="font-size:14px; font-weight:600; margin-bottom:12px;">Other Orders from Customer</h3>
                        <?php foreach ($customer_orders as $co): ?>
                        <div class="detail-row">
                            <span>
                                <a href="order_details.php?id=<?php echo $co['order_id']; ?>" style="color:#10b981; text-decoration:none; font-weight:500;">#<?php echo $co['order_id']; ?></a>
                                <span class="detail-label" style="margin-left:8px;"><?php echo format_date($co['order_date']); ?></span>
                            </span>
                            <span class="detail-value"><?php echo format_currency($co['total_amount']); ?> <?php echo status_badge($co['status'], 'order'); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card" style="margin-top:24px;">
                <h2 style="font-size:18px; font-weight:600; margin-bottom:20px;">Order Items</h2>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="6" style="text-align:center; color:#9ca3af; padding:24px;">No items found</td></tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td style="font-weight:500;"><?php echo htmlspecialchars($item['product_name'] ?? 'Unknown'); ?></td>
                                    <td style="font-family:monospace; font-size:12px;"><?php echo htmlspecialchars($item['sku'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($item['category'] ?? '—'); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo format_currency($item['unit_price']); ?></td>
                                    <td style="font-weight:600;"><?php echo format_currency($item['quantity'] * $item['unit_price']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="border-top:2px solid #e5e7eb;">
                                    <td colspan="5" style="text-align:right; font-weight:600;">Total</td>
                                    <td style="font-weight:700; font-size:16px;"><?php echo format_currency($order['total_amount']); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

</body>
</html>
