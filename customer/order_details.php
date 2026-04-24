<?php
/**
 * Legacy customer order details route.
 * Customer order details now open from customer/orders.php via modal.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$order_id = (int)($_GET['id'] ?? 0);
$customer_id = get_user_id();

if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notification_id, $customer_id]);
}

if ($order_id > 0) {
    redirect('orders.php?highlight=' . $order_id);
}

redirect('orders.php');
