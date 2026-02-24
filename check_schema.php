<?php
require 'includes/db.php';
$res = $conn->query('DESCRIBE products');
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' | Null: ' . $row['Null'] . ' | Default: ' . ($row['Default'] ?? 'NULL') . "\n";
}
?>
