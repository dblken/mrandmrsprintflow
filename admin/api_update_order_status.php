<?php
/**
 * Admin: Update Order Status API
 * PrintFlow - Printing Shop PWA
 *
 * POST JSON endpoint (Admin role).
 * When status → 'Completed', triggers deduct_materials_by_variant().
 * If deduction fails, refuses the status change and returns error.
 */

require_once __DIR__ . '/../includes/api_header.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';
require_once __DIR__ . '/../includes/product_branch_stock.php';
require_once __DIR__ . '/../includes/InventoryManager.php';
require_once __DIR__ . '/../includes/variant_functions.php';
require_once __DIR__ . '/../includes/TarpaulinService.php';

require_role(['Admin', 'Manager']);

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
$allowed = ['Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Cancelled', 'To Rate', 'Rated', 'Approved', 'To Pay', 'Pending Review', 'Pending Approval', 'For Revision', 'In Production', 'Printing'];
ensure_order_status_values($allowed);
if (!$order_id || !in_array($new_status, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Invalid order or status']);
    exit;
}

// Get current order
$order = db_query("SELECT order_id, status, customer_id, branch_id FROM orders WHERE order_id = ?", 'i', [$order_id]);
if (empty($order)) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}
$order = $order[0];

printflow_assert_order_branch_access($order_id);

// --- Safeguard: Check if all roll-based items have production specs ---
if ($new_status === 'Completed' && $order['status'] !== 'Completed') {
    $missing_specs = db_query("
        SELECT p.name 
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        LEFT JOIN order_tarp_details otd ON oi.order_item_id = otd.order_item_id
        WHERE oi.order_id = ? 
        AND (p.category LIKE '%TARPAULIN%' OR p.category LIKE '%STKR%')
        AND otd.order_item_id IS NULL
    ", 'i', [$order_id]);

    if (!empty($missing_specs)) {
        $names = array_column($missing_specs, 'name');
        echo json_encode([
            'success' => false,
            'error'   => "Production specs missing for: " . implode(', ', $names) . ". Please configure dimensions and rolls first.",
        ]);
        exit;
    }
}

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

    // --- Tarpaulin Roll Deduction ---
    try {
        TarpaulinService::deductInventoryForOrder($order_id);
    } catch (Exception $e) {
        // We log it but maybe continue? Or block?
        // Blocking is safer if the user wants strict inventory control.
        echo json_encode([
            'success' => false,
            'error'   => "Tarpaulin deduction failed: " . $e->getMessage(),
        ]);
        exit;
    }

    $orderBranchId = (int)($order['branch_id'] ?? 0);
    $orderRef = printflow_get_order_inventory_reference($order_id);
    $orderLabel = $orderRef['label'] ?? ('Order #' . printflow_format_order_code($order_id, ''));
    // Branch-aware product stock deduction for regular order items.
    $productItems = db_query(
        "SELECT oi.product_id, oi.quantity, p.name AS product_name
         FROM order_items oi
         LEFT JOIN products p ON p.product_id = oi.product_id
         WHERE oi.order_id = ?",
        'i',
        [$order_id]
    ) ?: [];

    foreach ($productItems as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 0);
        if ($productId <= 0 || $qty <= 0) {
            continue;
        }

        if (!printflow_product_deduct_stock_for_branch($productId, $orderBranchId, $qty)) {
            $productName = (string)($item['product_name'] ?? ('Product #' . $productId));
            echo json_encode([
                'success' => false,
                'error'   => "Insufficient stock for \"{$productName}\" at branch #{$orderBranchId}.",
            ]);
            exit;
        }

        printflow_record_product_inventory_transaction(
            $productId,
            'OUT',
            (float)$qty,
            'ORDER',
            $order_id,
            "{$orderLabel} completed - " . (string)($item['product_name'] ?? ('Product #' . $productId)),
            (int)(get_user_id() ?? 0),
            date('Y-m-d'),
            $orderBranchId
        );
    }
}

// Update status
db_execute(
    "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?",
    'si', [$new_status, $order_id]
);

// Notify customer + add system message to chat
$customer_id = (int)$order['customer_id'];
if ($customer_id) {
    $notif = get_order_status_notification_payload($order_id, $new_status);
    create_notification($customer_id, 'Customer', $notif['message'], $notif['type'], false, false, $order_id);
    add_order_system_message($order_id, $notif['message']);
}

$admin_id = get_user_id();
log_activity($admin_id, 'Order Status Update', "Order #{$order_id} → {$new_status}");

echo json_encode([
    'success' => true,
    'message' => "Order #{$order_id} updated to {$new_status}",
]);
