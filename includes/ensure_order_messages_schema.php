<?php
/**
 * Ensures `order_messages.sender` allows 'System' (automated order-thread messages).
 * Older dumps used ENUM('Customer','Staff') only.
 */
function printflow_ensure_order_messages_schema(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    global $conn;
    $check = @$conn->query("SHOW COLUMNS FROM `order_messages` LIKE 'sender'");
    if (!$check || $check->num_rows === 0) {
        if ($check) {
            $check->free();
        }
        return;
    }
    $row = $check->fetch_assoc();
    $check->free();
    $type = $row['Type'] ?? '';
    if (stripos($type, 'System') !== false) {
        return;
    }
    $sql = "ALTER TABLE `order_messages` MODIFY COLUMN `sender` ENUM('Customer','Staff','System') NOT NULL DEFAULT 'Customer'";
    if (!@$conn->query($sql)) {
        error_log('ensure_order_messages_schema: ' . $conn->error);
    }
}
