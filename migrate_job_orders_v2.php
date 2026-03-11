<?php
require_once __DIR__ . '/includes/db.php';
try {
    // Add customer_type to job_orders
    db_execute("ALTER TABLE job_orders ADD COLUMN customer_type ENUM('NEW', 'REGULAR') DEFAULT 'NEW' AFTER status");
    echo "Migration Successful: customer_type added to job_orders\n";
} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
}
