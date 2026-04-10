<?php
/**
 * Setup script to create chatbot and FAQ tables
 * Run this once to ensure all tables exist
 */

require_once __DIR__ . '/includes/db.php';

echo "Setting up chatbot tables...\n";

// Create FAQ table
$faq_sql = "CREATE TABLE IF NOT EXISTS faq (
    faq_id INT AUTO_INCREMENT PRIMARY KEY,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    status ENUM('Activated', 'Deactivated') DEFAULT 'Activated',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

db_execute($faq_sql);
echo "FAQ table created/verified.\n";

// Create chatbot conversations table
$conv_sql = "CREATE TABLE IF NOT EXISTS chatbot_conversations (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

db_execute($conv_sql);
echo "Chatbot conversations table created/verified.\n";

// Create chatbot messages table
$msg_sql = "CREATE TABLE IF NOT EXISTS chatbot_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_type ENUM('customer','admin') NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_conv (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

db_execute($msg_sql);
echo "Chatbot messages table created/verified.\n";

// Create settings table if it doesn't exist
$settings_sql = "CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) UNIQUE NOT NULL,
    value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

db_execute($settings_sql);
echo "Settings table created/verified.\n";

// Insert some sample FAQs
$sample_faqs = [
    [
        'question' => 'What are your business hours?',
        'answer' => 'We are open Monday to Friday from 8:00 AM to 6:00 PM, and Saturday from 9:00 AM to 4:00 PM. We are closed on Sundays and public holidays.',
        'status' => 'Activated'
    ],
    [
        'question' => 'What printing services do you offer?',
        'answer' => 'We offer a wide range of printing services including tarpaulins, t-shirts, stickers, business cards, flyers, banners, and custom designs. We also provide large format printing and promotional materials.',
        'status' => 'Activated'
    ],
    [
        'question' => 'How long does it take to complete an order?',
        'answer' => 'Standard orders typically take 2-3 business days. Rush orders can be completed within 24 hours for an additional fee. Complex custom designs may require additional time.',
        'status' => 'Activated'
    ],
    [
        'question' => 'Do you offer pickup and delivery?',
        'answer' => 'We are a pickup-only service. You can collect your completed orders from our shop during business hours. We will notify you when your order is ready for pickup.',
        'status' => 'Activated'
    ],
    [
        'question' => 'What file formats do you accept for custom designs?',
        'answer' => 'We accept various file formats including PDF, AI, PSD, PNG, JPG, and SVG. For best results, please provide high-resolution files (300 DPI or higher).',
        'status' => 'Activated'
    ]
];

// Check if FAQs already exist
$existing_faqs = db_query("SELECT COUNT(*) as count FROM faq");
$faq_count = $existing_faqs[0]['count'] ?? 0;

if ($faq_count == 0) {
    echo "Adding sample FAQs...\n";
    foreach ($sample_faqs as $faq) {
        db_execute(
            "INSERT INTO faq (question, answer, status) VALUES (?, ?, ?)",
            'sss',
            [$faq['question'], $faq['answer'], $faq['status']]
        );
    }
    echo "Sample FAQs added.\n";
} else {
    echo "FAQs already exist ($faq_count found).\n";
}

echo "Chatbot setup complete!\n";
echo "You can now:\n";
echo "1. Visit the admin FAQ management page to add more responses\n";
echo "2. Test the chatbot on customer pages\n";
echo "3. View customer inquiries in the admin support inbox\n";
?>