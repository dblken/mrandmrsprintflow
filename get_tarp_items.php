<?php
require_once __DIR__ . '/includes/db.php';
$res = db_query("SELECT * FROM inv_items WHERE name LIKE '%Tarpaulin%' OR category_id = 2");
foreach ($res as $row) {
    echo "ID: {$row['id']} | Name: {$row['name']} | Category: {$row['category_id']}\n";
}
