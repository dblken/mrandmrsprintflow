<?php
/**
 * push_helper.php — Web Push dispatch helpers.
 * Requires: includes/WebPush.php, includes/db.php
 */

if (!class_exists('WebPush')) {
    require_once __DIR__ . '/WebPush.php';
}
require_once __DIR__ . '/vapid_bootstrap.php';

if (!defined('BASE_PATH') && file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/shop_config.php';

function push_base_path(): string
{
    $base = defined('BASE_PATH') ? (string) BASE_PATH : '';
    $base = rtrim(trim($base), '/');

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '' && strpos($host, 'mrandmrsprintflow.com') !== false && $base === '/printflow') {
        $base = '';
    }

    return $base === '/' ? '' : $base;
}

/**
 * Return a WebPush instance using the stored VAPID config.
 * Returns null if VAPID keys are not configured yet.
 */
function get_webpush(): ?WebPush
{
    static $instance = null;
    if ($instance !== null) return $instance;

    $cfg = printflow_vapid_config();
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
    $base = push_base_path();
    $isCustomer = $user_type === 'Customer';
    $isStaff = $user_type === 'Staff';
    $isManager = $user_type === 'Manager';
    $panelBase = $isManager ? ($base . '/manager') : ($base . '/admin');

    switch ($type) {
        case 'Order':
        case 'New Order':
        case 'Payment':
            if ($data_id && $isCustomer) {
                return $base . '/customer/chat.php?order_id=' . $data_id;
            }
            if ($isCustomer) {
                return $base . '/customer/orders.php';
            }
            if ($isStaff) {
                return $data_id
                    ? $base . '/staff/orders.php?order_id=' . (int) $data_id
                    : $base . '/staff/notifications.php';
            }

            return $data_id
                ? (($isManager ? $panelBase . '/orders.php' : $panelBase . '/orders_management.php') . '?open_order=' . (int) $data_id)
                : ($isManager ? $panelBase . '/orders.php' : $panelBase . '/orders_management.php');

        case 'Job Order':
        case 'Payment Issue':
            if ($isCustomer) {
                return $base . '/customer/new_job_order.php';
            }
            if ($isStaff) {
                return $data_id
                    ? $base . '/staff/customizations.php?order_id=' . (int) $data_id . '&job_type=JOB'
                    : $base . '/staff/customizations.php';
            }
            return $data_id
                ? $panelBase . '/job_orders.php?open_job=' . (int) $data_id
                : $panelBase . '/job_orders.php';

        case 'Chat':
        case 'Message':
            if ($isCustomer) {
                return $data_id
                    ? $base . '/customer/chat.php?order_id=' . $data_id
                    : $base . '/customer/orders.php';
            }
            if ($isStaff) {
                return $data_id
                    ? $base . '/staff/chats.php?order_id=' . (int) $data_id
                    : $base . '/staff/chats.php';
            }
            return $data_id
                ? (($isManager ? $panelBase . '/orders.php' : $panelBase . '/orders_management.php') . '?open_order=' . (int) $data_id)
                : ($isManager ? $panelBase . '/orders.php' : $panelBase . '/orders_management.php');

        case 'Stock':
        case 'Inventory':
            if ($isStaff) {
                return $base . '/staff/notifications.php';
            }
            return $panelBase . '/inv_transactions_ledger.php' . ($data_id ? '?item_id=' . (int) $data_id : '');

        case 'Design':
        case 'Customization':
            if ($data_id && $isCustomer) {
                return $base . '/customer/chat.php?order_id=' . $data_id;
            }
            if ($isStaff) {
                return $data_id
                    ? $base . '/staff/customizations.php?order_id=' . (int) $data_id . '&job_type=ORDER'
                    : $base . '/staff/customizations.php';
            }
            return $data_id
                ? (($isManager ? $panelBase . '/orders.php' : $panelBase . '/orders_management.php') . '?open_order=' . (int) $data_id)
                : ($isManager ? $panelBase . '/orders.php' : $panelBase . '/orders_management.php');

        case 'Profile':
            return $isManager
                ? $panelBase . '/notifications.php'
                : $panelBase . '/user_staff_management.php';
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

    $base = push_base_path();
    $icon = !empty($GLOBALS['shop_logo_url']) ? (string)$GLOBALS['shop_logo_url'] : ($base . '/public/assets/images/icon-192.png');
    $badge = $base . '/public/assets/images/icon-72.png';
    $home = $base . '/';

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
        'icon'  => $icon,
        'badge' => $badge,
        'url'   => $home,
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

    $base = push_base_path();
    $icon = !empty($GLOBALS['shop_logo_url']) ? (string)$GLOBALS['shop_logo_url'] : ($base . '/public/assets/images/icon-192.png');
    $badge = $base . '/public/assets/images/icon-72.png';
    $home = $base . '/';

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
        'icon'  => $icon,
        'badge' => $badge,
        'url'   => $home,
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
