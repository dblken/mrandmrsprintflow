<?php
require_once __DIR__ . '/includes/db.php';
$sql = "SELECT jo.*, c.first_name, c.last_name, c.customer_type, c.transaction_count 
        FROM job_orders jo 
        LEFT JOIN customers c ON jo.customer_id = c.customer_id 
        WHERE 1=1
        ORDER BY jo.priority = 'HIGH' DESC, jo.due_date ASC, jo.created_at DESC";
try {
    $res = db_query($sql);
    echo "Query successful. Count: " . count($res) . "\n";
} catch (Exception $e) {
    echo "Query failed: " . $e->getMessage() . "\n";
}
