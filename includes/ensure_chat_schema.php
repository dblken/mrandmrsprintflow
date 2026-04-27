<?php
/**
 * Unified Chat Schema Enforcement
 * Ensures all columns and tables required for the Chat system are present.
 * Included by list_conversations.php, fetch_messages.php, etc.
 */

if (!function_exists('db_query')) return;

global $conn;

// 1. Ensure order_messages columns
require_once __DIR__ . '/ensure_order_messages.php';

// 2. Ensure order_items columns (customization_data)
$cols = db_query("SHOW COLUMNS FROM order_items") ?: [];
$has_customization = false;
foreach ($cols as $col) {
    if ($col['Field'] === 'customization_data') {
        $has_customization = true;
        break;
    }
}
if (!$has_customization) {
    @$conn->query("ALTER TABLE order_items ADD COLUMN customization_data TEXT NULL");
}

// 3. Ensure customers columns (profile_picture, online_status)
$cols = db_query("SHOW COLUMNS FROM customers") ?: [];
$existing = [];
foreach ($cols as $col) { $existing[$col['Field']] = true; }

if (empty($existing['profile_picture'])) {
    @$conn->query("ALTER TABLE customers ADD COLUMN profile_picture VARCHAR(255) NULL AFTER last_name");
}
if (empty($existing['online_status'])) {
    @$conn->query("ALTER TABLE customers ADD COLUMN online_status ENUM('online', 'offline', 'in-call') DEFAULT 'offline'");
}

// 4. Ensure users columns (profile_picture, online_status)
$cols = db_query("SHOW COLUMNS FROM users") ?: [];
$existing = [];
foreach ($cols as $col) { $existing[$col['Field']] = true; }

if (empty($existing['profile_picture'])) {
    @$conn->query("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL AFTER last_name");
}
if (empty($existing['online_status'])) {
    @$conn->query("ALTER TABLE users ADD COLUMN online_status ENUM('online', 'offline', 'in-call') DEFAULT 'offline'");
}

// 5. Ensure orders columns (is_archived, branch_id)
$cols = db_query("SHOW COLUMNS FROM orders") ?: [];
$existing = [];
foreach ($cols as $col) { $existing[$col['Field']] = true; }

if (empty($existing['is_archived'])) {
    @$conn->query("ALTER TABLE orders ADD COLUMN is_archived TINYINT(1) DEFAULT 0");
}
if (empty($existing['branch_id'])) {
    @$conn->query("ALTER TABLE orders ADD COLUMN branch_id INT DEFAULT NULL");
}
