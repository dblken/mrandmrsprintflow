<?php
/**
 * API: Submit Payment Proof
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

header('Content-Type: application/json');

try {

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$customer_id = get_user_id();

// Validate order
$order_result = db_query("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?", 'ii', [$order_id, $customer_id]);
if (empty($order_result)) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}
$order = $order_result[0];

// Validate amount (at least 50%)
$min_downpayment = (float)$order['total_amount'] * 0.5;
if ($amount < $min_downpayment - 0.01) { // Allowing small float tolerance
    echo json_encode(['success' => false, 'message' => 'Amount must be at least 50% of the total order (' . format_currency($min_downpayment) . ')']);
    exit;
}

// Handle file upload
if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
    ];
    $err_code = $_FILES['payment_proof']['error'] ?? UPLOAD_ERR_NO_FILE;
    $err_msg = $upload_errors[$err_code] ?? 'Please upload a proof of payment image.';
    echo json_encode(['success' => false, 'message' => $err_msg]);
    exit;
}

// Ensure upload directory exists and is writable
$upload_dir = __DIR__ . '/../uploads/payments';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Upload directory could not be created.']);
        exit;
    }
}

$upload = upload_file($_FILES['payment_proof'], ['jpg', 'jpeg', 'png', 'webp'], 'payments');

if (!$upload['success']) {
    echo json_encode(['success' => false, 'message' => 'Upload failed: ' . $upload['error']]);
    exit;
}

$file_path = $upload['file_path'];

// Update order
$sql = "UPDATE orders SET 
        status = 'Downpayment Submitted', 
        downpayment_amount = ?, 
        payment_proof = ?, 
        payment_submitted_at = NOW() 
        WHERE order_id = ?";

$success = db_execute($sql, 'dsi', [$amount, $file_path, $order_id]);

if ($success) {
    // Notify staff
    $staff_msg = "Customer submitted downpayment for Order #{$order_id} (PHP " . number_format($amount, 2) . ")";
    
    // Get all staff users to notify
    $staff_users = db_query("SELECT user_id FROM users WHERE role = 'Staff' AND status = 'Activated'");
    foreach ($staff_users as $staff) {
        create_notification($staff['user_id'], 'Staff', $staff_msg, 'Order', false, false, $order_id);
    }
    
    // Log activity
    log_activity($customer_id, 'Payment Submitted', "Submitted proof for Order #{$order_id}");

    echo json_encode([
        'success' => true, 
        'message' => 'Payment submitted successfully. Waiting for staff verification.',
        'file_path' => $file_path
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database update failed. Please try again.']);
}

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
