<?php
require 'includes/functions.php';
$res = db_query('SHOW TABLES');
foreach($res as $r) echo current($r) . PHP_EOL;
?>
