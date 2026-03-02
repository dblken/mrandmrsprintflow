<?php
/**
 * Inventory Migration Script (Excel logic to Transactions)
 * 
 * Usage:
 * Place your CSV file in the same directory (e.g. `inventory_import.csv`)
 * Format: Item_Name, Unit, Category_Name, Opening_Stock, Day_1_Usage, Day_2_Usage... Day_31_Usage
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/InventoryService.php';

// ONLY RUN THIS VIA CLI OR SECURE ADMIN ROUTE
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

$csvFile = __DIR__ . '/inventory_import.csv';

if (!file_exists($csvFile)) {
    echo "Please create a file named 'inventory_import.csv' in the database/ directory.\n";
    echo "Format:\nItem_Name,Unit,Category_Name,Opening_Stock,Day_1_Usage,Day_2_Usage,...,Day_31_Usage\n";
    exit;
}

$year = 2024; // Change this to the target year
$month = 1;   // Change this to the target month

echo "Starting Migration for $year-$month...\n";

$handle = fopen($csvFile, "r");
$header = fgetcsv($handle); // Skip header

$successCount = 0;
$txCount = 0;

$adminUserId = 1; // Assuming 1 is a valid admin checking this.

db_execute("SET autocommit=0;");

try {
    while (($row = fgetcsv($handle)) !== FALSE) {
        $itemName = trim($row[0]);
        $unit = trim($row[1]) ?: 'pcs';
        $categoryName = trim($row[2]);
        $openingStock = (float)($row[3] ?? 0);
        
        if (empty($itemName)) continue;

        // 1. Get or Create Category
        $catId = null;
        if (!empty($categoryName)) {
            $cat = db_query("SELECT id FROM inv_categories WHERE name = ?", 's', [$categoryName]);
            if ($cat) {
                $catId = $cat[0]['id'];
            } else {
                global $conn;
                $stmt = $conn->prepare("INSERT INTO inv_categories (name) VALUES (?)");
                $stmt->bind_param("s", $categoryName);
                $stmt->execute();
                $catId = $stmt->insert_id;
                $stmt->close();
            }
        }

        // 2. Get or Create Item
        $item = db_query("SELECT id FROM inv_items WHERE name = ?", 's', [$itemName]);
        if ($item) {
            $itemId = $item[0]['id'];
        } else {
            global $conn;
            $stmt = $conn->prepare("INSERT INTO inv_items (category_id, name, unit) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $catId, $itemName, $unit);
            $stmt->execute();
            $itemId = $stmt->insert_id;
            $stmt->close();
        }

        // 3. Insert Opening Balance on the 1st of the month
        if ($openingStock > 0) {
            $date = sprintf("%04d-%02d-01", $year, $month);
            InventoryService::recordTransaction($itemId, 'opening_balance', $openingStock, $date, 'Migration', null, 'Excel Import', $adminUserId);
            $txCount++;
        }

        // 4. Insert Daily Usage (Columns 4 through 34 assuming 31 days)
        for ($day = 1; $day <= 31; $day++) {
            $colIndex = 3 + $day;
            if (isset($row[$colIndex])) {
                $usage = (float)$row[$colIndex];
                if ($usage > 0) {
                    if (checkdate($month, $day, $year)) {
                        $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                        // Usage is an 'issue', which InventoryService handles as an OUT transaction automatically
                        InventoryService::recordTransaction($itemId, 'issue', $usage, $date, 'Migration', null, 'Excel Daily Usage Import', $adminUserId);
                        $txCount++;
                    }
                }
            }
        }
        $successCount++;
        echo "Migrated Item: $itemName\n";
    }
    
    db_execute("COMMIT;");
    echo "\nMigration Complete!\n";
    echo "Items Processed: $successCount\n";
    echo "Transactions Created: $txCount\n";

} catch (Exception $e) {
    db_execute("ROLLBACK;");
    echo "\nMigration Failed: " . $e->getMessage() . "\n";
} finally {
    db_execute("SET autocommit=1;");
    fclose($handle);
}
