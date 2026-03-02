<?php
require 'includes/db.php';
$res = $conn->query("SELECT * FROM inv_items WHERE name LIKE '%Tarpaulin%'");
echo "Tarpaulin Inv Items:\n";
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
