<?php
/**
 * Ensures at least one sample branch exists (for local/demo DBs).
 * Run from project root: php database/seed_sample_branch.php
 *
 * Skips if any branch row already exists.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/db.php';

$row = db_query('SELECT COUNT(*) AS c FROM branches');
$count = (int)($row[0]['c'] ?? 0);
if ($count > 0) {
    fwrite(STDOUT, "Branches table already has {$count} row(s). Nothing to do.\n");
    exit(0);
}

$ok = db_execute(
    "INSERT INTO branches (branch_name, email, address, address_line, barangay, city, province, contact_number, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW())",
    'ssssssss',
    [
        'Sample — Main Branch',
        'main@printflow.local',
        '123 Sample Street',
        '',
        'Sample Barangay',
        'Quezon City',
        'Metro Manila',
        '09171234567',
    ]
);

if ($ok !== false) {
    $idMsg = is_int($ok) ? " (new id: {$ok})" : '';
    fwrite(STDOUT, "Inserted sample branch \"Sample — Main Branch\"{$idMsg}.\n");
    exit(0);
}

fwrite(STDERR, "Failed to insert sample branch.\n");
exit(1);
