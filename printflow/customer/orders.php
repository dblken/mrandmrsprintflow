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
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:1100px;">
        <h1 class="ct-page-title">My Orders</h1>

        <!-- Filter -->
        <div class="ct-filter">
            <form method="GET" style="display:flex; gap:1rem; align-items:end;">
                <div style="flex:1;">
                    <label style="display:block; font-size:0.75rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:0.4rem;">Filter by Status</label>
                    <select name="status" class="input-field">
                        <option value="">All Orders</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="Ready for Pickup" <?php echo $status_filter === 'Ready for Pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                        <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="height:fit-content;">Apply</button>
            </form>
        </div>

        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="ct-empty">
                <div class="ct-empty-icon">📦</div>
                <p>No orders found</p>
                <a href="products.php" class="btn-primary">Start Shopping</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="ct-order-card">
                    <div class="ct-order-header">
                        <div>
                            <p class="ct-order-id">Order #<?php echo $order['order_id']; ?></p>
                            <p class="ct-order-date"><?php echo format_datetime($order['order_date']); ?></p>
                        </div>
                        <div style="text-align:right;">
                            <p class="ct-order-amount"><?php echo format_currency($order['total_amount']); ?></p>
                            <?php echo status_badge($order['status'], 'order'); ?>
                        </div>
                    </div>

                    <div class="ct-order-meta">
                        <div>
                            <p class="ct-order-meta-label">Payment Status</p>
                            <p class="ct-order-meta-value"><?php echo status_badge($order['payment_status'], 'payment'); ?></p>
                        </div>
                        <div>
                            <p class="ct-order-meta-label">Estimated Completion</p>
                            <p class="ct-order-meta-value"><?php echo $order['estimated_completion'] ? format_date($order['estimated_completion']) : 'TBD'; ?></p>
                        </div>
                        <div style="display:flex; align-items:center;">
                            <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="ct-view-link">
                                View Details →
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
