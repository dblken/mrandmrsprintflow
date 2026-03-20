<?php
/**
 * Support chat — customer message / inquiry API
 * POST: Save a new customer inquiry (conversation-based)
 * GET ?id=X: Check if conversation has been answered (returns latest admin reply)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Ensure conversations + messages tables exist
db_execute("CREATE TABLE IF NOT EXISTS chatbot_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT DEFAULT NULL,
    guest_id VARCHAR(64) DEFAULT NULL,
    customer_name VARCHAR(100) DEFAULT 'Guest',
    customer_email VARCHAR(150) DEFAULT NULL,
    last_message_preview VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','answered','expired') DEFAULT 'pending',
    is_archived TINYINT(1) DEFAULT 0,
    last_activity_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_customer (customer_id),
    KEY idx_guest (guest_id),
    KEY idx_status (status),
    KEY idx_activity (last_activity_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

db_execute("CREATE TABLE IF NOT EXISTS chatbot_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_type ENUM('customer','admin') NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_conv (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data)) $data = $_POST;

    $question = trim($data['question'] ?? '');
    $customer_id = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
    $guest_id = trim($data['guest_id'] ?? '');
    $customer_name = trim($data['customer_name'] ?? 'Guest');
    $customer_email = trim($data['customer_email'] ?? '');

    if (empty($question)) {
        echo json_encode(['success' => false, 'error' => 'Question is required']);
        exit;
    }

    // Find or create conversation
    $conversation_id = null;
    if ($customer_id) {
        $existing = db_query(
            "SELECT id FROM chatbot_conversations WHERE customer_id = ? AND (is_archived = 0 OR is_archived IS NULL) ORDER BY last_activity_at DESC LIMIT 1",
            'i', [$customer_id]
        );
        $conversation_id = !empty($existing) ? (int)$existing[0]['id'] : null;
    } elseif ($guest_id) {
        $existing = db_query(
            "SELECT id FROM chatbot_conversations WHERE guest_id = ? AND (is_archived = 0 OR is_archived IS NULL) ORDER BY last_activity_at DESC LIMIT 1",
            's', [$guest_id]
        );
        $conversation_id = !empty($existing) ? (int)$existing[0]['id'] : null;
    }

    if (!$conversation_id) {
        $preview = mb_strlen($question) > 100 ? mb_substr($question, 0, 100) . '...' : $question;
        if (!$guest_id) $guest_id = 'g_' . time() . '_' . bin2hex(random_bytes(4));
        $conversation_id = db_execute(
            "INSERT INTO chatbot_conversations (customer_id, guest_id, customer_name, customer_email, last_message_preview, status, last_activity_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())",
            'issss', [$customer_id, $guest_id, $customer_name ?: 'Guest', $customer_email ?: null, $preview]
        );
        if ($conversation_id === true) {
            global $conn;
            $conversation_id = (int)$conn->insert_id;
        }
    }

    $conv_check = db_query("SELECT id FROM chatbot_conversations WHERE id = ?", 'i', [$conversation_id]);
    if (empty($conv_check)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create conversation']);
        exit;
    }

    db_execute("INSERT INTO chatbot_messages (conversation_id, sender_type, message, created_at) VALUES (?, 'customer', ?, NOW())", 'is', [$conversation_id, $question]);
    $preview = mb_strlen($question) > 100 ? mb_substr($question, 0, 100) . '...' : $question;
    db_execute("UPDATE chatbot_conversations SET last_message_preview = ?, last_activity_at = NOW() WHERE id = ?", 'si', [$preview, $conversation_id]);

    $from_label = $customer_name && $customer_name !== 'Guest' ? $customer_name : 'Guest';
    if (function_exists('create_notification')) {
        create_notification(1, 'User', "New support chat message from {$from_label}: " . substr($question, 0, 50) . "...", 'System', false, false, $conversation_id);
    }

    echo json_encode([
        'success' => true,
        'inquiry_id' => $conversation_id,
        'conversation_id' => $conversation_id,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Try new conversations table first
    $conv = db_query("SELECT id, status FROM chatbot_conversations WHERE id = ?", 'i', [$id]);
    if (!empty($conv)) {
        $messages = db_query("SELECT sender_type, message, created_at FROM chatbot_messages WHERE conversation_id = ? AND sender_type = 'admin' ORDER BY created_at DESC LIMIT 1", 'i', [$id]);
        $admin_reply = !empty($messages) ? $messages[0]['message'] : null;
        $customer_msg = db_query("SELECT message FROM chatbot_messages WHERE conversation_id = ? AND sender_type = 'customer' ORDER BY created_at ASC LIMIT 1", 'i', [$id]);
        $question = !empty($customer_msg) ? $customer_msg[0]['message'] : '';
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $id,
                'question' => $question,
                'admin_reply' => $admin_reply,
                'status' => $conv[0]['status'],
                'replied_at' => !empty($messages) ? $messages[0]['created_at'] : null,
            ]
        ]);
        exit;
    }

    // Fallback: legacy chatbot_inquiries (for old stored IDs)
    if (function_exists('db_query')) {
        $inq = db_query("SELECT id, question, admin_reply, status, replied_at FROM chatbot_inquiries WHERE id = ?", 'i', [$id]);
        if (!empty($inq)) {
            echo json_encode(['success' => true, 'data' => $inq[0]]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Inquiry not found']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
