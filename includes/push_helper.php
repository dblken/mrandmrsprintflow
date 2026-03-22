<?php
/**
 * push_helper.php — Web Push dispatch helpers.
 * Requires: includes/WebPush.php, includes/db.php
 */

if (!class_exists('WebPush')) {
    require_once __DIR__ . '/WebPush.php';
}

/**
 * Return a WebPush instance using the stored VAPID config.
 * Returns null if VAPID keys are not configured yet.
 */
function get_webpush(): ?WebPush
{
    static $instance = null;
    if ($instance !== null) return $instance;

    $cfg_file = __DIR__ . '/vapid_config.php';
    if (!file_exists($cfg_file)) return null;

    $cfg = require $cfg_file;
    if (empty($cfg['public_key']) || empty($cfg['private_key'])) return null;

    $instance = new WebPush(
        $cfg['subject']     ?? 'mailto:admin@printflow.com',
        $cfg['public_key'],
        $cfg['private_key']
    );
    return $instance;
}

/**
 * Build a notification URL based on type and context.
 */
function push_url_for_type(string $type, ?int $data_id, string $user_type): string
{
    $base = '/printflow';
    switch ($type) {
        case 'Order':
        case 'New Order':
            // Redirect to chat when order-related (data_id = order_id)
            if ($data_id && $user_type === 'Customer') {
                return $base . '/customer/chat.php?order_id=' . $data_id;
            }
            if ($user_type === 'Customer') {
                return $base . '/customer/orders.php';
            }
            if ($user_type === 'Staff' || $user_type === 'Manager') {
                if ($data_id) {
                    return $base . '/staff/order_details.php?id=' . (int)$data_id;
                }
                return $base . '/staff/notifications.php';
            }
            return $base . '/admin/orders_management.php';
        case 'Job Order':
            return $user_type === 'Customer'
                ? $base . '/customer/new_job_order.php'
                : $base . '/admin/orders_management.php';
        case 'Chat':
        case 'Message':
            return $data_id
                ? $base . '/customer/order_chat.php?order_id=' . $data_id
                : $base . '/customer/orders.php';
        case 'Stock':
        case 'Inventory':
            return $base . '/admin/inv_items_management.php';
        case 'Design':
        case 'Customization':
            if ($data_id && $user_type === 'Customer') {
                return $base . '/customer/chat.php?order_id=' . $data_id;
            }
            return $base . '/admin/orders_management.php';
        case 'Profile':
            return $base . '/admin/user_staff_management.php';
        default:
            return $base . '/';
    }
}

/**
 * Push a notification payload to every subscribed device of one user.
 *
 * @param  int    $user_id
 * @param  string $user_type   'Customer' | 'Admin' | 'Staff' | ...
 * @param  array  $payload     ['title', 'body', 'url', 'tag', 'icon']
 * @param  int    $ttl
 * @return int    Number of successful pushes
 */
function push_notify_user(int $user_id, string $user_type, array $payload, int $ttl = 86400): int
{
    $wp = get_webpush();
    if (!$wp) return 0;

    $rows = db_query(
        'SELECT id, endpoint, p256dh, auth_key FROM push_subscriptions
         WHERE user_id = ? AND user_type = ?',
        'is',
        [$user_id, $user_type]
    );
    if (empty($rows)) return 0;

    // Defaults
    $payload += [
        'title' => 'PrintFlow',
        'icon'  => '/printflow/public/assets/images/icon-192.png',
        'badge' => '/printflow/public/assets/images/icon-72.png',
        'url'   => '/printflow/',
    ];

    $sent = 0;
    foreach ($rows as $row) {
        try {
            $ok = $wp->send(
                ['endpoint' => $row['endpoint'], 'p256dh' => $row['p256dh'], 'auth' => $row['auth_key']],
                $payload,
                $ttl
            );
            if ($ok) $sent++;
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'subscription_expired') {
                db_execute('DELETE FROM push_subscriptions WHERE id = ?', 'i', [(int)$row['id']]);
            } else {
                error_log('[push_notify_user] Unexpected error: ' . $e->getMessage());
            }
        }
    }
    return $sent;
}

/**
 * Push to ALL admin/staff users (useful for order alerts).
 *
 * @param  string[] $user_types  e.g. ['Admin', 'Staff']
 * @param  array    $payload
 * @return int
 */
function push_notify_role(array $user_types, array $payload, int $ttl = 86400): int
{
    $wp = get_webpush();
    if (!$wp) return 0;

    $placeholders = implode(',', array_fill(0, count($user_types), '?'));
    $types        = str_repeat('s', count($user_types));
    $rows = db_query(
        "SELECT id, user_id, user_type, endpoint, p256dh, auth_key
         FROM push_subscriptions WHERE user_type IN ($placeholders)",
        $types,
        $user_types
    );
    if (empty($rows)) return 0;

    $payload += [
        'title' => 'PrintFlow',
        'icon'  => '/printflow/public/assets/images/icon-192.png',
        'badge' => '/printflow/public/assets/images/icon-72.png',
        'url'   => '/printflow/',
    ];

    $sent = 0;
    foreach ($rows as $row) {
        try {
            $ok = $wp->send(
                ['endpoint' => $row['endpoint'], 'p256dh' => $row['p256dh'], 'auth' => $row['auth_key']],
                $payload,
                $ttl
            );
            if ($ok) $sent++;
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'subscription_expired') {
                db_execute('DELETE FROM push_subscriptions WHERE id = ?', 'i', [(int)$row['id']]);
            }
        }
    }
    return $sent;
}
