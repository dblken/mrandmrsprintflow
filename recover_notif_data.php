<?php
require_once 'includes/db.php';
$notifications = db_query("SELECT notification_id, message FROM notifications WHERE data_id IS NULL");
$recovered_count = 0;

foreach ($notifications as $n) {
    if (preg_match('/#(\d+)/', $n['message'], $matches)) {
        $order_id = (int)$matches[1];
        db_execute("UPDATE notifications SET data_id = ? WHERE notification_id = ?", 'ii', [$order_id, $n['notification_id']]);
        $recovered_count++;
    }
}

echo "Successfully recovered data_id for $recovered_count notifications.";
?>
