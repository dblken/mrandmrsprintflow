<?php
/**
 * Branch Migration 2
 * PrintFlow - Printing Shop PWA
 * Safely adds status constraints to branches table
 */

require_once __DIR__ . '/../includes/db.php';

try {
    echo "Starting Branch UI Migration...\n";

    // 1. Check & Add `status` column to `branches`
    $check_status = db_query("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'branches' 
        AND COLUMN_NAME = 'status'
    ");
    
    if (empty($check_status)) {
        db_execute("ALTER TABLE branches ADD COLUMN status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active' AFTER contact_number");
        echo "Added 'status' column to branches.\n";
    } else {
        echo "'status' column already exists in branches.\n";
    }

    // 2. Add INDEX for `status` if it doesn't exist
    $check_index = db_query("
        SELECT INDEX_NAME 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'branches' 
        AND INDEX_NAME = 'idx_branch_status'
    ");

    if (empty($check_index)) {
        db_execute("ALTER TABLE branches ADD INDEX idx_branch_status (status)");
        echo "Added index 'idx_branch_status' to branches.\n";
    }

    // 3. Make `branch_name` UNIQUE if it isn't already
    $check_unique = db_query("
        SELECT INDEX_NAME 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'branches' 
        AND INDEX_NAME = 'unique_branch_name'
    ");

    if (empty($check_unique)) {
        db_execute("ALTER TABLE branches ADD CONSTRAINT unique_branch_name UNIQUE (branch_name)");
        echo "Added UNIQUE constraint 'unique_branch_name' to branches.\n";
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
}
