<?php
require 'includes/db.php';
$tables = ['inv_categories', 'inv_items', 'inv_rolls', 'job_order_materials', 'inventory_transactions', 'materials', 'material_categories'];
foreach ($tables as $t) {
    echo "\n--- $t ---\n";
    $res = db_query("DESCRIBE $t");
    print_r($res);
}
?>
