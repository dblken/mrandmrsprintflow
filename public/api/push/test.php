<?php
/**
 * Send a test push notification to current logged-in user.
 * Useful to verify closed-app/device delivery after subscription.
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/push_helper.php';
require_once __DIR__ . '/../../../includes/push_debug_helper.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$user_id = (int)get_user_id();
$user_type = (string)(get_user_type() ?? 'Customer');
$base = function_exists('printflow_notification_base_path') ? printflow_notification_base_path() : '/printflow';

$payload = [
    'title' => 'PrintFlow Test Notification',
    'body' => 'Push is working on this device. You should also receive this when app/browser is inactive.',
    'tag' => 'pf-test-' . $user_id . '-' . time(),
    'url' => rtrim($base, '/') . '/public/index.php',
    'icon' => rtrim($base, '/') . '/public/assets/images/icon-192.png',
    'badge' => rtrim($base, '/') . '/public/assets/images/icon-72.png',
];

$dispatch = push_dispatch_user($user_id, $user_type, $payload);

$success = ((int)($dispatch['sent'] ?? 0) > 0);

printflow_push_debug_log('push_test_requested', [
    'success' => $success,
    'dispatch' => $dispatch,
], $user_id, $user_type);

echo json_encode([
    'success' => $success,
    'error' => $success ? '' : ((string)($dispatch['last_error'] ?? '') ?: 'push_not_delivered'),
    'dispatch' => $dispatch,
]);
