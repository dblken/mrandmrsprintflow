<?php
/**
 * Ensures `services` table exists (idempotent).
 */
function ensure_services_table(): void {
    global $conn;
    $sql = "CREATE TABLE IF NOT EXISTS `services` (
        `service_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` varchar(150) NOT NULL,
        `category` varchar(80) DEFAULT NULL,
        `description` text,
        `price` decimal(10,2) NOT NULL,
        `duration` varchar(100) DEFAULT NULL COMMENT 'e.g. 2-3 business days',
        `status` enum('Activated','Deactivated','Archived') NOT NULL DEFAULT 'Activated',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`service_id`),
        UNIQUE KEY `uniq_service_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql)) {
        error_log('ensure_services_table: ' . $conn->error);
    }
}
