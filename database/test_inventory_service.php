<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/InventoryService.php';

// 1. Create a dummy category
db_execute("INSERT IGNORE INTO inv_categories (id, name) VALUES (1, 'Raw Materials')");

// 2. Create a dummy item (Tarpaulin)
db_execute("INSERT IGNORE INTO inv_items (id, category_id, name, unit, roll_length_ft, allow_negative_stock) VALUES (1, 1, 'Tarpaulin 10oz', 'ft', 164.00, 0)");

// Clean out existing transactions for item 1
db_execute("DELETE FROM inventory_transactions WHERE item_id = 1");
db_execute("UPDATE inv_items SET current_stock = 0 WHERE id = 1");

try {
    echo "Testing InventoryService...\n";

    // 1. Opening Balance (e.g. 5 rolls * 164ft = 820ft)
    $t1 = InventoryService::recordTransaction(1, 'opening_balance', 820.00, date('Y-m-d'), 'Migration', 1, 'Initial stock import');
    echo "Recorded Opening Balance. Transaction ID: $t1\n";

    // 2. Job Order Issue (e.g. 50ft used for a banner)
    $t2 = InventoryService::recordTransaction(1, 'issue', 50.00, date('Y-m-d'), 'JobOrder', 1001, 'Banner printing');
    echo "Recorded Issue. Transaction ID: $t2\n";

    // 3. Receive new purchase from supplier (e.g. 2 rolls = 328ft)
    $t3 = InventoryService::recordTransaction(1, 'purchase', 328.00, date('Y-m-d'), 'PurchaseOrder', 5001, 'Supplier restock');
    echo "Recorded Purchase. Transaction ID: $t3\n";
    
    // Total should be: 820 - 50 + 328 = 1098
    $soh = InventoryService::getStockOnHand(1);
    
    // Check Cached value against dynamic calculation
    $cached = db_query("SELECT current_stock FROM inv_items WHERE id = 1")[0]['current_stock'];
    
    echo "Calculated Stock On Hand: {$soh}\n";
    echo "Cached Stock Column: {$cached}\n";
    
    $rollEquiv = InventoryService::getRollEquivalent(1);
    echo "Roll Equivalent: {$rollEquiv} rolls\n";
    
    if ($soh == $cached && $soh == 1098) {
        echo "SUCCESS: Calculations are correct!\n";
    } else {
        echo "ERROR: Calculations mismatch or incorrect total.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
