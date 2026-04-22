<?php
/**
 * push/poll.php — Lightweight in-tab notification poll.
 * GET ?since=<unix_timestamp>
 * Returns new notifications created after `since` for the logged-in user.
 * Used as fallback when the tab is open (in-tab toasts); the service worker
 * handles background push when the tab is closed.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/branch_context.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'notifications' => []]);
    exit;
}

$user_id   = (int) get_user_id();
$user_type = get_user_type() ?? 'Customer';
$since     = isset($_GET['since']) ? (int) $_GET['since'] : (time() - 30);

// Pull notifications newer than the timestamp
if ($user_type === 'Customer') {
    $rows = db_query(
        "SELECT notification_id AS id, notification_id, message, type, data_id, is_read,
                UNIX_TIMESTAMP(created_at) AS ts
         FROM notifications
         WHERE customer_id = ? AND UNIX_TIMESTAMP(created_at) > ?
         ORDER BY created_at ASC
         LIMIT 20",
        'ii',
        [$user_id, $since]
    );
} else {
    $rows = db_query(
        "SELECT notification_id AS id, message, type, data_id, is_read,
                UNIX_TIMESTAMP(created_at) AS ts
         FROM notifications
         WHERE user_id = ? AND UNIX_TIMESTAMP(created_at) > ?
         ORDER BY created_at ASC
         LIMIT 20",
        'ii',
        [$user_id, $since]
    );
    $branchId = in_array($user_type, ['Staff', 'Manager'], true)
        ? (printflow_branch_filter_for_user() ?? 0)
        : 0;
    $rows = printflow_filter_notifications_for_user($rows ?: [], (string)$user_type, $branchId > 0 ? (int)$branchId : null);
}

foreach ($rows as &$row) {
    $row['target_url'] = printflow_notification_target_url_for_user((string)$user_type, $row);
    if ($user_type === 'Customer') {
        $base = defined('BASE_URL') ? BASE_URL : '/printflow';
        $fallback = $base . '/public/assets/images/services/default.png';
        $row['title'] = customer_notification_title((string)($row['type'] ?? ''), (string)($row['message'] ?? ''));
        $row['image'] = customer_notification_image_url($row, $fallback);
        $row['fallback'] = $fallback;
    } else {
        $base = defined('BASE_URL') ? BASE_URL : '/printflow';
        $fallback = $base . '/public/assets/images/services/default.png';
        $row['message'] = printflow_notification_display_message($row);
        $row['image'] = staff_admin_notification_image_url($row, $fallback);
        $row['fallback'] = $fallback;
    }
}
unset($row);

// Unread count
$unread = get_unread_notification_count($user_id, $user_type);

echo json_encode([
    'success'       => true,
    'notifications' => $rows ?: [],
    'unread_count'  => (int) $unread,
    'server_time'   => time(),
]);
