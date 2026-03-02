<?php
require 'includes/db.php';
$res = db_query('SELECT email FROM users WHERE role = "Admin" AND status = "Activated" LIMIT 1');
print_r($res);
?>
