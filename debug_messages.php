<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/plain');

$rows = db_query("SELECT message_id, order_id, sender, message_type, file_type, image_path, message_file, file_path, message FROM order_messages ORDER BY message_id DESC LIMIT 20");
foreach ($rows as $r) {
    print_r($r);
    echo "-------------------\n";
}
