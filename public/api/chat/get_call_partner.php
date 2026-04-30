<?php
/**
 * get_call_partner.php
 * Returns the call partner (staff) for a customer initiating a call.
 * Uses reliable DB lookups — NOT user_status (which only tracks chat-page activity).
 * The socket server determines real-time availability; this just resolves who to call.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/branch_context.php';

ob_start();
header('Content-Type: application/json');

if (!is_logged_in()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$order_id  = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$user_id   = get_user_id();
$user_type = get_user_type();

if (!$order_id) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
    exit();
}

$partner = null;

if ($user_type === 'Customer') {
    // ── Customer → Staff ──────────────────────────────────────────────────────
    // Priority 1: Most-recently active staff in user_status for this order
    // Priority 2: Last staff sender in order_messages
    // Priority 3: Job-order assignee
    // Priority 4: Most recently active staff in the same branch as the order
    // Priority 5: ANY staff/manager (global fallback)
    $sql = "
        SELECT u.user_id,
               TRIM(CONCAT(u.first_name, ' ', u.last_name)) AS full_name,
               u.profile_picture
        FROM users u
        WHERE u.user_id = (
            SELECT COALESCE(
                (SELECT us.user_id
                   FROM user_status us
                   JOIN users u2 ON u2.user_id = us.user_id
                  WHERE us.order_id = ? AND us.user_type = 'Staff'
                  ORDER BY us.last_activity DESC LIMIT 1),

                (SELECT m.sender_id
                   FROM order_messages m
                   JOIN users u3 ON u3.user_id = m.sender_id
                  WHERE m.order_id = ? AND m.sender_id > 0 AND m.sender = 'Staff'
                  ORDER BY m.message_id DESC LIMIT 1),

                (SELECT jo.assigned_to
                   FROM job_orders jo
                   JOIN users u4 ON u4.user_id = jo.assigned_to
                  WHERE jo.order_id = ? AND jo.assigned_to IS NOT NULL
                  ORDER BY jo.updated_at DESC LIMIT 1),

                (SELECT u5.user_id
                   FROM users u5
                   JOIN orders o ON o.branch_id = u5.branch_id
                  WHERE o.order_id = ? AND u5.role IN ('Staff','Manager','Admin')
                  ORDER BY u5.last_activity DESC, u5.user_id ASC LIMIT 1),

                (SELECT u6.user_id
                   FROM users u6
                  WHERE u6.role IN ('Staff','Manager','Admin')
                  ORDER BY u6.last_activity DESC LIMIT 1)
            )
        )
        LIMIT 1";

    $rows = db_query($sql, 'iiii', [$order_id, $order_id, $order_id, $order_id]);
    if (!empty($rows)) {
        $partner = [
            'id'     => (int)$rows[0]['user_id'],
            'name'   => $rows[0]['full_name'],
            'avatar' => $rows[0]['profile_picture'],
            'type'   => 'Staff'
        ];
    }

} else {
    // ── Staff → Customer ──────────────────────────────────────────────────────
    // Ensure staff can access this order
    printflow_assert_order_branch_access($order_id);

    $rows = db_query(
        "SELECT c.customer_id,
                TRIM(CONCAT(c.first_name, ' ', c.last_name)) AS full_name,
                c.profile_picture
           FROM customers c
           JOIN orders o ON o.customer_id = c.customer_id
          WHERE o.order_id = ?
          LIMIT 1",
        'i', [$order_id]
    );
    if (!empty($rows)) {
        $partner = [
            'id'     => (int)$rows[0]['customer_id'],
            'name'   => $rows[0]['full_name'],
            'avatar' => $rows[0]['profile_picture'],
            'type'   => 'Customer'
        ];
    }
}

ob_end_clean();
echo json_encode([
    'success' => true,
    'partner' => $partner   // null if no partner could be resolved
]);
exit();
?>
