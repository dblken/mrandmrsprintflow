<?php
require_once __DIR__ . '/../includes/functions.php';

// 1. Categories Table
$sql_categories = "
CREATE TABLE IF NOT EXISTS inv_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// 2. Items Table
$sql_items = "
CREATE TABLE IF NOT EXISTS inv_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NULL,
    sku VARCHAR(50) UNIQUE NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    unit VARCHAR(20) DEFAULT 'pcs',
    roll_length_ft DECIMAL(10,2) NULL,
    min_stock_level DECIMAL(10,2) DEFAULT 0.00,
    allow_negative_stock BOOLEAN DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    current_stock DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES inv_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// 3. Inventory Transactions Table
$sql_transactions = "
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    transaction_date DATE NOT NULL,
    transaction_type ENUM('opening_balance', 'purchase', 'issue', 'adjustment_up', 'adjustment_down', 'return', 'transfer_in', 'transfer_out') NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id INT NULL,
    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (item_id) REFERENCES inv_items(id) ON DELETE RESTRICT,
    INDEX idx_item_date (item_id, transaction_date),
    INDEX idx_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    db_execute($sql_categories);
    echo "Categories table created.\n";
    
    db_execute($sql_items);
    echo "Items table created.\n";
    
    db_execute($sql_transactions);
    echo "Transactions table created.\n";
    
    echo "Inventory schema setup complete.\n";
} catch (Exception $e) {
    echo "Error creating schema: " . $e->getMessage() . "\n";
}
