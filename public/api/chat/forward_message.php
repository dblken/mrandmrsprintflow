<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/branch_context.php';
require_once __DIR__ . '/../../../includes/ensure_chat_schema.php';

ob_start();
header('Content-Type: application/json');

if (!is_logged_in()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$target_order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$original_message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$user_id = get_user_id();
$user_type = get_user_type();

if (!$target_order_id || !$original_message_id) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Missing target order ID or original message ID']);
    exit();
}

// 1. Fetch the original message
$orig = db_query("SELECT * FROM order_messages WHERE message_id = ?", 'i', [$original_message_id]);
if (!$orig) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Original message not found']);
    exit();
}
$orig = $orig[0];

// 2. Access control for target order
if ($user_type !== 'Customer') {
    // Staff must have access to the target branch
    try {
        printflow_assert_order_branch_access($target_order_id);
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
} else {
    // Customer must own the target order
    $order_check = db_query("SELECT customer_id FROM orders WHERE order_id = ?", 'i', [$target_order_id]);
    if (!$order_check || $order_check[0]['customer_id'] != $user_id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Unauthorized access to target order']);
        exit();
    }
}

// 3. Prepare the forwarded message
$db_sender = ($user_type === 'Customer') ? 'Customer' : 'Staff';
$message_text = $orig['message'];
$message_type = $orig['message_type'];

// If it's a text message, add [Forwarded] prefix if not already present
if ($message_type === 'text' || $message_type === 'message') {
    if (strpos($message_text, '[Forwarded]') === false) {
        $message_text = "[Forwarded]: " . $message_text;
    }
} else {
    // For media, the message text might be empty or a caption.
    // If it's empty, we can just say [Forwarded Attachment] in the text field if we want,
    // but the actual media fields will carry the content.
    if (empty($message_text)) {
        // Optional: keep it empty or set to [Forwarded Attachment]
        // Let's keep it consistent with the user's view if they add text.
    } else {
        if (strpos($message_text, '[Forwarded]') === false) {
            $message_text = "[Forwarded]: " . $message_text;
        }
    }
}

// 4. Insert the new message
$sql = "INSERT INTO order_messages (
            order_id, sender, sender_id, message, message_type, 
            image_path, file_type, file_path, message_file, 
            file_name, file_size, read_receipt
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";

$success = db_execute($sql, 'isississssi', [
    $target_order_id, 
    $db_sender, 
    $user_id, 
    $message_text, 
    $message_type,
    $orig['image_path'],
    $orig['file_type'],
    $orig['file_path'],
    $orig['message_file'],
    $orig['file_name'],
    $orig['file_size']
]);

if ($success) {
    // 5. Notify the opposite side
    $message_kind = ($message_type === 'text' || $message_type === 'message') ? 'message' : 'attachment';
    printflow_notify_chat_message($target_order_id, $db_sender, $message_kind);

    ob_end_clean();
    echo json_encode(['success' => true]);
} else {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Failed to insert forwarded message']);
}
?>
