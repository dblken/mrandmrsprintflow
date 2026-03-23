<?php 
require_once 'includes/functions.php';
$res = db_query('SHOW CREATE TABLE notifications', '', []);
echo $res[0]['Create Table'];
