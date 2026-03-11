<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Ensure user is staff
if (!is_staff() && !is_admin()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = get_user_id();
$user_type = get_user_type(); // 'Staff' or 'Admin'
$count = get_unread_notification_count($user_id, $user_type);

header('Content-Type: application/json');
echo json_encode(['count' => $count]);
