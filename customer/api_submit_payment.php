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
$customer_id = get_user_id();
$payment_choice = $_POST['payment_choice'] ?? 'full';
if (!in_array($payment_choice, ['full', 'half'], true)) {
    $payment_choice = 'full';
}

// Validate order
$order_result = db_query("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?", 'ii', [$order_id, $customer_id]);
if (empty($order_result)) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}
$order = $order_result[0];

// Validate amount and proof
$amount = (float)($_POST['amount'] ?? 0);
$min_downpayment = ($payment_choice === 'half') ? (float)$order['total_amount'] * 0.5 : (float)$order['total_amount'];

if ($amount < $min_downpayment - 0.01) {
    echo json_encode(['success' => false, 'message' => 'Amount must be at least ' . ($payment_choice === 'half' ? '50%' : '100%') . ' of the total (' . format_currency($min_downpayment) . ')']);
    exit;
}

if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please upload a proof of payment.']);
    exit;
}

$upload = upload_file($_FILES['payment_proof'], ['jpg', 'jpeg', 'png', 'webp'], 'payments');
if (!$upload['success']) {
    echo json_encode(['success' => false, 'message' => 'Upload failed: ' . $upload['error']]);
    exit;
}

$file_path = $upload['file_path'];
$payment_type = ($payment_choice === 'half') ? '50_percent' : 'full_payment';

$sql = "UPDATE orders SET 
        status = 'Downpayment Submitted', 
        payment_type = ?,
        downpayment_amount = ?, 
        payment_proof = ?, 
        payment_submitted_at = NOW() 
        WHERE order_id = ?";
$success = db_execute($sql, 'sdsi', [$payment_type, $amount, $file_path, $order_id]);

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
