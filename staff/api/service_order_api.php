<?php
/**
 * Staff API: service order detail (JSON) + approve / reject / update status.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/service_order_helper.php';
require_once __DIR__ . '/../../includes/service_order_staff_modal_data.php';

if (!is_logged_in() || (!is_staff() && !is_admin())) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

service_order_ensure_tables();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && ($_GET['action'] ?? '') === 'detail') {
    $id = (int)($_GET['id'] ?? 0);
    $data = service_order_staff_modal_data($id);
    if ($data === null) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

if ($method !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$input = $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($input)) {
    $input = $_POST;
}

if (!verify_csrf_token($input['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

$order_id = (int)($input['order_id'] ?? $input['id'] ?? 0);
if ($order_id < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid order']);
    exit;
}

$order_row = db_query(
    'SELECT * FROM service_orders WHERE id = ?',
    'i',
    [$order_id]
);
if (empty($order_row)) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}
$order_row = $order_row[0];

$op = $input['op'] ?? '';

if ($op === 'approve') {
    db_execute("UPDATE service_orders SET status = 'Processing' WHERE id = ?", 'i', [$order_id]);
    if (function_exists('create_notification')) {
        create_notification(
            (int)$order_row['customer_id'],
            'Customer',
            "Your service order #{$order_id} has been approved and is now processing.",
            'Order',
            true,
            false
        );
    }
} elseif ($op === 'reject') {
    db_execute("UPDATE service_orders SET status = 'Rejected' WHERE id = ?", 'i', [$order_id]);
    if (function_exists('create_notification')) {
        create_notification(
            (int)$order_row['customer_id'],
            'Customer',
            "Your service order #{$order_id} has been rejected.",
            'Order',
            true,
            false
        );
    }
} elseif ($op === 'update_status') {
    $new_status = (string)($input['status'] ?? '');
    if (!in_array($new_status, ['Pending', 'Pending Review', 'Approved', 'Processing', 'Completed', 'Rejected'], true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        exit;
    }
    db_execute('UPDATE service_orders SET status = ? WHERE id = ?', 'si', [$new_status, $order_id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Unknown operation']);
    exit;
}

$data = service_order_staff_modal_data($order_id);
echo json_encode(['success' => true, 'data' => $data]);
exit;
