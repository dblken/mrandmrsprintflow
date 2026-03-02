<?php
require_once __DIR__ . '/../includes/db.php';

$sql = "CREATE TABLE IF NOT EXISTS job_order_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_order_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (job_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) {
    echo "Table 'job_order_files' created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
?>
