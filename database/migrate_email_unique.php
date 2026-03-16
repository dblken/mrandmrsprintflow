<?php
/**
 * migrate_email_unique.php
 * Adds UNIQUE index on email column for both `users` and `customers` tables.
 * Provides race-condition protection against duplicate email registrations.
 *
 * Run once from browser: http://localhost/printflow/database/migrate_email_unique.php
 * Or from CLI: php migrate_email_unique.php
 */

require_once __DIR__ . '/../includes/db.php';

// Prevent accidental re-runs from the browser without confirmation
echo "<pre>\n";
echo "=== PrintFlow — Email UNIQUE Index Migration ===\n\n";

$tables = [
    'users'     => 'email',
    'customers' => 'email',
];

foreach ($tables as $table => $column) {
    echo "Processing table: `{$table}` column: `{$column}`\n";

    // ── 1. Check for existing duplicate emails ──────────────────
    $dupes = db_query(
        "SELECT {$column}, COUNT(*) AS cnt FROM {$table} GROUP BY {$column} HAVING cnt > 1",
        '', []
    );

    if (!empty($dupes)) {
        echo "  [WARNING] Found " . count($dupes) . " duplicate email(s):\n";
        foreach ($dupes as $row) {
            echo "    - {$row[$column]} ({$row['cnt']} occurrences)\n";
        }
        echo "  Cannot add UNIQUE index while duplicates exist.\n";
        echo "  Please resolve them manually, then re-run this migration.\n\n";
        continue;
    }

    // ── 2. Check if UNIQUE index already exists ─────────────────
    $existing = db_query(
        "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = ?
            AND COLUMN_NAME  = ?
            AND NON_UNIQUE   = 0
          LIMIT 1",
        'ss', [$table, $column]
    );

    if (!empty($existing)) {
        echo "  [SKIP] UNIQUE index already exists ('{$existing[0]['INDEX_NAME']}').\n\n";
        continue;
    }

    // ── 3. Add UNIQUE index ─────────────────────────────────────
    $indexName = "uq_{$table}_{$column}";
    $result = db_execute(
        "ALTER TABLE `{$table}` ADD UNIQUE KEY `{$indexName}` (`{$column}`)"
    );

    if ($result !== false) {
        echo "  [OK] Added UNIQUE index `{$indexName}` on `{$table}`.`{$column}`.\n\n";
    } else {
        echo "  [ERROR] Failed to add UNIQUE index on `{$table}`.`{$column}`.\n\n";
    }
}

echo "=== Migration complete ===\n";
echo "</pre>\n";
