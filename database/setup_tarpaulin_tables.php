<?php
/**
 * Setup Tarpaulin Specialized Tables (Robust Version)
 */
require_once __DIR__ . '/../includes/db.php';

// 1. Create Tarpaulin Rolls Table
$conn->query("CREATE TABLE IF NOT EXISTS inv_tarp_rolls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    roll_code VARCHAR(30) UNIQUE NOT NULL,
    width_ft INT NOT NULL,
    total_length_ft DECIMAL(10,2) DEFAULT 164.00,
    remaining_length_ft DECIMAL(10,2) NOT NULL,
    status ENUM('OPEN', 'FINISHED', 'VOID') DEFAULT 'OPEN',
    supplier VARCHAR(100) NULL,
    unit_cost DECIMAL(10,2) NULL,
    received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    FOREIGN KEY (item_id) REFERENCES inv_items(id) ON DELETE RESTRICT,
    INDEX idx_width_status (width_ft, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 2. Create Order Tarpaulin Details Table
$conn->query("CREATE TABLE IF NOT EXISTS order_tarp_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT NOT NULL,
    roll_id INT NOT NULL,
    width_ft INT NOT NULL,
    height_ft DECIMAL(10,2) NOT NULL,
    qty INT NOT NULL,
    required_length_ft DECIMAL(10,2) NOT NULL,
    is_deducted TINYINT(1) DEFAULT 0,
    deducted_at DATETIME NULL,
    FOREIGN KEY (order_item_id) REFERENCES order_items(order_item_id) ON DELETE CASCADE,
    FOREIGN KEY (roll_id) REFERENCES inv_tarp_rolls(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 3. Add item_type column to inventory_transactions if missing
$checkCol = $conn->query("SHOW COLUMNS FROM inventory_transactions LIKE 'item_type'");
if ($checkCol->num_rows == 0) {
    echo "Adding item_type column...\n";
    $conn->query("ALTER TABLE inventory_transactions ADD COLUMN item_type ENUM('STANDARD', 'TARPAULIN_ROLL') DEFAULT 'STANDARD' AFTER item_id");
}

// 4. Add unique index to inventory_transactions if missing
$checkIndex = $conn->query("SHOW INDEX FROM inventory_transactions WHERE Key_name = 'uniq_ref'");
if ($checkIndex->num_rows == 0) {
    echo "Adding unique index uniq_ref...\n";
    // Check if we need to clean up duplicates first? For now assume it's clean on a new setup or handle error
    if (!$conn->query("ALTER TABLE inventory_transactions ADD UNIQUE INDEX uniq_ref (reference_type, reference_id, item_type, item_id)")) {
        echo "Warning adding index: " . $conn->error . "\n";
    }
}

echo "Setup complete.\n";
?>
