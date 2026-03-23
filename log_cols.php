<?php
require 'includes/db.php';
$out = fopen('cols_fixed.txt', 'w');
function log_table($table, $out) {
    fwrite($out, "--- $table ---\n");
    $res = db_query("DESCRIBE $table");
    foreach ($res as $row) {
        fwrite($out, "  " . $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | " . $row['Key'] . "\n");
    }
}
log_table('job_order_materials', $out);
log_table('job_orders', $out);
log_table('orders', $out);
fclose($out);
