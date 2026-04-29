<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/branch_context.php';
require_once __DIR__ . '/../../../includes/ensure_chat_schema.php';

// Prevent accidental output (notices, etc.) from breaking JSON
ob_start();

header('Content-Type: application/json');

if (!is_logged_in()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$reply_id = isset($_POST['reply_id']) && (int)$_POST['reply_id'] > 0 ? (int)$_POST['reply_id'] : null;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
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
$db_sender = ($user_type === 'Customer') ? 'Customer' : 'Staff';
$messages_sent = 0;

$file_signatures = [];
if (isset($_FILES['image'])) {
    $files = $_FILES['image'];
    $is_array = is_array($files['name']);
    $count = $is_array ? count($files['name']) : 1;

    for ($i = 0; $i < $count; $i++) {
        $error = $is_array ? $files['error'][$i] : $files['error'];
        if ($error !== UPLOAD_ERR_OK) {
            continue;
        }

        $file_signatures[] = [
            'name' => (string)($is_array ? $files['name'][$i] : $files['name']),
            'size' => (int)($is_array ? $files['size'][$i] : $files['size']),
        ];
    }
}

$dedupe_payload = [
    'order_id' => $order_id,
    'sender' => $db_sender,
    'sender_id' => (int)$user_id,
    'reply_id' => $reply_id,
    'message' => $message,
    'files' => $file_signatures,
];
$dedupe_hash = hash('sha256', json_encode($dedupe_payload));
$dedupe_guard = $_SESSION['chat_submit_guard'] ?? null;

if (
    is_array($dedupe_guard)
    && ($dedupe_guard['hash'] ?? '') === $dedupe_hash
    && isset($dedupe_guard['time'])
    && (microtime(true) - (float)$dedupe_guard['time']) < 2.5
) {
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'messages_sent' => 0,
        'duplicate_ignored' => true,
    ]);
    exit();
}

$_SESSION['chat_submit_guard'] = [
    'hash' => $dedupe_hash,
    'time' => microtime(true),
];

// 1. Handle text message
if ($message !== '') {
    $sql = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, read_receipt, reply_id)
            VALUES (?, ?, ?, ?, 'text', 0, ?)";
    if (db_execute($sql, 'isisi', [$order_id, $db_sender, $user_id, $message, $reply_id])) {
        $messages_sent++;
    }
}

// 2. Handle multiple files (images/videos)
if (isset($_FILES['image'])) {
    $files = $_FILES['image'];
    $is_array = is_array($files['name']);
    $count = $is_array ? count($files['name']) : 1;

    for ($i = 0; $i < $count; $i++) {
        $error = $is_array ? $files['error'][$i] : $files['error'];
        if ($error !== UPLOAD_ERR_OK) continue;

        $name = $is_array ? $files['name'][$i] : $files['name'];
        $single_file = [
            'name'     => $name,
            'type'     => $is_array ? $files['type'][$i] : $files['type'],
            'tmp_name' => $is_array ? $files['tmp_name'][$i] : $files['tmp_name'],
            'error'    => $error,
            'size'     => $is_array ? $files['size'][$i] : $files['size'],
        ];

        // Process file (up to 50MB) 
        // We use the 'chat' folder destination, let's keep allowed extensions explicit.
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $is_video = in_array($ext, ['mp4', 'webm', 'mov']);
        $file_type = $is_video ? 'video' : 'image';
        $dest_folder = $is_video ? 'chat/videos' : 'chat/images';

        $upload = upload_file($single_file, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov'], $dest_folder, null, 50 * 1024 * 1024);
        if (!($upload['success'] ?? false)) continue;

        $image_path = (string)$upload['file_path'];
        $msg_type = $is_video ? 'video' : 'image';

        $sql = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, image_path, file_type, file_path, message_file, file_name, file_size, read_receipt, reply_id)
                VALUES (?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?, 0, ?)";
        
        if (db_execute($sql, 'isissssssii', [
            $order_id, $db_sender, $user_id, 
            $msg_type, 
            $image_path, 
            $file_type, 
            $image_path, 
            $image_path, 
            $name, 
            $single_file['size'], 
            $reply_id
        ])) {
            $messages_sent++;
        }
    }
}

if ($messages_sent === 0) {
    echo json_encode(['success' => false, 'error' => 'No message or images were sent.']);
    exit();
}

// 3. Notify the opposite side so chat messages also trigger real notifications.
$message_kind = 'message';
if ($message === '' && isset($_FILES['image'])) {
    $message_kind = 'attachment';
}
printflow_notify_chat_message($order_id, $db_sender, $message_kind);


// Clear accidental output before sending JSON
ob_end_clean();
echo json_encode(['success' => true, 'messages_sent' => $messages_sent]);
?>
