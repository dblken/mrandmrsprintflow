<?php
require_once __DIR__ . '/../includes/db.php';
global $conn;

echo "=== order_items table stats ===\n";
$r = db_query("SELECT COUNT(*) as cnt FROM order_items");
echo "Total order_items: " . $r[0]['cnt'] . "\n";

$r = db_query("SELECT MAX(order_id) as mx FROM order_items");
echo "Max order_id in order_items: " . ($r[0]['mx'] ?? 'none') . "\n";

$r = db_query("SELECT MIN(order_id) as mn FROM order_items");
echo "Min order_id in order_items: " . ($r[0]['mn'] ?? 'none') . "\n";

echo "\n=== Last 10 order_items ===\n";
$r = db_query("SELECT oi.order_id, oi.product_id, oi.quantity FROM order_items oi ORDER BY oi.order_id DESC LIMIT 10");
foreach ($r as $row) echo "  order_id={$row['order_id']} product_id={$row['product_id']} qty={$row['quantity']}\n";

echo "\n=== Orders in Feb 2026 ===\n";
$r = db_query("SELECT order_id FROM orders WHERE order_date >= '2026-02-01' ORDER BY order_id");
$feb_ids = array_column($r, 'order_id');
echo "Feb order IDs: " . implode(', ', $feb_ids) . "\n";

echo "\n=== Do any Feb order IDs exist in order_items? ===\n";
if (!empty($feb_ids)) {
    $ids = implode(',', $feb_ids);
    $r = db_query("SELECT order_id, COUNT(*) as cnt FROM order_items WHERE order_id IN ($ids) GROUP BY order_id");
    if (empty($r)) {
        echo "NONE! Feb orders have NO items in order_items table.\n";
        echo "This is the bug — the seed/demo data didn't insert order_items for Feb orders.\n";
    } else {
        foreach ($r as $row) echo "  order_id={$row['order_id']} items={$row['cnt']}\n";
    }
}
