<?php
/**
 * Admin: Update Order Status API
 * PrintFlow - Printing Shop PWA
 *
 * POST JSON endpoint (Admin role).
 * When status → 'Completed', triggers deduct_materials_by_variant().
 * If deduction fails, refuses the status change and returns error.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/variant_functions.php';

require_role('Admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

if (!verify_csrf_token($input['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$order_id   = (int)($input['order_id'] ?? 0);
$new_status = $input['status'] ?? '';

$allowed = ['Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Cancelled'];
if (!$order_id || !in_array($new_status, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Invalid order or status']);
    exit;
}

// Get current order
$order = db_query("SELECT order_id, status, customer_id FROM orders WHERE order_id = ?", 'i', [$order_id]);
if (empty($order)) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}
$order = $order[0];

// --- Material deduction when marking Completed ---
if ($new_status === 'Completed' && $order['status'] !== 'Completed') {
    $deduction = deduct_materials_by_variant($order_id);
    if (!$deduction['success']) {
        echo json_encode([
            'success' => false,
            'error'   => implode(' ', $deduction['errors']),
        ]);
        exit;
    }
}

// Update status
db_execute(
    "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?",
    'si', [$new_status, $order_id]
);

// Notify customer
$customer_id = (int)$order['customer_id'];
if ($customer_id) {
    create_notification($customer_id, 'Customer', "Your order #{$order_id} status: {$new_status}", 'Order', false, false);
}

$admin_id = get_user_id();
log_activity($admin_id, 'Order Status Update', "Order #{$order_id} → {$new_status}");

echo json_encode([
    'success' => true,
    'message' => "Order #{$order_id} updated to {$new_status}",
]);
