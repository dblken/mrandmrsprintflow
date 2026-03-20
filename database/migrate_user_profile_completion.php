<?php
/**
 * Migration: Add profile completion and ID validation columns to users table
 */
require_once __DIR__ . '/../includes/db.php';

$cols = db_query("SHOW COLUMNS FROM users");
$existing = array_column($cols, 'Field');

$add = function($col, $def) use ($existing) {
    if (!in_array($col, $existing)) {
        db_execute("ALTER TABLE users ADD COLUMN `$col` $def");
        echo "Added column: $col\n";
    }
};

try {
    $add('profile_completion_token', 'VARCHAR(64) NULL');
    $add('profile_completion_expires', 'DATETIME NULL');
    $add('id_validation_image', 'VARCHAR(255) NULL');
    echo "Migration completed.\n";
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
