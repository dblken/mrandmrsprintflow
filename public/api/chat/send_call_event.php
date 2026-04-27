<?php
/**
 * Log call events (missed, ended, etc.) to the chat thread.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$order_id    = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$event_type  = isset($_POST['event_type']) ? trim($_POST['event_type']) : ''; // missed, ended, declined, busy, no_answer
$call_type   = isset($_POST['call_type']) ? trim($_POST['call_type']) : 'voice'; // voice, video
$duration    = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
$caller_id   = isset($_POST['caller_id']) ? (int)$_POST['caller_id'] : 0;
$caller_type = isset($_POST['caller_type']) ? trim($_POST['caller_type']) : '';

if (!$order_id || !$event_type) {
    echo json_encode(['success' => false, 'error' => 'Missing data']);
    exit();
}

// Map roles
$db_sender = ($caller_type === 'Customer') ? 'Customer' : 'Staff';

// Format message
$msg_text = "";
if ($event_type === 'missed' || $event_type === 'no_answer') {
    $msg_text = ($call_type === 'video') ? "Missed Video Call" : "Missed Call";
} elseif ($event_type === 'declined') {
    $msg_text = ($call_type === 'video') ? "Video Call Declined" : "Call Declined";
} elseif ($event_type === 'busy') {
    $msg_text = "Line Busy";
} elseif ($event_type === 'ended') {
    $m = floor($duration / 60);
    $s = $duration % 60;
    $dur_str = ($m > 0) ? "{$m}m {$s}s" : "{$s}s";
    $msg_text = ($call_type === 'video') ? "Video Call Ended • {$dur_str}" : "Call Ended • {$dur_str}";
}

if ($msg_text === "") {
    echo json_encode(['success' => false, 'error' => 'Unknown event type']);
    exit();
}

// Insert into order_messages
$sql = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, file_type, read_receipt)
        VALUES (?, ?, ?, ?, 'call_event', ?, 0)";

$success = db_execute($sql, 'isiss', [
    $order_id, 
    $db_sender, 
    $caller_id, 
    $msg_text, 
    $event_type // we store 'missed', 'ended' etc in file_type column for easier styling
]);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
exit();
?>
