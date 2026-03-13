<?php
/**
 * Migration: Add missing columns to orders table
 * Adds: branch_id, downpayment_amount, payment_type
 */
require_once __DIR__ . '/includes/db.php';

echo "Starting orders table migration v3...\n\n";
ob_flush();
flush();

try {
    global $conn;
    
    // Check and add branch_id
    echo "Checking branch_id column...\n";
    ob_flush(); flush();
    $check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                          WHERE TABLE_NAME='orders' AND COLUMN_NAME='branch_id' 
                          AND TABLE_SCHEMA=DATABASE()");
    if ($check && $check->num_rows === 0) {
        $result = $conn->query("ALTER TABLE orders ADD COLUMN branch_id INT DEFAULT NULL");
        if ($result) {
            echo "✓ Added branch_id column\n";
        } else {
            echo "⚠ branch_id: " . $conn->error . "\n";
        }
    } else {
        echo "✓ branch_id column already exists\n";
    }
    ob_flush(); flush();
    
    // Check and add downpayment_amount
    echo "Checking downpayment_amount column...\n";
    ob_flush(); flush();
    $check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                          WHERE TABLE_NAME='orders' AND COLUMN_NAME='downpayment_amount' 
                          AND TABLE_SCHEMA=DATABASE()");
    if ($check && $check->num_rows === 0) {
        $result = $conn->query("ALTER TABLE orders ADD COLUMN downpayment_amount DECIMAL(10,2) DEFAULT 0");
        if ($result) {
            echo "✓ Added downpayment_amount column\n";
        } else {
            echo "⚠ downpayment_amount: " . $conn->error . "\n";
        }
    } else {
        echo "✓ downpayment_amount column already exists\n";
    }
    ob_flush(); flush();
    
    // Check and add/modify payment_type
    echo "Checking payment_type column...\n";
    ob_flush(); flush();
    $check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                          WHERE TABLE_NAME='orders' AND COLUMN_NAME='payment_type' 
                          AND TABLE_SCHEMA=DATABASE()");
    if ($check && $check->num_rows === 0) {
        $result = $conn->query("ALTER TABLE orders ADD COLUMN payment_type VARCHAR(50) DEFAULT 'full_payment'");
        if ($result) {
            echo "✓ Added payment_type column\n";
        } else {
            echo "⚠ payment_type: " . $conn->error . "\n";
        }
    } else {
        // Try to modify to ensure it's large enough
        $result = $conn->query("ALTER TABLE orders MODIFY COLUMN payment_type VARCHAR(50) DEFAULT 'full_payment'");
        if ($result) {
            echo "✓ Modified payment_type column to VARCHAR(50)\n";
        } else {
            echo "⚠ payment_type already VARCHAR(50) or larger\n";
        }
    }
    ob_flush(); flush();
    
    echo "\n✅ Migration completed successfully!\n";
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
