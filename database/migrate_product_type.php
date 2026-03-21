<?php
/**
 * Migration: Add product_type to products table
 * Values: 'fixed' (pre-made catalog items) | 'custom' (customizable services)
 */
require_once __DIR__ . '/../includes/db.php';

if (isset($conn)) {
    $check = $conn->query("SHOW COLUMNS FROM products LIKE 'product_type'");
    if ($check && $check->num_rows > 0) {
        echo "OK: product_type column already exists.\n";
    } else {
        $sql = "ALTER TABLE products ADD COLUMN product_type ENUM('fixed','custom') NOT NULL DEFAULT 'custom' AFTER category";
        if ($conn->query($sql)) {
            echo "OK: product_type column added.\n";
        } else {
            echo "ERROR: " . $conn->error . "\n";
            exit(1);
        }
    }
} else {
    echo "ERROR: No database connection.\n";
    exit(1);
}
