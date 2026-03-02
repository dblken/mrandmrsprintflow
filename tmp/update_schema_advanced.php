<?php
require 'includes/db.php';

try {
    // Check if notes column exists
    $checkNotes = db_query("SHOW COLUMNS FROM job_order_materials LIKE 'notes'");
    if (empty($checkNotes)) {
        db_execute("ALTER TABLE job_order_materials ADD COLUMN notes TEXT NULL AFTER unit_cost_at_assignment");
    }

    // Check if metadata column exists
    $checkMetadata = db_query("SHOW COLUMNS FROM job_order_materials LIKE 'metadata'");
    if (empty($checkMetadata)) {
        db_execute("ALTER TABLE job_order_materials ADD COLUMN metadata JSON NULL AFTER notes");
    }

    echo "Database schema updated successfully.\n";
} catch (Exception $e) {
    echo "Error updating database schema: " . $e->getMessage() . "\n";
}
?>
