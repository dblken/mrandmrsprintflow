<?php
require_once __DIR__ . '/../includes/db.php';
global $conn;

echo "=== orders ===\n";
$res = $conn->query('DESCRIBE orders');
while($r = $res->fetch_assoc()) echo $r['Field'].' - '.$r['Type']."\n";

echo "=== order_items ===\n";
$res = $conn->query('DESCRIBE order_items');
while($r = $res->fetch_assoc()) echo $r['Field'].' - '.$r['Type']."\n";

echo "=== customers ===\n";
$res = $conn->query('DESCRIBE customers');
while($r = $res->fetch_assoc()) echo $r['Field'].' - '.$r['Type']."\n";
