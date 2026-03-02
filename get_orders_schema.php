<?php
require_once __DIR__ . '/includes/db.php';
$res = db_query("DESCRIBE orders");
if (!$res) {
    echo "Table orders not found or error encountered.\n";
    exit(1);
}
foreach ($res as $row) {
    echo "{$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']} | {$row['Extra']}\n";
}
