<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/plain');

function describe_table($table) {
    echo "Table: $table\n";
    $rows = db_query("DESCRIBE `$table` ");
    foreach ($rows as $row) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
    echo "\n";
}

describe_table('orders');
describe_table('order_items');
describe_table('order_messages');
describe_table('customers');
describe_table('users');
