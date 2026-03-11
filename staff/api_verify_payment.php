<?php
/**
 * API: Verify Payment Proof (Staff)
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Staff']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$action = $_POST['action'] ?? ''; // 'Approve' or 'Reject'

if (!$order_id || !in_array($action, ['Approve', 'Reject'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// Get order details
$order_result = db_query("SELECT * FROM orders WHERE order_id = ?", 'i', [$order_id]);
if (empty($order_result)) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}
$order = $order_result[0];

$staff_id = get_user_id();
$new_status = '';
$payment_status = $order['payment_status'];

if ($action === 'Approve') {
    $new_status = 'Paid – In Process';
    $payment_status = 'Paid';
    
    // Update order
    $sql = "UPDATE orders SET status = ?, payment_status = ? WHERE order_id = ?";
    $success = db_execute($sql, 'ssi', [$new_status, $payment_status, $order_id]);
    
    if ($success) {
        $msg = "✅ Your downpayment for Order #{$order_id} has been approved. Your order is now in process!";
        create_notification($order['customer_id'], 'Customer', $msg, 'Order', false, false, $order_id);
        log_activity($staff_id, 'Payment Approved', "Approved payment for Order #{$order_id}");
    }
} else {
    // Rejected - move back to To Pay or Pending Review
    $new_status = 'To Pay';
    
    // Clear proof so they can re-upload
    $sql = "UPDATE orders SET status = ?, payment_proof = NULL WHERE order_id = ?";
    $success = db_execute($sql, 'si', [$new_status, $order_id]);
    
    if ($success) {
        $msg = "❌ Your payment proof for Order #{$order_id} was rejected. Please upload a valid proof of payment.";
        create_notification($order['customer_id'], 'Customer', $msg, 'Order', false, false, $order_id);
        log_activity($staff_id, 'Payment Rejected', "Rejected payment for Order #{$order_id}");
        
        // Delete the file if it exists to save space (optional, but cleaner)
        if ($order['payment_proof'] && file_exists(__DIR__ . '/../' . $order['payment_proof'])) {
            @unlink(__DIR__ . '/../' . $order['payment_proof']);
        }
    }
}

if ($success) {
    echo json_encode([
        'success' => true,
        'new_status' => $new_status,
        'payment_status' => $payment_status
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database update failed']);
}
