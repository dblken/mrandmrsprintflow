<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/branch_context.php';
require_once __DIR__ . '/../../../includes/ensure_chat_schema.php';

// Prevent accidental output (warnings/notices) from breaking JSON
ob_start();

header('Content-Type: application/json');

if (!is_logged_in()) {
    ob_end_clean();
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

if ($user_type !== 'Customer') {
    ob_end_clean();
    ob_start();
    printflow_assert_order_branch_access($order_id);
}

// Map Admin/Manager/Staff to 'Staff'
$db_user_type = ($user_type === 'Customer') ? 'Customer' : 'Staff';

$sql = "INSERT INTO user_status (user_type, user_id, order_id, is_typing, last_activity) 
        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), last_activity = CURRENT_TIMESTAMP";

$result = db_execute($sql, 'siii', [$db_user_type, $user_id, $order_id, $is_typing]);

// Also update the global user/customer last_activity for the "Online" indicator (defensive check)
if ($user_type === 'Customer') {
    $has_col = !empty(db_query("SHOW COLUMNS FROM customers LIKE 'last_activity'"));
    if ($has_col) {
        db_execute("UPDATE customers SET last_activity = CURRENT_TIMESTAMP WHERE customer_id = ?", 'i', [$user_id]);
    }
} else {
    $has_col = !empty(db_query("SHOW COLUMNS FROM users LIKE 'last_activity'"));
    if ($has_col) {
        db_execute("UPDATE users SET last_activity = CURRENT_TIMESTAMP WHERE user_id = ?", 'i', [$user_id]);
    }
}

// PARTNER DISCOVERY (For Calling/Video Call)
$partner = null;

if ($user_type === 'Customer') {
    // Robust discovery: check job assignments, last message sender, or active user_status
    $sql_discovery = "
        SELECT u.user_id, TRIM(CONCAT(u.first_name, ' ', u.last_name)) as full_name, u.profile_picture 
        FROM users u 
        WHERE u.user_id = (
            SELECT COALESCE(
                (SELECT jo.assigned_to FROM job_orders jo WHERE jo.order_id = ? AND jo.assigned_to IS NOT NULL ORDER BY jo.updated_at DESC LIMIT 1),
                (SELECT m.sender_id FROM order_messages m WHERE m.order_id = ? AND m.sender_id > 0 AND m.sender = 'Staff' ORDER BY m.message_id DESC LIMIT 1),
                (SELECT us.user_id FROM user_status us WHERE us.order_id = ? AND us.user_type = 'Staff' ORDER BY us.last_activity DESC LIMIT 1),
                (SELECT u2.user_id FROM users u2 JOIN orders o ON o.branch_id = u2.branch_id WHERE o.order_id = ? AND u2.role IN ('Staff','Manager','Admin') ORDER BY u2.online_status = 'online' DESC, u2.user_id ASC LIMIT 1)
            )
        ) LIMIT 1";
    
    $s = db_query($sql_discovery, 'iiii', [$order_id, $order_id, $order_id, $order_id]);
    
    if (!empty($s)) {
        $partner = [
            'id' => (int)$s[0]['user_id'],
            'name' => $s[0]['full_name'],
            'avatar' => $s[0]['profile_picture']
        ];
    }
} else {
    // Staff is calling customer
    $c = db_query("SELECT c.customer_id, TRIM(CONCAT(c.first_name, ' ', c.last_name)) as full_name, c.profile_picture 
                   FROM customers c 
                   JOIN orders o ON o.customer_id = c.customer_id 
                   WHERE o.order_id = ?", 'i', [$order_id]);
    if (!empty($c)) {
        $partner = [
            'id' => (int)$c[0]['customer_id'],
            'name' => $c[0]['full_name'],
            'avatar' => $c[0]['profile_picture']
        ];
    }
}

// Clear any accidental output before sending JSON
ob_end_clean();
echo json_encode([
    'success' => true,
    'partner' => $partner
]);
exit();
?>
