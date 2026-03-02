<?php
require_once __DIR__ . '/includes/db.php';

$queries = [
    "ALTER TABLE job_orders ADD COLUMN payment_proof_path VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE job_orders ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE job_orders ADD COLUMN payment_reference VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE job_orders ADD COLUMN payment_submitted_amount DECIMAL(12,2) DEFAULT '0.00'",
    "ALTER TABLE job_orders ADD COLUMN payment_proof_status ENUM('NONE', 'SUBMITTED', 'VERIFIED', 'REJECTED') DEFAULT 'NONE'",
    "ALTER TABLE job_orders ADD COLUMN payment_proof_uploaded_at DATETIME NULL",
    "ALTER TABLE job_orders ADD COLUMN payment_verified_at DATETIME NULL",
    "ALTER TABLE job_orders ADD COLUMN payment_verified_by INT NULL",
    "ALTER TABLE job_orders ADD COLUMN payment_rejection_reason TEXT NULL",
    "ALTER TABLE job_orders MODIFY COLUMN payment_status ENUM('UNPAID', 'PENDING_VERIFICATION', 'PARTIAL', 'PAID') NOT NULL DEFAULT 'UNPAID'"
];

foreach ($queries as $q) {
    try {
        db_execute($q);
        echo "SUCCESS: $q\n";
    } catch (Throwable $e) {
        echo "ERROR ($q): " . $e->getMessage() . "\n";
    }
}
?>
