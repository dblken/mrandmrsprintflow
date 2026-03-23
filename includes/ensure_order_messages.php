<?php
/**
 * Ensure order_messages table exists with required columns.
 * Include once from chat APIs or migrations.
 */
if (!function_exists('db_query')) return;

$check = db_query("SHOW TABLES LIKE 'order_messages'");
if (empty($check)) return; // Table must exist; run migrate_order_messages_system.php or import schema

global $conn;
$cols = db_query("SHOW COLUMNS FROM order_messages") ?: [];
$has_message_type = $has_image_path = false;
foreach ($cols as $col) {
    if ($col['Field'] === 'message_type') $has_message_type = true;
    if ($col['Field'] === 'image_path') $has_image_path = true;
}
if (!$has_message_type) {
    @$conn->query("ALTER TABLE order_messages ADD COLUMN message_type VARCHAR(20) DEFAULT 'text' AFTER message");
}
if (!$has_image_path) {
    @$conn->query("ALTER TABLE order_messages ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER message_type");
}
@$conn->query("ALTER TABLE order_messages MODIFY COLUMN sender ENUM('Customer','Staff','System') NOT NULL DEFAULT 'Customer'");
@$conn->query("ALTER TABLE order_messages MODIFY COLUMN sender_id INT DEFAULT 0");
