<?php
require_once __DIR__ . '/includes/db.php';
$res = db_query("DESCRIBE inv_rolls");
if (!$res) {
    echo "Table inv_rolls not found or error encountered.\n";
    exit(1);
}
foreach ($res as $row) {
    echo "{$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']} | {$row['Extra']}\n";
}
