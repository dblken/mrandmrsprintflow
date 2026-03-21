<?php
/**
 * Migration: Add System sender support to order_messages
 * Run once: php database/migrate_order_messages_system.php
 */
require_once __DIR__ . '/../includes/db.php';

// 1. Alter sender enum to include 'System'
$conn->query("ALTER TABLE order_messages MODIFY COLUMN sender ENUM('Customer','Staff','System') NOT NULL DEFAULT 'Customer'");
$conn->query("ALTER TABLE order_messages MODIFY COLUMN sender_id INT DEFAULT 0");

// 2. Add message_type if missing
$cols = $conn->query("SHOW COLUMNS FROM order_messages LIKE 'message_type'");
if ($cols && $cols->num_rows === 0) {
    $conn->query("ALTER TABLE order_messages ADD COLUMN message_type VARCHAR(20) DEFAULT 'text' AFTER message");
}

// 3. Add image_path if missing
$cols = $conn->query("SHOW COLUMNS FROM order_messages LIKE 'image_path'");
if ($cols && $cols->num_rows === 0) {
    $conn->query("ALTER TABLE order_messages ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER message_type");
}

echo "Migration complete: order_messages updated for System messages.\n";
