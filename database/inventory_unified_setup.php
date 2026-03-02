<?php
/**
 * Unified Inventory Schema Setup (Robust Version)
 * Aligns the system with the final professional spec.
 */
require_once __DIR__ . '/../includes/db.php';

echo "Starting Inventory Module Schema Update...\n";

function columnExists($table, $column) {
    global $conn;
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res->num_rows > 0;
}

// 1. Categories
$conn->query("CREATE TABLE IF NOT EXISTS inv_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 2. Items Table
$checkItems = $conn->query("SHOW TABLES LIKE 'inv_items'");
if ($checkItems->num_rows == 0) {
    echo "Creating inv_items table...\n";
    $conn->query("CREATE TABLE inv_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        unit_of_measure VARCHAR(20) DEFAULT 'pcs',
        track_by_roll TINYINT(1) DEFAULT 0,
        default_roll_length_ft DECIMAL(10,2) NULL,
        reorder_level DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES inv_categories(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} else {
    echo "Updating existing inv_items table...\n";
    if (columnExists('inv_items', 'unit')) {
        $conn->query("ALTER TABLE inv_items CHANGE COLUMN unit unit_of_measure VARCHAR(20) DEFAULT 'pcs'");
    }
    if (columnExists('inv_items', 'min_stock_level')) {
        $conn->query("ALTER TABLE inv_items CHANGE COLUMN min_stock_level reorder_level DECIMAL(10,2) DEFAULT 0.00");
    }
    if (!columnExists('inv_items', 'track_by_roll')) {
        $conn->query("ALTER TABLE inv_items ADD COLUMN track_by_roll TINYINT(1) DEFAULT 0 AFTER unit_of_measure");
    }
    if (!columnExists('inv_items', 'default_roll_length_ft')) {
        $conn->query("ALTER TABLE inv_items ADD COLUMN default_roll_length_ft DECIMAL(10,2) NULL AFTER track_by_roll");
    }
}

// 3. Rolls Table
$checkTarpRolls = $conn->query("SHOW TABLES LIKE 'inv_tarp_rolls'");
$checkRolls = $conn->query("SHOW TABLES LIKE 'inv_rolls'");
if ($checkTarpRolls->num_rows > 0 && $checkRolls->num_rows == 0) {
    echo "Renaming inv_tarp_rolls to inv_rolls...\n";
    $conn->query("RENAME TABLE inv_tarp_rolls TO inv_rolls");
} elseif ($checkRolls->num_rows == 0) {
    echo "Creating inv_rolls table...\n";
    $conn->query("CREATE TABLE inv_rolls (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        roll_code VARCHAR(30) UNIQUE NOT NULL,
        width_ft INT NULL,
        total_length_ft DECIMAL(10,2) NOT NULL,
        remaining_length_ft DECIMAL(10,2) NOT NULL,
        status ENUM('OPEN', 'FINISHED', 'VOID') DEFAULT 'OPEN',
        received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES inv_items(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// 4. Inventory Transactions
echo "Updating inventory_transactions table...\n";
if (!columnExists('inventory_transactions', 'roll_id')) {
    $conn->query("ALTER TABLE inventory_transactions ADD COLUMN roll_id INT NULL AFTER item_id");
}
if (!columnExists('inventory_transactions', 'direction')) {
    $conn->query("ALTER TABLE inventory_transactions ADD COLUMN direction ENUM('IN', 'OUT') NULL AFTER roll_id");
}
$conn->query("UPDATE inventory_transactions SET direction = IF(quantity >= 0, 'IN', 'OUT') WHERE direction IS NULL");

if (!columnExists('inventory_transactions', 'uom')) {
    $conn->query("ALTER TABLE inventory_transactions ADD COLUMN uom VARCHAR(20) NULL AFTER quantity");
}
if (columnExists('inventory_transactions', 'reference_type')) {
    $conn->query("ALTER TABLE inventory_transactions CHANGE COLUMN reference_type ref_type VARCHAR(50) NULL");
}
if (columnExists('inventory_transactions', 'reference_id')) {
    $conn->query("ALTER TABLE inventory_transactions CHANGE COLUMN reference_id ref_id INT NULL");
}

echo "Schema update complete.\n";
?>
