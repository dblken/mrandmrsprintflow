<?php
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$user_id = get_user_id();
$user_type = get_user_type();

if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
    exit();
}

// 1. Fetch new messages
$sql = "SELECT * FROM order_messages WHERE order_id = ? AND message_id > ? ORDER BY message_id ASC";
$messages_raw = db_query($sql, 'ii', [$order_id, $last_id]);

$messages = [];
if ($messages_raw) {
    foreach ($messages_raw as $msg) {
        $is_system = ($msg['sender'] === 'System');
        $is_self = false;
        if (!$is_system) {
            if ($user_type === 'Customer') {
                $is_self = ($msg['sender'] === 'Customer');
            } else {
                $is_self = ($msg['sender'] === 'Staff');
            }
        }

        $image_path = (string)($msg['image_path'] ?? '');
        if ($image_path !== '' && !preg_match('#^https?://#i', $image_path)) {
            if (strpos($image_path, '/printflow/') !== 0) {
                $image_path = '/printflow/' . ltrim($image_path, '/');
            }
        }

        $messages[] = [
            'id' => $msg['message_id'],
            'message' => $msg['message'],
            'message_type' => $msg['message_type'] ?? 'text',
            'image_path' => $image_path,
            'created_at' => date('h:i A', strtotime($msg['created_at'])),
            'is_self' => $is_self,
            'is_seen' => (bool)$msg['read_receipt'],
            'is_system' => $is_system
        ];
    }
}

// 2. Mark messages as seen (exclude System - they're auto-read)
$target_sender = ($user_type === 'Customer') ? 'Staff' : 'Customer';
db_execute("UPDATE order_messages SET read_receipt = 1 WHERE order_id = ? AND sender = ? AND read_receipt = 0", 'is', [$order_id, $target_sender]);

// 2.5 Clear lingering notifications for this chat
if ($user_type === 'Customer') {
    db_execute("UPDATE notifications SET is_read = 1 WHERE customer_id = ? AND type = 'Message' AND data_id = ? AND is_read = 0", 'ii', [$user_id, $order_id]);
} else {
    db_execute("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type = 'Message' AND data_id = ? AND is_read = 0", 'ii', [$user_id, $order_id]);
}

// 3. Fetch partner status

$partner_type = ($user_type === 'Customer') ? 'Staff' : 'Customer';
// For Staff, we might have multiple people, but usually one handles an order. 
// For now, get the most recently active person who isn't the current user on this order.
$partner_sql = "SELECT last_activity, is_typing FROM user_status 
                WHERE order_id = ? AND user_type = ? 
                ORDER BY last_activity DESC LIMIT 1";
$partner_raw = db_query($partner_sql, 'is', [$order_id, $partner_type]);

$partner = [
    'is_online' => false,
    'is_typing' => false
];

if (!empty($partner_raw)) {
    $last_active = strtotime($partner_raw[0]['last_activity']);
    $partner['is_online'] = (time() - $last_active) < 60; // Online if active in last 60s
    $partner['is_typing'] = (bool)$partner_raw[0]['is_typing'] && $partner['is_online'];
}

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'partner' => $partner
]);
?>
