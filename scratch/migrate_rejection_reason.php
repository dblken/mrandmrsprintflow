<?php
require_once __DIR__ . '/../includes/db.php';

echo "Ensuring rejection_reason columns exist...\n";

// Check orders table
$columns = db_query("SHOW COLUMNS FROM orders LIKE 'rejection_reason'");
if (empty($columns)) {
    echo "Adding rejection_reason to orders table...\n";
    db_execute("ALTER TABLE orders ADD COLUMN rejection_reason TEXT DEFAULT NULL AFTER revision_reason");
} else {
    echo "rejection_reason already exists in orders table.\n";
}

// Check customizations table
$columns = db_query("SHOW COLUMNS FROM customizations LIKE 'rejection_reason'");
if (empty($columns)) {
    echo "Adding rejection_reason to customizations table...\n";
    db_execute("ALTER TABLE customizations ADD COLUMN rejection_reason TEXT DEFAULT NULL AFTER status");
} else {
    echo "rejection_reason already exists in customizations table.\n";
}

echo "Done.\n";
