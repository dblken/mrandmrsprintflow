<?php
/**
 * Migration: Add `duration` column to `order_messages` and populate voice rows.
 * Usage: php database/migrate_add_duration_order_messages.php
 * IMPORTANT: Back up your database before running this on production.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    echo "Checking for existing 'duration' column in order_messages...\n";
    $col = db_query("SHOW COLUMNS FROM order_messages LIKE 'duration'");
    if (!empty($col)) {
        echo "Column 'duration' already exists — skipping ALTER TABLE.\n";
    } else {
        echo "Adding 'duration' column...\n";
        db_execute("ALTER TABLE order_messages ADD COLUMN duration FLOAT DEFAULT NULL AFTER file_name");
        echo "Added 'duration' column.\n";
    }

    echo "Updating voice messages with default duration 3.0 where duration is NULL or 0...\n";
    $updated = db_execute("UPDATE order_messages SET duration = 3.0 WHERE message_type = 'voice' AND (duration IS NULL OR duration = 0)");
    if ($updated === false) {
        echo "Update failed (db_execute returned false).\n";
    } else {
        echo "Update completed. Affected rows: " . (is_int($updated) ? $updated : 'unknown') . "\n";
    }

    echo "Migration finished.\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
