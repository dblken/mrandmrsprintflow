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
$existing = [];
$has_message_type = $has_image_path = false;
foreach ($cols as $col) {
    if (!empty($col['Field'])) {
        $existing[$col['Field']] = true;
    }
    if ($col['Field'] === 'message_type') $has_message_type = true;
    if ($col['Field'] === 'image_path') $has_image_path = true;
}
if (!$has_message_type) {
    @$conn->query("ALTER TABLE order_messages ADD COLUMN message_type VARCHAR(20) DEFAULT 'text' AFTER message");
}
if (!$has_image_path) {
    @$conn->query("ALTER TABLE order_messages ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER message_type");
}
if (empty($existing['reply_id'])) {
    @$conn->query("ALTER TABLE order_messages ADD COLUMN reply_id INT DEFAULT NULL AFTER order_id");
}
if (empty($existing['file_type'])) {
    @$conn->query("ALTER TABLE order_messages ADD COLUMN file_type ENUM('text','image','video','voice') DEFAULT 'text' AFTER message_type");
}
if (empty($existing['file_path'])) {
    @$conn->query("ALTER TABLE order_messages ADD COLUMN file_path VARCHAR(255) NULL AFTER file_type");
}
if (empty($existing['file_size'])) {
    @$conn->query("ALTER TABLE order_messages ADD COLUMN file_size INT NULL AFTER file_path");
}
if (empty($existing['file_name'])) {
    @$conn->query("ALTER TABLE order_messages ADD COLUMN file_name VARCHAR(255) NULL AFTER file_size");
}
if (empty($existing['message_file'])) {
    @$conn->query("ALTER TABLE order_messages ADD COLUMN message_file VARCHAR(255) NULL AFTER image_path");
}
if (empty($existing['is_pinned'])) {
    @$conn->query("ALTER TABLE order_messages ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER read_receipt");
}
if (empty($existing['is_forwarded'])) {
    @$conn->query("ALTER TABLE order_messages ADD COLUMN is_forwarded TINYINT(1) NOT NULL DEFAULT 0 AFTER is_pinned");
}
@$conn->query("ALTER TABLE order_messages MODIFY COLUMN sender ENUM('Customer','Staff','System') NOT NULL DEFAULT 'Customer'");
@$conn->query("ALTER TABLE order_messages MODIFY COLUMN sender_id INT DEFAULT 0");
@$conn->query("ALTER TABLE order_messages MODIFY COLUMN file_type VARCHAR(20) DEFAULT 'text'");

@$conn->query("CREATE TABLE IF NOT EXISTS message_reactions (
    reaction_id INT NOT NULL AUTO_INCREMENT,
    message_id INT NOT NULL,
    sender ENUM('Customer','Staff','System') NOT NULL,
    sender_id INT NOT NULL,
    reaction_type VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reaction_id),
    UNIQUE KEY unique_reaction (message_id, sender, sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

@$conn->query("CREATE TABLE IF NOT EXISTS user_status (
    id INT NOT NULL AUTO_INCREMENT,
    user_type ENUM('Customer','Staff') NOT NULL,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    is_typing TINYINT(1) DEFAULT 0,
    last_activity TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_user_order (user_type, user_id, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
