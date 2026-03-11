<?php
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$is_typing = isset($_POST['is_typing']) ? (int)$_POST['is_typing'] : 0;
$user_id = get_user_id();
$user_type = get_user_type();

if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
    exit();
}

// Map Admin/Manager/Staff to 'Staff'
$db_user_type = ($user_type === 'Customer') ? 'Customer' : 'Staff';

$sql = "INSERT INTO user_status (user_type, user_id, order_id, is_typing, last_activity) 
        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), last_activity = CURRENT_TIMESTAMP";

$result = db_execute($sql, 'siii', [$db_user_type, $user_id, $order_id, $is_typing]);

echo json_encode(['success' => (bool)$result]);
?>
