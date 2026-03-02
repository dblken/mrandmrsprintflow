<?php
require_once __DIR__ . '/../includes/db.php';

// Check current enum values
$result = db_query("SHOW COLUMNS FROM job_orders LIKE 'status'");
echo "Current status column: " . $result[0]['Type'] . "\n";

// Alter the table to add new statuses
$alter_sql = "ALTER TABLE job_orders MODIFY COLUMN status ENUM('PENDING','APPROVED','TO_PAY','IN_PRODUCTION','TO_RECEIVE','COMPLETED','CANCELLED') DEFAULT 'PENDING'";
if ($conn->query($alter_sql) === TRUE) {
    echo "Table job_orders altered successfully.\n";
} else {
    echo "Error altering table: " . $conn->error . "\n";
}
?>
