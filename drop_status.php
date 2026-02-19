<?php
require_once __DIR__ . '/includes/db.php';

// disable foreign key checks just in case
db_execute("SET FOREIGN_KEY_CHECKS=0");

try {
    // Check if column exists
    $columns = db_query("SHOW COLUMNS FROM customers LIKE 'status'");
    if (!empty($columns)) {
        echo "Dropping status column...<br>";
        db_execute("ALTER TABLE customers DROP COLUMN status");
        echo "Status column dropped.<br>";
    } else {
        echo "Status column does not exist.<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

db_execute("SET FOREIGN_KEY_CHECKS=1");
echo "Done.";
?>
