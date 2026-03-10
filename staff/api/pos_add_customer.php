<?php
/**
 * POS Quick Add Customer API
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !in_array(get_user_type(), ['Admin', 'Staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$first_name = sanitize($input['first_name'] ?? '');
$last_name = sanitize($input['last_name'] ?? '');
$phone = sanitize($input['contact_number'] ?? '');
$email = sanitize($input['email'] ?? '');

if (empty($first_name) || empty($last_name)) {
    echo json_encode(['success' => false, 'message' => 'First and Last name are required.']);
    exit;
}

// Check duplicates
if (!empty($email)) {
    $exists = db_query("SELECT customer_id FROM customers WHERE email = ?", 's', [$email]);
    if (!empty($exists)) {
        echo json_encode(['success' => false, 'message' => 'Email is already registered.']);
        exit;
    }
} else {
    // Generate a dummy email to bypass NOT NULL constraint
    $email = 'walkin_' . time() . '_' . rand(100,999) . '@printflow.local';
}

if (!empty($phone)) {
    $exists = db_query("SELECT customer_id FROM customers WHERE contact_number = ?", 's', [$phone]);
    if (!empty($exists)) {
        echo json_encode(['success' => false, 'message' => 'Phone number is already registered.']);
        exit;
    }
}

// Placeholder password
$password_hash = password_hash('Walkin123!', PASSWORD_BCRYPT);

$sql = "INSERT INTO customers (first_name, middle_name, last_name, email, contact_number, password_hash, is_profile_complete) 
        VALUES (?, '', ?, ?, ?, ?, 1)";

$customer_id = db_execute($sql, 'sssss', [
    $first_name,
    $last_name,
    $email,
    $phone,
    $password_hash
]);

if ($customer_id) {
    echo json_encode([
        'success' => true,
        'customer_id' => $customer_id,
        'message' => 'Customer added successfully'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error while adding customer.']);
}
