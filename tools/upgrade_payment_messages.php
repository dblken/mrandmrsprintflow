<?php
// One-off script: upgrade legacy "ready for payment" text messages to order_card entries
// Usage: php tools/upgrade_payment_messages.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$rows = db_query("SELECT id, order_id, message, message_type FROM order_messages WHERE message LIKE '%ready for payment%' AND message_type != 'order_card'");
if (empty($rows)) {
    echo "No matching messages found.\n";
    exit(0);
}

$updated = 0;
foreach ($rows as $r) {
    $id = $r['id'];
    $order_id = (int)$r['order_id'];

    // Fetch order details
    $order = db_query("SELECT order_id, total_amount, status FROM orders WHERE order_id = ? LIMIT 1", 'i', [$order_id]);
    $order = $order ? $order[0] : null;
    $amount = $order ? (float)$order['total_amount'] : 0.0;

    // Build action URL to payment
    $base = rtrim(BASE_URL, '/');
    $action_url = $base . '/customer/payment.php?order_id=' . $order_id;

    // Build meta JSON similar to printflow_send_order_update
    $meta = [
        'step' => 'send_to_payment',
        'order_id' => $order_id,
        'product_name' => 'Order',
        'amount' => $amount,
        'origin_actor' => 'staff',
        'sender_type' => 'staff',
        'order_status' => $order['status'] ?? '',
        'payment_status' => '',
        'thumbnail' => '',
        'button_label' => 'Proceed to Payment',
        'action_url' => $action_url,
    ];
    $meta_json = json_encode($meta);

    // Update row
    $res = db_execute("UPDATE order_messages SET message_type = 'order_card', action_url = ?, meta_json = ? WHERE id = ?", 'ssi', [$action_url, $meta_json, $id]);
    if ($res) {
        $updated++;
        echo "Upgraded message id={$id} order_id={$order_id}\n";
    } else {
        echo "Failed to upgrade id={$id}\n";
    }
}

echo "Completed. Total upgraded: {$updated}\n";
