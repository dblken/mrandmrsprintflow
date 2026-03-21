<?php
/**
 * Support chat — conversation expiration cron
 * Run via: php cron/chatbot_expire_conversations.php
 * Or add to crontab: 0 * * * * php /path/to/printflow/cron/chatbot_expire_conversations.php
 *
 * Marks conversations with no activity for 24 hours as expired and archived.
 */
$base = dirname(__DIR__);
require_once $base . '/includes/db.php';
require_once $base . '/includes/functions.php';

$r = db_query("SHOW TABLES LIKE 'chatbot_conversations'");
if (!empty($r)) {
    db_execute("UPDATE chatbot_conversations SET status = 'expired', is_archived = 1 WHERE status != 'expired' AND last_activity_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    echo date('Y-m-d H:i:s') . " Support chat expiration completed.\n";
} else {
    echo date('Y-m-d H:i:s') . " Table chatbot_conversations does not exist. Skipping.\n";
}
