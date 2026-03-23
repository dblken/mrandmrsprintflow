<?php 
require_once 'includes/functions.php';
$res = db_query('SELECT DISTINCT type FROM notifications', '', []);
foreach ($res as $r) {
    echo $r['type'] . "\n";
}
