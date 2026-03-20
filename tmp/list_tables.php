<?php
require_once __DIR__ . '/../includes/db.php';
$res = db_query("SHOW TABLES");
foreach($res as $r) {
    echo array_values($r)[0] . "\n";
}
