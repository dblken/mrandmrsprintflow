<?php
/**
 * AJAX: Get Order Data (Staff)
 * Returns full order details as JSON for modal display
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');

header('Content-Type: application/json');

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) {
    echo json_encode(['error' => 'Invalid order ID']);
    exit;
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
    echo json_encode(['error' => 'Order not found']);
    exit;
}
$order = $order_result[0];

// Get order items
$items = db_query("
    SELECT oi.*, p.name as product_name, p.sku, p.category
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
", 'i', [$order_id]);

// Get other orders from same customer
$customer_orders = db_query("
    SELECT order_id, order_date, total_amount, status
    FROM orders
    WHERE customer_id = ? AND order_id != ?
    ORDER BY order_date DESC LIMIT 5
", 'ii', [$order['cust_id'], $order_id]);

// Build items array
$items_out = [];
foreach ($items as $item) {
    $custom_data = json_decode($item['customization_data'] ?? '{}', true) ?? [];
    // Remove design_upload key from display
    unset($custom_data['design_upload']);

    $items_out[] = [
        'order_item_id' => $item['order_item_id'],
        'product_name'  => (function() use ($item, $custom_data) {
            if (!empty($item['product_name'])) return $item['product_name'];
            if (!empty($custom_data['service_type'])) {
                $name = $custom_data['service_type'];
                if (!empty($custom_data['product_type'])) {
                    $name .= " (" . $custom_data['product_type'] . ")";
                }
                return $name;
            }
            return 'Custom Order';
        })(),
        'sku'           => $item['sku'] ?? '',
        'category'      => $item['category'] ?? '',
        'quantity'      => (int)$item['quantity'],
        'unit_price'    => (float)$item['unit_price'],
        'subtotal'      => (float)($item['quantity'] * $item['unit_price']),
        'customization' => $custom_data,
        'has_design'    => !empty($item['design_image']) || !empty($item['design_file']),
        'has_reference' => !empty($item['reference_image_file']),
        'design_name'   => $item['design_image_name'] ?? 'design_file',
        'design_url'    => (!empty($item['design_image']) || !empty($item['design_file']))
                            ? '/printflow/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id']
                            : null,
        'reference_url' => !empty($item['reference_image_file'])
                            ? '/printflow/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id'] . '&field=reference'
                            : null,
    ];
}

// Build customer orders array
$cust_orders_out = [];
foreach ($customer_orders as $co) {
    $cust_orders_out[] = [
        'order_id'     => $co['order_id'],
        'order_date'   => format_date($co['order_date']),
        'total_amount' => format_currency($co['total_amount']),
        'status'       => $co['status'],
    ];
}

// Get revision history
$revisions_raw = db_query("
    SELECT r.revision_id, r.order_item_id, r.revision_reason, r.created_at, 
           u.first_name as staff_first, u.last_name as staff_last,
           p.name as product_name
    FROM order_item_revisions r
    LEFT JOIN users u ON r.staff_id = u.user_id
    LEFT JOIN order_items oi ON r.order_item_id = oi.order_item_id
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE r.order_id = ?
    ORDER BY r.created_at DESC
", 'i', [$order_id]);

$revisions_out = [];
foreach ($revisions_raw as $rev) {
    $revisions_out[] = [
        'revision_id'     => $rev['revision_id'],
        'order_item_id'   => $rev['order_item_id'],
        'product_name'    => $rev['product_name'] ?? 'Unknown Product',
        'revision_reason' => $rev['revision_reason'],
        'staff_name'      => trim(($rev['staff_first'] ?? '') . ' ' . ($rev['staff_last'] ?? '')),
        'created_at'      => format_datetime($rev['created_at']),
        'design_url'      => '/printflow/public/serve_design.php?type=revision_item&id=' . (int)$rev['revision_id']
    ];
}

echo json_encode([
    'order_id'            => $order['order_id'],
    'order_date'          => format_datetime($order['order_date']),
    'total_amount'        => format_currency($order['total_amount']),
    'total_raw'           => (float)$order['total_amount'],
    'status'              => $order['status'],
    'payment_status'      => $order['payment_status'],
    'payment_reference'   => $order['payment_reference'] ?? '',
    'payment_type'        => $order['payment_type'] ?? 'full_payment',
    'downpayment_amount'  => (float)($order['downpayment_amount'] ?? 0),
    'notes'               => $order['notes'] ?? '',
    'cancelled_by'        => $order['cancelled_by'] ?? '',
    'cancel_reason'       => $order['cancel_reason'] ?? '',
    'cancelled_at'        => !empty($order['cancelled_at']) ? format_datetime($order['cancelled_at']) : '',
    'design_status'       => $order['design_status'] ?? 'Pending',
    'reviewed_by'         => $order['reviewed_by'] ?? null,
    'reviewed_at'         => !empty($order['reviewed_at']) ? format_datetime($order['reviewed_at']) : '',
    'items'               => $items_out,
    'customer_orders'     => $cust_orders_out,
    'revisions'           => $revisions_out,
    'csrf_token'          => generate_csrf_token(),
]);
