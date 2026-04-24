<?php
/**
 * push/self_test.php
 * Sends a real Web Push test message to the currently logged-in user's saved devices.
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/push_helper.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$userId = (int)get_user_id();
$userType = (string)(get_user_type() ?? 'Customer');
$subscriptionCount = function_exists('push_subscription_count_for_user')
    ? push_subscription_count_for_user($userId, $userType)
    : 0;

if ($subscriptionCount <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'No saved push subscriptions for this account/device yet.',
        'subscription_count' => 0,
        'sent_count' => 0,
        'user_type' => $userType,
    ]);
    exit;
}

$base = function_exists('printflow_notification_base_path') ? printflow_notification_base_path() : '';
$role = strtolower($userType);
$target = $base . '/';
if (in_array($role, ['admin', 'manager', 'staff', 'customer'], true)) {
    $target = $base . '/' . $role . '/notifications.php';
}

$timestamp = date('Y-m-d H:i:s');
$payload = [
    'title' => 'PrintFlow Push Test',
    'body' => 'Background push test sent at ' . $timestamp,
    'tag' => 'pf-self-test-' . $userId,
    'url' => $target,
];

$sentCount = push_notify_user($userId, $userType, $payload, 300);

echo json_encode([
    'success' => $sentCount > 0,
    'subscription_count' => $subscriptionCount,
    'sent_count' => $sentCount,
    'user_type' => $userType,
    'target_url' => $target,
    'sent_at' => $timestamp,
    'error' => $sentCount > 0 ? null : 'Push send reached 0 successful deliveries. Check server error logs for [WebPush] or [push_notify_user].',
]);
