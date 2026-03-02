<?php
require_once __DIR__ . '/includes/db.php';
print_r(db_query("DESCRIBE job_orders"));
print_r(db_query("DESCRIBE orders"));
?>
