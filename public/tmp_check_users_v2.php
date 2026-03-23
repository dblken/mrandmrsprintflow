<?php
require_once __DIR__ . '/../includes/db.php';
$rows = db_query("SELECT email, role FROM users");
header('Content-Type: text/plain');
foreach ($rows as $row) {
    echo $row['email'] . " (" . $row['role'] . ")\n";
}
