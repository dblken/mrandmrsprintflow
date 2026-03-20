<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$staff_id = (int)get_user_id();

if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

// Fetch the order to get the customer and verify it exists
$order = db_query(
    "SELECT order_id, customer_id, status FROM orders WHERE order_id = ?",
    'i', [$order_id]
);

if (empty($order)) {
    echo json_encode(['success' => false, 'error' => 'Order not found.']);
    exit;
}

$customer_id = (int)$order[0]['customer_id'];

// Update order status to "Design Approved" and record the approving staff
$updated = db_execute(
    "UPDATE orders SET status = 'Design Approved', updated_at = NOW() WHERE order_id = ?",
    'i', [$order_id]
);

if (!$updated) {
    echo json_encode(['success' => false, 'error' => 'Failed to approve design.']);
    exit;
}

// Notify the customer
if ($customer_id) {
    create_notification(
        $customer_id,
        'Customer',
        "Your design for Order #{$order_id} has been approved! Production is starting.",
        'Design',
        false,
        false,
        $order_id
    );
}

echo json_encode(['success' => true, 'message' => 'Design successfully approved.']);
