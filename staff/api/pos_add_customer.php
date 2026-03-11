<?php
/**
 * API: Add Walk-in Customer for POS
 * Path: staff/api/pos_add_customer.php
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Require staff or admin role
if (!has_role(['Admin', 'Staff'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

header('Content-Type: application/json');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || empty($data['first_name']) || empty($data['last_name'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data. First and Last name are required.']);
    exit;
}

$first_name = sanitize($data['first_name']);
$last_name = sanitize($data['last_name']);
$email = !empty($data['email']) ? sanitize($data['email']) : null;
$contact = !empty($data['contact_number']) ? sanitize($data['contact_number']) : null;

try {
    // Check if email already exists if provided
    if ($email) {
        $exists = db_query("SELECT customer_id FROM customers WHERE email = ?", 's', [$email]);
        if ($exists) {
            echo json_encode(['success' => false, 'message' => 'A customer with this email already exists.']);
            exit;
        }
    }

    $result = db_execute(
        "INSERT INTO customers (first_name, last_name, email, contact_number, status, created_at) VALUES (?, ?, ?, ?, 'Activated', NOW())",
        'ssss',
        [$first_name, $last_name, $email, $contact]
    );

    if ($result) {
        global $conn;
        $customer_id = $conn->insert_id;
        echo json_encode(['success' => true, 'customer_id' => $customer_id, 'message' => 'Customer added successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add customer.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
