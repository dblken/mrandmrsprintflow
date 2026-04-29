<?php
/**
 * Authenticated push debug status for the current user/device.
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/push_debug_helper.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthenticated']);
    exit;
}

$userId = (int)(get_user_id() ?? 0);
$userType = (string)(get_user_type() ?? 'Customer');

db_execute("CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type VARCHAR(32) NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh TEXT NOT NULL,
    auth_key TEXT NOT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_endpoint (endpoint(255)),
    KEY idx_user (user_id, user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$subscriptions = db_query(
    "SELECT id, created_at, last_used, endpoint, user_agent
     FROM push_subscriptions
     WHERE user_id = ? AND user_type = ?
     ORDER BY id DESC
     LIMIT 5",
    'is',
    [$userId, $userType]
) ?: [];

$latestLogs = printflow_push_debug_recent($userId, $userType, 25);

$safeSubscriptions = array_map(static function (array $row): array {
    $endpoint = (string)($row['endpoint'] ?? '');
    return [
        'id' => (int)($row['id'] ?? 0),
        'created_at' => (string)($row['created_at'] ?? ''),
        'last_used' => (string)($row['last_used'] ?? ''),
        'endpoint_hash' => $endpoint !== '' ? hash('sha256', $endpoint) : '',
        'user_agent' => (string)($row['user_agent'] ?? ''),
    ];
}, $subscriptions);

echo json_encode([
    'success' => true,
    'user_id' => $userId,
    'user_type' => $userType,
    'subscription_count' => count($safeSubscriptions),
    'subscriptions' => $safeSubscriptions,
    'recent_logs' => $latestLogs,
], JSON_UNESCAPED_SLASHES);
