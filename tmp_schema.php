<?php
require 'includes/functions.php';
function show_table($name) {
    echo "\nTable: $name\n";
    $res = db_query("DESCRIBE $name");
    foreach($res as $r) echo $r['Field'] . ' ' . $r['Type'] . PHP_EOL;
}
show_table('orders');
show_table('order_items');
?>
