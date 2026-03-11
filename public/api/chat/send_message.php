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

// Map Admin/Manager/Staff to 'Staff'
$db_sender = ($user_type === 'Customer') ? 'Customer' : 'Staff';
$messages_sent = 0;

// 1. Handle Text Message
if (!empty($message)) {
    $sql = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, read_receipt) 
            VALUES (?, ?, ?, ?, 'text', 0)";
    if (db_execute($sql, 'isiss', [$order_id, $db_sender, $user_id, $message])) {
        $messages_sent++;
    }
}

// 2. Handle Multiple Images
if (isset($_FILES['image'])) {
    require_once __DIR__ . '/../../../includes/functions.php';
    $files = $_FILES['image'];
    $is_array = is_array($files['name']);
    $count = $is_array ? count($files['name']) : 1;
    
    for ($i = 0; $i < $count; $i++) {
        $error = $is_array ? $files['error'][$i] : $files['error'];
        if ($error === UPLOAD_ERR_OK) {
            $single_file = [
                'name'     => $is_array ? $files['name'][$i] : $files['name'],
                'type'     => $is_array ? $files['type'][$i] : $files['type'],
                'tmp_name' => $is_array ? $files['tmp_name'][$i] : $files['tmp_name'],
                'error'    => $error,
                'size'     => $is_array ? $files['size'][$i] : $files['size']
            ];
            
            $upload = upload_file($single_file, ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'chat');
            if ($upload['success']) {
                $image_path = $upload['file_path'];
                $sql = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, image_path, read_receipt) 
                        VALUES (?, ?, ?, '', 'image', ?, 0)";
                if (db_execute($sql, 'isiss', [$order_id, $db_sender, $user_id, $image_path])) {
                    $messages_sent++;
                }
            }
        }
    }
}

if ($messages_sent === 0) {
    echo json_encode(['success' => false, 'error' => 'No message or images to send, or upload failed.']);
    exit();
}

// 3. Create Notification for partner
$notif_msg = ($db_sender === 'Customer') ? "New message from Customer for Order #$order_id" : "New message from Staff for Order #$order_id";

if ($db_sender === 'Customer') {
    // Notify admin/staff
    create_notification(1, 'User', $notif_msg, 'Message', false, false, $order_id); 
} else {
    // Notify the customer
    $order = db_query("SELECT customer_id FROM orders WHERE order_id = ?", 'i', [$order_id]);
    if (!empty($order)) {
        create_notification($order[0]['customer_id'], 'Customer', $notif_msg, 'Message', false, false, $order_id);
    }
}

    echo json_encode(['success' => true]);
?>
