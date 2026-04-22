<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/branch_context.php';

// Prevent accidental output (notices, etc.) from breaking JSON
ob_start();

header('Content-Type: application/json');

if (!is_logged_in()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : (isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0);
$archive = isset($_POST['archive']) ? (int)$_POST['archive'] : (isset($_GET['archive']) ? (int)$_GET['archive'] : 1);
$user_id = get_user_id();
$user_type = get_user_type();

if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
    exit();
}

if ($user_type !== 'Customer') {
    ob_end_clean();
    ob_start();
    printflow_assert_order_branch_access($order_id);
}

$has_archive = !empty(db_query("SHOW COLUMNS FROM orders LIKE 'is_archived'"));
if (!$has_archive) {
    global $conn;
    @$conn->query("ALTER TABLE orders ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
}

$res = db_execute("UPDATE orders SET is_archived = ? WHERE order_id = ?", 'ii', [$archive, $order_id]);

// Clear accidental output before sending JSON
ob_end_clean();

echo json_encode(['success' => (bool)$res, 'archived' => (bool)$archive]);
?>
