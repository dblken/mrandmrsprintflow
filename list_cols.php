<?php
require 'includes/db.php';
$res = db_query("DESCRIBE job_order_materials");
foreach ($res as $row) {
    echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | " . $row['Key'] . "\n";
}
echo "--- job_orders ---\n";
$res = db_query("DESCRIBE job_orders");
foreach ($res as $row) {
    echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | " . $row['Key'] . "\n";
}
echo "--- orders ---\n";
$res = db_query("DESCRIBE orders");
foreach ($res as $row) {
    echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | " . $row['Key'] . "\n";
}
