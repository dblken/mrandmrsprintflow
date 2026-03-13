<?php
/**
 * One-time migration: Add address columns to customers table
 * Run once via browser at http://localhost/printflow/tmp_address_migration.php
 * DELETE this file after running!
 */
require_once __DIR__ . '/includes/db.php';

$cols = [
    "SHOW COLUMNS FROM customers LIKE 'region'" => "ALTER TABLE customers ADD COLUMN region VARCHAR(100) DEFAULT NULL AFTER gender",
    "SHOW COLUMNS FROM customers LIKE 'province'" => "ALTER TABLE customers ADD COLUMN province VARCHAR(100) DEFAULT NULL AFTER region",
    "SHOW COLUMNS FROM customers LIKE 'city'" => "ALTER TABLE customers ADD COLUMN city VARCHAR(100) DEFAULT NULL AFTER province",
    "SHOW COLUMNS FROM customers LIKE 'barangay'" => "ALTER TABLE customers ADD COLUMN barangay VARCHAR(150) DEFAULT NULL AFTER city",
    "SHOW COLUMNS FROM customers LIKE 'street_address'" => "ALTER TABLE customers ADD COLUMN street_address TEXT DEFAULT NULL AFTER barangay",
];

echo "<h2>Address Migration</h2><ul>";
foreach ($cols as $check => $alter) {
    $res = $conn->query($check);
    if ($res && $res->num_rows === 0) {
        if ($conn->query($alter)) {
            echo "<li>✅ Added: " . htmlspecialchars($alter) . "</li>";
        } else {
            echo "<li>❌ Failed: " . htmlspecialchars($conn->error) . "</li>";
        }
    } else {
        echo "<li>⚠️ Already exists — skipped: " . htmlspecialchars($check) . "</li>";
    }
}
echo "</ul><p>✅ Migration complete. <strong>Delete this file now!</strong></p>";
