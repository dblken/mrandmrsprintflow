<?php
/**
 * Chatbot Inquiry API
 * POST: Save a new customer inquiry
 * GET ?id=X: Check if an inquiry has been answered
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Auto-create table if not exists
global $conn;
$conn->query("CREATE TABLE IF NOT EXISTS chatbot_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) DEFAULT 'Guest',
    customer_email VARCHAR(150) DEFAULT NULL,
    question TEXT NOT NULL,
    admin_reply TEXT DEFAULT NULL,
    status ENUM('pending','answered') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    replied_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save new inquiry
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data)) {
        $data = $_POST;
    }
    
    $question = trim($data['question'] ?? '');
    $name = trim($data['customer_name'] ?? 'Guest');
    $email = trim($data['customer_email'] ?? '');
    
    if (empty($question)) {
        echo json_encode(['success' => false, 'error' => 'Question is required']);
        exit;
    }
    
    $sql = "INSERT INTO chatbot_inquiries (customer_name, customer_email, question, created_at) VALUES (?, ?, ?, NOW())";
    $result = db_execute($sql, 'sss', [$name, $email ?: null, $question]);
    
    if ($result) {
        // db_execute for INSERT typically returns the last inserted ID on success
        $inquiry_id = $result; 

        echo json_encode(['success' => true, 'inquiry_id' => $inquiry_id]);
        
        // Notify admin
        create_notification(1, 'User', "New chatbot inquiry from Guest: " . substr($question, 0, 50) . "...", 'System', false, false, $inquiry_id);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save inquiry']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    // Check if inquiry has been answered
    $id = (int)$_GET['id'];
    $inquiry = db_query("SELECT id, question, admin_reply, status, replied_at FROM chatbot_inquiries WHERE id = ?", 'i', [$id]);
    
    if (!empty($inquiry)) {
        echo json_encode([
            'success' => true,
            'data' => $inquiry[0]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Inquiry not found']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
