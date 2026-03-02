<?php
require_once __DIR__ . '/includes/db.php';

$year = date('Y');
$month = date('n');

$query = "
    SELECT DATE(created_at) as dt, SUM(amount_paid) as revenue, COUNT(*) as orders
    FROM job_orders
    WHERE payment_status = 'PAID' AND MONTH(created_at) = $month AND YEAR(created_at) = $year
    GROUP BY DATE(created_at)
";

print_r(db_query($query));
?>
