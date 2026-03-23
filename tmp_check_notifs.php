<?php 
require_once 'includes/functions.php';
$res = db_query('SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10', '', []);
foreach ($res as $r) {
    echo $r['notification_id'] . " | " . $r['customer_id'] . " | " . $r['message'] . " | " . $r['type'] . "\n";
}
