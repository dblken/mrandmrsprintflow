<?php
/**
 * AJAX: Update Order Status (Staff)
 * Handles status changes and stock deduction when completed
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';
require_once __DIR__ . '/../includes/product_branch_stock.php';
require_once __DIR__ . '/../includes/InventoryManager.php';

require_role(['Staff', 'Admin', 'Manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$new_status = $_POST['status'] ?? '';
$csrf_token = $_POST['csrf_token'] ?? '';

if (!verify_csrf_token($csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

if (!$order_id || !$new_status) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

printflow_assert_order_branch_access($order_id);

// 1. Get current status to avoid double-deduction
$order_row = db_query("SELECT status, branch_id, customer_id FROM orders WHERE order_id = ?", 'i', [$order_id]);
if (empty($order_row)) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

$old_status = $order_row[0]['status'];
$customer_id = (int)($order_row[0]['customer_id'] ?? 0);

// 2. Update Status
$update_sql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?";
$result = db_execute($update_sql, 'si', [$new_status, $order_id]);

if (!$result) {
    echo json_encode(['success' => false, 'error' => 'Failed to update order status']);
    exit;
}


// 3. Stock Deduction Logic
if ($new_status === 'Completed' && $old_status !== 'Completed') {
    $branch_id = (int)$order_row[0]['branch_id'];
    $items = db_query("SELECT product_id, quantity FROM order_items WHERE order_id = ?", 'i', [$order_id]);
    
    foreach ($items as $item) {
        $pid = (int)($item['product_id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 0);
        
        if ($pid > 0 && $qty > 0) {
            // Use branch-aware deduction
            if (printflow_product_deduct_stock_for_branch($pid, $branch_id, $qty)) {
                // Standard product stock is tracked separately from inv_items.
                // Do not mirror it into inventory_transactions, or the material
                // ledger can show the wrong inventory item label for the order.
            }
        }
    }
}

// 4. System Message
$notif = get_order_status_notification_payload($order_id, $new_status);
if ($customer_id > 0) {
    create_notification($customer_id, 'Customer', $notif['message'], $notif['type'], false, false, $order_id);
}
add_order_system_message($order_id, $notif['message']);

// Automated Chat Update (Shopee/Messenger Style)
$chat_steps = [
    'Approved'         => 'approved',
    'To Pay'           => 'send_to_payment',
    'Processing'       => 'in_production',
    'In Production'    => 'in_production',
    'Ready for Pickup' => 'ready_to_pickup',
    'Completed'        => 'completed',
];
$chat_step = $chat_steps[$new_status] ?? null;
if ($chat_step) {
    printflow_send_order_update($order_id, $chat_step);
    if ($chat_step === 'completed') {
        printflow_send_order_update($order_id, 'rate');
    }
}

echo json_encode(['success' => true, 'message' => "Order #$order_id marked as $new_status"]);
