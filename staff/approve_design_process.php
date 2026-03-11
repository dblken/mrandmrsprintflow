<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $order_id = (int)($_POST['order_id'] ?? 0);
    $staff_id = get_user_id();

    if (!$order_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
        exit;
    }

    if (approve_design($order_id, $staff_id)) {
        echo json_encode(['success' => true, 'message' => 'Design successfully approved.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to approve design.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
