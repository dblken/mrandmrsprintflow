<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/branch_context.php';
require_once __DIR__ . '/../../../includes/ensure_chat_schema.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if (!isset($_FILES['voice'])) {
    echo json_encode(['success' => false, 'error' => 'No voice data received']);
    exit();
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
    exit();
}

$file = $_FILES['voice'];
$user_id = get_user_id();
$user_type = get_user_type();
$db_sender = ($user_type === 'Customer') ? 'Customer' : 'Staff';

if ($user_type !== 'Customer') {
    printflow_assert_order_branch_access($order_id);
}

// Validate size (max ~10MB)
if ($file['size'] > 10000000) {
    echo json_encode(['success' => false, 'error' => 'File too large (Max 10MB)']);
    exit();
}

// Ensure directory exists - standardized folder
$upload_dir = __DIR__ . '/../../../uploads/chat/audio/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$filename = uniqid() . ".webm";
$target_path = $upload_dir . $filename;
$base_path = rtrim(defined('BASE_PATH') ? BASE_PATH : (defined('AUTH_REDIRECT_BASE') ? AUTH_REDIRECT_BASE : '/printflow'), '/');
$relative_path = ($base_path === '' ? '' : $base_path) . '/uploads/chat/audio/' . $filename;

if (move_uploaded_file($file['tmp_name'], $target_path)) {
    // Save to DB
    $sql = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, message_file, file_type, file_path, read_receipt)
            VALUES (?, ?, ?, '', 'voice', ?, 'voice', ?, 0)";
    
    if (db_execute($sql, 'isiss', [$order_id, $db_sender, $user_id, $relative_path, $relative_path])) {
        printflow_notify_chat_message($order_id, $db_sender, 'voice');
        echo json_encode(['success' => true, 'file' => $relative_path]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database insertion failed']);
    }
} else {
    $error_msg = error_get_last()['message'] ?? 'Check PHP upload limits or folder permissions';
    echo json_encode(['success' => false, 'error' => 'Failed to save recording: ' . $error_msg]);
}
