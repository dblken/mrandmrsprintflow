<?php
require 'includes/db.php';
$tables = ['inventory_categories', 'inventory_items', 'inventory_rolls', 'inventory_transactions', 'job_order_materials'];
foreach ($tables as $t) {
    echo "\n--- $t ---\n";
    $res = db_query("DESCRIBE $t");
    print_r($res);
}
?>
