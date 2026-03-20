<?php
/**
 * Migration: Add 'Profile' to notifications type ENUM
 */
require_once __DIR__ . '/../includes/db.php';

try {
    db_execute("ALTER TABLE notifications MODIFY COLUMN type ENUM('Order','Stock','System','Message','Profile') NOT NULL");
    echo "Migration completed: 'Profile' added to notifications type.\n";
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
