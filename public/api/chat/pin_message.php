<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/branch_context.php';
require_once __DIR__ . '/../../../includes/ensure_order_messages.php';

ob_start();
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
if ($message_id <= 0) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Missing message ID']);
    exit;
}

$row = db_query('SELECT message_id, order_id, is_pinned FROM order_messages WHERE message_id = ?', 'i', [$message_id]);
if (empty($row)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Message not found']);
    exit;
}

if (get_user_type() !== 'Customer') {
    ob_end_clean();
    ob_start();
    printflow_assert_order_branch_access((int)$row[0]['order_id']);
}

$next = empty($row[0]['is_pinned']) ? 1 : 0;
$ok = db_execute('UPDATE order_messages SET is_pinned = ? WHERE message_id = ?', 'ii', [$next, $message_id]);

ob_end_clean();
echo json_encode(['success' => (bool)$ok, 'is_pinned' => (bool)$next]);
