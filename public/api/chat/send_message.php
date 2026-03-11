<?php
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$user_id = get_user_id();
$user_type = get_user_type();

if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
    exit();
}

$image_path = null;
$message_type = 'text';

// 1. Handle Image Upload
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    require_once __DIR__ . '/../../../includes/functions.php';
    $upload = upload_file($_FILES['image'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'chat');
    if ($upload['success']) {
        $image_path = $upload['file_path'];
        $message_type = 'image';
    } else {
        echo json_encode(['success' => false, 'error' => $upload['error']]);
        exit();
    }
}

if (!$message && !$image_path) {
    echo json_encode(['success' => false, 'error' => 'Empty message']);
    exit();
}

// 2. Save Message
// Map Admin/Manager/Staff to 'Staff'
$db_sender = ($user_type === 'Customer') ? 'Customer' : 'Staff';

$sql = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, image_path, read_receipt) 
        VALUES (?, ?, ?, ?, ?, ?, 0)";
$result = db_execute($sql, 'isisss', [$order_id, $db_sender, $user_id, $message, $message_type, $image_path]);

if ($result) {
    // 3. Create Notification for partner
    $notif_msg = ($db_sender === 'Customer') ? "New message from Customer for Order #$order_id" : "New message from Staff for Order #$order_id";
    
    if ($db_sender === 'Customer') {
        // Notify admin/staff (for simplicity, we can notify the whole staff or just log it)
        // In a real app, you'd notify the assigned staff member.
        // Here we'll just create a general 'Message' type notification.
        create_notification(1, 'User', $notif_msg, 'Message', false, false, $order_id); // Notify Admin (ID 1)
    } else {
        // Notify the customer
        $order = db_query("SELECT customer_id FROM orders WHERE order_id = ?", 'i', [$order_id]);
        if (!empty($order)) {
            create_notification($order[0]['customer_id'], 'Customer', $notif_msg, 'Message', false, false, $order_id);
        }
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
