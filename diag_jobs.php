<?php
require_once __DIR__ . '/includes/db.php';
$orders = db_query("SELECT id, order_id, service_type, status, payment_status, amount_paid, created_at FROM job_orders WHERE status = 'COMPLETED'");
print_r($orders);
?>
