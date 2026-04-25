<?php
/**
 * Push queue helper.
 *
 * Stores push delivery jobs so failed background push attempts can be retried
 * safely by cron without blocking user-facing requests.
 */

require_once __DIR__ . '/db.php';

function printflow_ensure_push_queue_table(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    db_execute(
        "CREATE TABLE IF NOT EXISTS push_notification_queue (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            notification_id INT NOT NULL,
            user_id INT NOT NULL,
            user_type VARCHAR(20) NOT NULL DEFAULT 'Customer',
            payload_json LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempt_count INT NOT NULL DEFAULT 0,
            next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_attempt_at DATETIME NULL DEFAULT NULL,
            sent_at DATETIME NULL DEFAULT NULL,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_push_notification_queue_notification_user (notification_id, user_id, user_type),
            KEY idx_push_notification_queue_status_next (status, next_attempt_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $ensured = true;
}

function printflow_enqueue_push_notification(int $notificationId, int $userId, string $userType, array $payload): void
{
    if ($notificationId <= 0 || $userId <= 0 || $userType === '') {
        return;
    }

    printflow_ensure_push_queue_table();

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || $json === '') {
        return;
    }

    $existing = db_query(
        "SELECT id FROM push_notification_queue WHERE notification_id = ? AND user_id = ? AND user_type = ? LIMIT 1",
        'iis',
        [$notificationId, $userId, $userType]
    );

    if (!empty($existing)) {
        db_execute(
            "UPDATE push_notification_queue
             SET payload_json = ?, updated_at = NOW()
             WHERE id = ?",
            'si',
            [$json, (int)$existing[0]['id']]
        );
        return;
    }

    db_execute(
        "INSERT INTO push_notification_queue
            (notification_id, user_id, user_type, payload_json, status, next_attempt_at)
         VALUES (?, ?, ?, ?, 'pending', NOW())",
        'iiss',
        [$notificationId, $userId, $userType, $json]
    );
}

function printflow_mark_push_queue_sent(int $notificationId, int $userId, string $userType): void
{
    printflow_ensure_push_queue_table();

    db_execute(
        "UPDATE push_notification_queue
         SET status = 'sent',
             sent_at = NOW(),
             last_attempt_at = NOW(),
             attempt_count = attempt_count + 1,
             last_error = NULL
         WHERE notification_id = ? AND user_id = ? AND user_type = ?",
        'iis',
        [$notificationId, $userId, $userType]
    );
}

function printflow_mark_push_queue_result(int $queueId, array $dispatch): void
{
    printflow_ensure_push_queue_table();

    $subscriptions = (int)($dispatch['subscriptions'] ?? 0);
    $sent = (int)($dispatch['sent'] ?? 0);
    $failed = (int)($dispatch['failed'] ?? 0);
    $expired = (int)($dispatch['expired'] ?? 0);
    $lastError = trim((string)($dispatch['last_error'] ?? ''));

    if ($sent > 0) {
        db_execute(
            "UPDATE push_notification_queue
             SET status = 'sent',
                 sent_at = NOW(),
                 last_attempt_at = NOW(),
                 attempt_count = attempt_count + 1,
                 last_error = NULL
             WHERE id = ?",
            'i',
            [$queueId]
        );
        return;
    }

    if ($subscriptions <= 0 || $expired > 0 && $failed <= 0) {
        db_execute(
            "UPDATE push_notification_queue
             SET status = 'no_subscription',
                 last_attempt_at = NOW(),
                 attempt_count = attempt_count + 1,
                 last_error = ?
             WHERE id = ?",
            'si',
            [$lastError !== '' ? $lastError : 'no_subscriptions', $queueId]
        );
        return;
    }

    $row = db_query("SELECT attempt_count FROM push_notification_queue WHERE id = ? LIMIT 1", 'i', [$queueId]);
    $attemptCount = (int)($row[0]['attempt_count'] ?? 0) + 1;
    $maxAttempts = 6;
    $delayMinutes = min(60, max(1, (int)pow(2, max(0, $attemptCount - 1))));

    if ($attemptCount >= $maxAttempts) {
        db_execute(
            "UPDATE push_notification_queue
             SET status = 'failed',
                 last_attempt_at = NOW(),
                 attempt_count = ?,
                 last_error = ?
             WHERE id = ?",
            'isi',
            [$attemptCount, $lastError !== '' ? $lastError : 'push_failed', $queueId]
        );
        return;
    }

    db_execute(
        "UPDATE push_notification_queue
         SET status = 'retry',
             last_attempt_at = NOW(),
             attempt_count = ?,
             next_attempt_at = DATE_ADD(NOW(), INTERVAL ? MINUTE),
             last_error = ?
         WHERE id = ?",
        'iisi',
        [$attemptCount, $delayMinutes, $lastError !== '' ? $lastError : 'push_failed', $queueId]
    );
}

function printflow_process_push_queue(int $limit = 25): array
{
    printflow_ensure_push_queue_table();
    require_once __DIR__ . '/push_helper.php';

    $jobs = db_query(
        "SELECT id, notification_id, user_id, user_type, payload_json, attempt_count
         FROM push_notification_queue
         WHERE status IN ('pending', 'retry')
           AND next_attempt_at <= NOW()
         ORDER BY next_attempt_at ASC, id ASC
         LIMIT " . max(1, (int)$limit)
    );

    $summary = [
        'processed' => 0,
        'sent' => 0,
        'retry' => 0,
        'failed' => 0,
        'no_subscription' => 0,
    ];

    foreach ($jobs as $job) {
        $payload = json_decode((string)$job['payload_json'], true);
        if (!is_array($payload)) {
            db_execute(
                "UPDATE push_notification_queue
                 SET status = 'failed',
                     last_attempt_at = NOW(),
                     attempt_count = attempt_count + 1,
                     last_error = 'invalid_payload_json'
                 WHERE id = ?",
                'i',
                [(int)$job['id']]
            );
            $summary['processed']++;
            $summary['failed']++;
            continue;
        }

        $dispatch = push_dispatch_user(
            (int)$job['user_id'],
            (string)$job['user_type'],
            $payload
        );

        printflow_mark_push_queue_result((int)$job['id'], $dispatch);

        $summary['processed']++;
        if ((int)($dispatch['sent'] ?? 0) > 0) {
            $summary['sent']++;
            continue;
        }
        if ((int)($dispatch['subscriptions'] ?? 0) <= 0) {
            $summary['no_subscription']++;
            continue;
        }

        $updated = db_query("SELECT status FROM push_notification_queue WHERE id = ? LIMIT 1", 'i', [(int)$job['id']]);
        $status = (string)($updated[0]['status'] ?? '');
        if ($status === 'retry') {
            $summary['retry']++;
        } else {
            $summary['failed']++;
        }
    }

    return $summary;
}
