<?php
/**
 * One-time script: Create verification_codes table + add is_profile_complete to customers
 * Run once via: php database/create_verification_table.php
 */
require_once __DIR__ . '/../includes/db.php';
global $conn;

// 1. Create verification_codes table
$conn->query("CREATE TABLE IF NOT EXISTS `verification_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `identifier` varchar(100) NOT NULL,
  `type` enum('email','phone') NOT NULL,
  `code` varchar(6) NOT NULL,
  `purpose` enum('register','reset') DEFAULT 'register',
  `expires_at` timestamp NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_identifier` (`identifier`, `purpose`, `is_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");
echo "1. verification_codes table: OK\n";

// 2. Add is_profile_complete column (ignore error if already exists)
$conn->query("ALTER TABLE `customers` ADD COLUMN `is_profile_complete` tinyint(1) NOT NULL DEFAULT 1");
if ($conn->errno == 1060) {
    echo "2. is_profile_complete column already exists (OK)\n";
} else {
    echo "2. is_profile_complete column added: OK\n";
    // Existing customers are already complete
    $conn->query("UPDATE `customers` SET `is_profile_complete` = 1");
}

echo "\nMigration complete!\n";
