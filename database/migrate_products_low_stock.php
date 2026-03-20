<?php
/**
 * Migration: Add low_stock_level to products table
 * Default: 10. Used for automatic stock status (Out of Stock / Low Stock / In Stock).
 */
require_once __DIR__ . '/../includes/db.php';

if (isset($conn)) {
    $check = $conn->query("SHOW COLUMNS FROM products LIKE 'low_stock_level'");
    if ($check && $check->num_rows > 0) {
        echo "OK: low_stock_level column already exists.\n";
    } else {
        $sql = "ALTER TABLE products ADD COLUMN low_stock_level INT NOT NULL DEFAULT 10 AFTER stock_quantity";
        if ($conn->query($sql)) {
            echo "OK: low_stock_level column added.\n";
        } else {
            echo "ERROR: " . $conn->error . "\n";
            exit(1);
        }
    }
} else {
    echo "ERROR: No database connection.\n";
    exit(1);
}
