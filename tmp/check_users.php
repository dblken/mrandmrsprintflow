<?php
require_once __DIR__ . '/../includes/db.php';
$res = db_query("SELECT first_name, last_name, role FROM users");
print_r($res);
