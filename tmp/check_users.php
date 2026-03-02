<?php
require 'includes/db.php';
$res = db_query('SELECT user_id, email, user_type FROM users WHERE user_type IN ("Admin", "Staff") LIMIT 5');
print_r($res);
?>
