<?php
require_once __DIR__ . '/includes/db.php';
try {
    // Add order_item_id to job_orders
    db_execute("ALTER TABLE job_orders ADD COLUMN order_item_id INT NULL AFTER customer_id");
    echo "Migration Successful: order_item_id added to job_orders\n";
} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
}
