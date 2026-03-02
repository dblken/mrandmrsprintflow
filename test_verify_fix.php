<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/TarpaulinService.php';

echo "Testing TarpaulinService::getAvailableRolls...\n";
try {
    // Assuming width 5 exists or just testing the query structure
    $rolls = TarpaulinService::getAvailableRolls(5);
    echo "Success! Found " . count($rolls) . " rolls.\n";
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}

echo "\nTesting JobOrderService::processDeductions (Query Check)...\n";
// We won't run the full deduction but we can check if the queries are valid by manually running one
$sql = "SELECT id FROM inv_rolls WHERE item_id = 1 AND status = 'OPEN' ORDER BY received_at ASC LIMIT 1";
$res = db_query($sql);
if ($res !== false) {
    echo "Query 1 Success!\n";
} else {
    echo "Query 1 FAILED!\n";
}

$sql2 = "SELECT id, remaining_length_ft FROM inv_rolls WHERE item_id = 1 AND status = 'OPEN' ORDER BY received_at ASC";
$res2 = db_query($sql2);
if ($res2 !== false) {
    echo "Query 2 Success!\n";
} else {
    echo "Query 2 FAILED!\n";
}
