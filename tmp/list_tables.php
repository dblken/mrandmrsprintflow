<?php
require 'includes/db.php';
$res = db_query("SHOW TABLES");
print_r($res);
?>
