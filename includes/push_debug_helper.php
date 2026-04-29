<?php
/**
 * Push notification debug helpers.
 *
 * Stores lightweight diagnostics so we can identify which layer fails:
 * service worker lifecycle, subscription save, push send, queue processing,
 * or notification display.
 */

require_once __DIR__ . '/db.php';

function printflow_push_debug_enabled(): bool
{
    return true;
}

function printflow_ensure_push_debug_table(): void
{
    static $ensured = false;
    if ($ensured || !printflow_push_debug_enabled()) {
        return;
    }

    db_execute(
        "CREATE TABLE IF NOT EXISTS push_debug_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(80) NOT NULL,
            user_id INT NOT NULL DEFAULT 0,
            user_type VARCHAR(32) NOT NULL DEFAULT '',
            endpoint_hash VARCHAR(64) NOT NULL DEFAULT '',
            payload_json LONGTEXT NULL,
            request_uri VARCHAR(255) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_push_debug_user (user_id, user_type, created_at),
            KEY idx_push_debug_event (event_type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $ensured = true;
}

function printflow_push_debug_log(string $eventType, array $payload = [], int $userId = 0, string $userType = '', string $endpoint = ''): void
{
    if (!printflow_push_debug_enabled()) {
        return;
    }

    printflow_ensure_push_debug_table();

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        $json = '{}';
    }

    $endpointHash = $endpoint !== '' ? hash('sha256', $endpoint) : '';
    $requestUri = substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 255);
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    db_execute(
        "INSERT INTO push_debug_log
            (event_type, user_id, user_type, endpoint_hash, payload_json, request_uri, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        'sisssss',
        [$eventType, max(0, $userId), $userType, $endpointHash, $json, $requestUri, $userAgent]
    );
}

function printflow_push_debug_recent(int $userId = 0, string $userType = '', int $limit = 30): array
{
    if (!printflow_push_debug_enabled()) {
        return [];
    }

    printflow_ensure_push_debug_table();

    $limit = max(1, min(100, $limit));
    if ($userId > 0 && $userType !== '') {
        return db_query(
            "SELECT id, event_type, user_id, user_type, payload_json, created_at
             FROM push_debug_log
             WHERE user_id = ? AND user_type = ?
             ORDER BY id DESC
             LIMIT {$limit}",
            'is',
            [$userId, $userType]
        ) ?: [];
    }

    return db_query(
        "SELECT id, event_type, user_id, user_type, payload_json, created_at
         FROM push_debug_log
         ORDER BY id DESC
         LIMIT {$limit}"
    ) ?: [];
}
