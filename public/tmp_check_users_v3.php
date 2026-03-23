<?php
require_once __DIR__ . '/../includes/db.php';
$rows = db_query("SELECT email, first_name, last_name, role FROM users");
header('Content-Type: text/plain');
foreach ($rows as $row) {
    echo $row['email'] . " | " . $row['first_name'] . " " . $row['last_name'] . " (" . $row['role'] . ")\n";
}
