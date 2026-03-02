<?php
require_once __DIR__ . '/includes/db.php';
$res = db_query("SHOW COLUMNS FROM job_orders");
file_put_contents(__DIR__ . '/schema_output.json', json_encode($res, JSON_PRETTY_PRINT));
echo "Done";
