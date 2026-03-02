<?php
require_once __DIR__ . '/../includes/functions.php';

echo "--- Items ---\n";
$items = db_query("SELECT id, name, unit, current_stock FROM inv_items");
print_r($items);

echo "\n--- Transactions ---\n";
$txs = db_query("SELECT id, item_id, transaction_type, quantity, transaction_date FROM inventory_transactions");
print_r($txs);
