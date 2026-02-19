<?php
/**
 * Admin Order Details API
 * PrintFlow - Printing Shop PWA
 * Returns order details as JSON for the modal view
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

header('Content-Type: application/json');

$order_id = (int)($_GET['id'] ?? 0);

if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

// Get order with customer info
$order_result = db_query("
    SELECT o.*, 
           CONCAT(c.first_name, ' ', c.last_name) as customer_name,
           c.email as customer_email, 
           c.contact_number as customer_phone,
           c.first_name as cust_first
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.customer_id 
    WHERE o.order_id = ?
", 'i', [$order_id]);

if (empty($order_result)) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
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

// Format data for response
$formatted_items = [];
if (!empty($items)) {
    foreach ($items as $item) {
        $formatted_items[] = [
            'product_name' => $item['product_name'] ?? 'Unknown Product',
            'sku' => $item['sku'] ?? '—',
            'category' => $item['category'] ?? '—',
            'quantity' => (int)$item['quantity'],
            'unit_price' => (float)$item['unit_price'],
            'subtotal' => (float)($item['quantity'] * $item['unit_price']),
            'unit_price_formatted' => format_currency($item['unit_price']),
            'subtotal_formatted' => format_currency($item['quantity'] * $item['unit_price']),
        ];
    }
}

echo json_encode([
    'success' => true,
    'order' => [
        'order_id' => $order['order_id'],
        'customer_name' => $order['customer_name'] ?? 'N/A',
        'customer_email' => $order['customer_email'] ?? 'N/A',
        'customer_phone' => $order['customer_phone'] ?? 'N/A',
        'customer_initial' => strtoupper(substr($order['cust_first'] ?? 'C', 0, 1)),
        'order_date' => format_datetime($order['order_date']),
        'total_amount' => format_currency($order['total_amount']),
        'status' => $order['status'],
        'payment_status' => $order['payment_status'],
        'payment_reference' => $order['payment_reference'] ?? '',
        'notes' => $order['notes'] ?? '',
    ],
    'items' => $formatted_items
]);
