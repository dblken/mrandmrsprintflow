<?php
require_once __DIR__ . '/includes/db.php';
$sql = "ALTER TABLE inv_rolls ADD COLUMN width_ft INT NOT NULL AFTER item_id";
if (db_execute($sql)) {
    echo "Successfully added width_ft column to inv_rolls table.\n";
} else {
    echo "FAILED: " . $conn->error . "\n";
}
