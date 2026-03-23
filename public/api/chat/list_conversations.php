<?php
/**
 * List conversations (orders with chat) for Customer or Staff
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/ensure_order_messages.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = get_user_id();
$user_type = get_user_type();

if ($user_type === 'Customer') {
    // Customer: orders they own (include staff_name from most recent Staff message)
    $sql = "
        SELECT o.order_id, o.status, o.order_date,
               (SELECT m.message FROM order_messages m WHERE m.order_id = o.order_id ORDER BY m.message_id DESC LIMIT 1) AS last_message,
               (SELECT m.created_at FROM order_messages m WHERE m.order_id = o.order_id ORDER BY m.message_id DESC LIMIT 1) AS last_message_at,
               (SELECT COUNT(*) FROM order_messages m WHERE m.order_id = o.order_id AND m.sender = 'Staff' AND m.read_receipt = 0) AS unread_count,
               (SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(oi.customization_data, '$.service_type')), p.name, 'Order') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id LIMIT 1) AS service_name,
               (SELECT TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) FROM order_messages m JOIN users u ON u.user_id = m.sender_id WHERE m.order_id = o.order_id AND m.sender = 'Staff' ORDER BY m.message_id DESC LIMIT 1) AS staff_name
        FROM orders o
        WHERE o.customer_id = ?
        ORDER BY COALESCE((SELECT MAX(m.created_at) FROM order_messages m WHERE m.order_id = o.order_id), o.order_date) DESC
    ";
    $rows = db_query($sql, 'i', [$user_id]);
} else {
    // Staff/Admin/Manager: orders with messages OR recent (90 days) — wider net so staff always see conversations
    $sql = "
        SELECT o.order_id, o.status, o.order_date,
               TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))) AS customer_name,
               (SELECT m.message FROM order_messages m WHERE m.order_id = o.order_id ORDER BY m.message_id DESC LIMIT 1) AS last_message,
               (SELECT m.created_at FROM order_messages m WHERE m.order_id = o.order_id ORDER BY m.message_id DESC LIMIT 1) AS last_message_at,
               (SELECT COUNT(*) FROM order_messages m WHERE m.order_id = o.order_id AND m.sender = 'Customer' AND m.read_receipt = 0) AS unread_count,
               (SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(oi.customization_data, '$.service_type')), p.name, 'Order') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id LIMIT 1) AS service_name
        FROM orders o
        LEFT JOIN customers c ON c.customer_id = o.customer_id
        WHERE o.status != 'Cancelled'
        AND (
            EXISTS (SELECT 1 FROM order_messages m WHERE m.order_id = o.order_id)
            OR o.order_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        )
        ORDER BY COALESCE((SELECT MAX(m.created_at) FROM order_messages m WHERE m.order_id = o.order_id), o.order_date) DESC
    ";
    $rows = db_query($sql);
    if ($rows === false) {
        $rows = [];
    }
}

$conversations = [];
foreach ($rows ?: [] as $r) {
    $last_msg = (string)($r['last_message'] ?? '');
    if (strlen($last_msg) > 60) $last_msg = substr($last_msg, 0, 57) . '...';
    $customer_name = trim((string)($r['customer_name'] ?? ''));
    if ($customer_name === '') $customer_name = 'Customer';
    $staff_name = trim((string)($r['staff_name'] ?? ''));
    if ($staff_name === '') $staff_name = 'PrintFlow Team';
    $conv = [
        'order_id' => (int)$r['order_id'],
        'status' => $r['status'] ?? '',
        'service_name' => $r['service_name'] ?? 'Order',
        'customer_name' => $customer_name,
        'last_message' => $last_msg,
        'last_message_at' => $r['last_message_at'] ?? $r['order_date'] ?? null,
        'unread_count' => (int)($r['unread_count'] ?? 0),
    ];
    if ($user_type === 'Customer') {
        $conv['staff_name'] = $staff_name;
    }
    $conversations[] = $conv;
}

echo json_encode(['success' => true, 'conversations' => $conversations]);
