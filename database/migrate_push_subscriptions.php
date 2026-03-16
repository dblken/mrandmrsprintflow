<?php
/**
 * migrate_push_subscriptions.php
 * Creates the push_subscriptions table for Web Push / PWA notifications.
 * Run once: http://localhost/printflow/database/migrate_push_subscriptions.php
 */
require_once __DIR__ . '/../includes/db.php';

echo "<pre>\n=== PrintFlow — Push Subscriptions Migration ===\n\n";

$sql = "CREATE TABLE IF NOT EXISTS push_subscriptions (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id      INT              NOT NULL,
    user_type    VARCHAR(20)      NOT NULL DEFAULT 'Customer',
    endpoint     TEXT             NOT NULL,
    p256dh       VARCHAR(512)     NOT NULL,
    auth_key     VARCHAR(128)     NOT NULL,
    user_agent   VARCHAR(255)     DEFAULT NULL,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY   uq_endpoint (endpoint(500)),
    KEY          idx_user (user_id, user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

global $conn;
if ($conn->query($sql) !== false) {
    echo "[OK] Table 'push_subscriptions' created (or already exists).\n";
} else {
    echo "[ERROR] " . $conn->error . "\n";
}

echo "\n=== Migration complete ===\n</pre>\n";
