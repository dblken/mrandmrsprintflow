<?php
require_once __DIR__ . '/includes/db.php';

$year = date('Y');
$month = date('n');

$query = "
    SELECT DATE(order_date) as dt, COALESCE(SUM(total_amount), 0) as revenue, COUNT(*) as orders
    FROM (
        SELECT order_date, total_amount
        FROM orders
        WHERE payment_status = 'Paid'
        
        UNION ALL
        
        SELECT created_at as order_date, amount_paid as total_amount
        FROM job_orders
        WHERE payment_status = 'PAID'
    ) combined
    WHERE MONTH(order_date) = $month AND YEAR(order_date) = $year
    GROUP BY DATE(order_date)
    ORDER BY dt
";

print_r(db_query($query));
?>
