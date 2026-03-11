<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = get_user_id();
$user_type = get_user_type();
$count = get_unread_notification_count($user_id, $user_type);

echo json_encode([
    'success' => true,
    'count' => (int)$count
]);
?>
