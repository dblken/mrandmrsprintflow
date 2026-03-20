<?php
/**
 * Migration: Create customer_cart table for persisting cart across sessions
 * PrintFlow - Cart persistence for logged-in customers
 */
require_once __DIR__ . '/../includes/db.php';

if (isset($conn)) {
    $sql = "CREATE TABLE IF NOT EXISTS customer_cart (
        customer_id INT NOT NULL,
        product_id INT NOT NULL,
        variant_id INT NOT NULL DEFAULT 0,
        quantity INT NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (customer_id, product_id, variant_id),
        FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "OK: customer_cart table created or already exists.\n";
    } else {
        echo "ERROR: " . $conn->error . "\n";
        exit(1);
    }
} else {
    echo "ERROR: No database connection.\n";
    exit(1);
}
