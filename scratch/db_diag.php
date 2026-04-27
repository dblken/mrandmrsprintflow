<?php
require_once __DIR__ . '/../includes/db.php';

function check_table($table) {
    echo "Checking table: $table\n";
    try {
        $count = db_query("SELECT COUNT(*) as cnt FROM `$table`")[0]['cnt'];
        echo "Count: $count\n";
        if ($count > 0) {
            $samples = db_query("SELECT * FROM `$table` LIMIT 5");
            print_r($samples);
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "-------------------\n";
}

header('Content-Type: text/plain');
check_table('users');
check_table('customers');
check_table('orders');
check_table('order_messages');
