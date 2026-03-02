<?php
require_once __DIR__ . '/includes/db.php';

function dump_table($tableName) {
    echo "--- Table: $tableName ---\n";
    $res = db_query("DESCRIBE $tableName");
    if ($res) {
        foreach ($res as $row) {
            echo "{$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']} | {$row['Extra']}\n";
        }
    } else {
        echo "Failed to describe $tableName\n";
    }
    echo "\n";
}

header('Content-Type: text/plain');
dump_table('job_orders');
dump_table('inv_rolls');
