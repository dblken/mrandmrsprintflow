<?php
require_once __DIR__ . '/includes/db.php';
$cols = db_query("DESCRIBE job_orders");
foreach($cols as $c) {
    if (in_array($c['Field'], ['id','order_id','customer_id','payment_proof_path', 'status', 'payment_status'])) {
        print_r($c);
    }
}
?>
