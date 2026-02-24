<?php
// migrate_branches.php
require_once __DIR__ . '/../includes/db.php';

echo "Starting Branch Migration...\n";

// 1. Create branches table
$createBranches = "
CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(255) NOT NULL,
    address TEXT,
    contact_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
db_execute($createBranches);
echo "Branches table checked/created.\n";

// Insert default main branch
db_execute("INSERT IGNORE INTO branches (id, branch_name) VALUES (1, 'Main Branch')");
echo "Main branch inserted.\n";

// 2. Modify users table
$checkUserCol = db_query("
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'branch_id'
");
if (empty($checkUserCol)) {
    db_execute("ALTER TABLE users ADD COLUMN branch_id INT NULL");
    db_execute("ALTER TABLE users ADD CONSTRAINT fk_user_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL");
    echo "users table updated.\n";
} else {
    echo "users table already has branch_id.\n";
}

// 3. Create branch_inventory table
$createBranchInventory = "
CREATE TABLE IF NOT EXISTS branch_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    material_id INT NOT NULL,
    stock_quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    CONSTRAINT fk_inventory_material FOREIGN KEY (material_id) REFERENCES materials(material_id) ON DELETE CASCADE,
    UNIQUE KEY unique_branch_material (branch_id, material_id)
)";
db_execute($createBranchInventory);
echo "branch_inventory table checked/created.\n";

// Migrate existing inventory
$checkOldInventory = db_query("
    SELECT * FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory'
");
if (!empty($checkOldInventory)) {
    db_execute("
        INSERT IGNORE INTO branch_inventory (branch_id, material_id, stock_quantity)
        SELECT 1, material_id, stock_quantity FROM inventory
    ");
    echo "Migrated existing inventory to Main Branch.\n";
}

// Check if materials has a current_stock column
$checkMaterialCol = db_query("
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'materials' AND COLUMN_NAME = 'current_stock'
");
if (!empty($checkMaterialCol)) {
    db_execute("
        INSERT IGNORE INTO branch_inventory (branch_id, material_id, stock_quantity)
        SELECT 1, material_id, current_stock FROM materials
    ");
    echo "Migrated materials.current_stock to Main Branch.\n";
}

// 4. Modify orders table
$checkOrderCol = db_query("
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'branch_id'
");
if (empty($checkOrderCol)) {
    db_execute("ALTER TABLE orders ADD COLUMN branch_id INT NOT NULL DEFAULT 1");
    db_execute("ALTER TABLE orders ADD CONSTRAINT fk_order_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE");
    echo "orders table updated.\n";
} else {
    echo "orders table already has branch_id.\n";
}

echo "Migration Complete.\n";
