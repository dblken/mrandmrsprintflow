<?php
/**
 * migrate_pending_review_to_pending.php
 * Updates any "Pending Review" status to "Pending" for consistency.
 *
 * Run once from browser: http://localhost/printflow/database/migrate_pending_review_to_pending.php
 * Or from CLI: php migrate_pending_review_to_pending.php
 */

require_once __DIR__ . '/../includes/db.php';

echo "<pre>\n";
echo "=== PrintFlow — Pending Review → Pending Migration ===\n\n";

$updates = [
    ['orders', 'status'],
    ['service_orders', 'status'],
];

foreach ($updates as [$table, $col]) {
    echo "Processing table: `{$table}` column: `{$col}`\n";

    // Check if table exists
    $exists = db_query("SHOW TABLES LIKE ?", 's', [$table]);
    if (empty($exists)) {
        echo "  [SKIP] Table `{$table}` does not exist.\n\n";
        continue;
    }

    // Check if column exists
    $cols = db_query("SHOW COLUMNS FROM `{$table}` LIKE ?", 's', [$col]);
    if (empty($cols)) {
        echo "  [SKIP] Column `{$col}` does not exist in `{$table}`.\n\n";
        continue;
    }

    // Count rows with Pending Review
    $count = db_query("SELECT COUNT(*) as c FROM `{$table}` WHERE `{$col}` = 'Pending Review'", '', []);
    $n = $count[0]['c'] ?? 0;

    if ($n === 0) {
        echo "  [OK] No rows with 'Pending Review'.\n\n";
        continue;
    }

    $ok = db_execute("UPDATE `{$table}` SET `{$col}` = 'Pending' WHERE `{$col}` = 'Pending Review'", '', []);
    if ($ok !== false) {
        echo "  [OK] Updated {$n} row(s) from 'Pending Review' to 'Pending'.\n\n";
    } else {
        echo "  [ERROR] Failed to update.\n\n";
    }
}

echo "=== Migration complete ===\n";
echo "</pre>\n";
