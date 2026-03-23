<?php
/**
 * API: Verify Job Order Payment Proofs
 * Handles the staff/admin verification logic for uploaded payment proofs.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Only staff or admins can verify
if (!in_array($_SESSION['user_type'], ['Admin', 'Staff'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$job_id = (int)($_POST['id'] ?? 0);

if (!$action || !$job_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid request parameters.']);
    exit;
}

// Fetch the job
$job = db_query("SELECT * FROM job_orders WHERE id = ?", 'i', [$job_id]);
if (empty($job)) {
    echo json_encode(['success' => false, 'error' => 'Job order not found.']);
    exit;
}

$job = $job[0];
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_first_name'];

// Verify Action
if ($action === 'verify_payment') {
    
    // Idempotency check: Ensure we only verify SUBMITTED proofs
    if ($job['payment_proof_status'] !== 'SUBMITTED') {
        echo json_encode(['success' => false, 'error' => 'Payment proof is not currently in SUBMITTED state.']);
        exit;
    }
    
    $submitted_amount = (float)$job['payment_submitted_amount'];
    
    if ($submitted_amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot verify: Valid submitted amount not found.']);
        exit;
    }
    
    $current_paid = (float)$job['amount_paid'];
    $estimated_total = (float)$job['estimated_total'];
    $required_payment = (float)$job['required_payment'];
    
    // Calculate new amounts
    $new_amount_paid = $current_paid + $submitted_amount;
    
    // Determine new payment status based on money
    $new_payment_status = 'UNPAID';
    if ($new_amount_paid > 0 && $new_amount_paid < $estimated_total) {
        $new_payment_status = 'PARTIAL';
    } elseif ($new_amount_paid >= $estimated_total) {
        $new_payment_status = 'PAID';
        // Cap amount_paid to estimated_total just for safety, though it shouldn't exceed
        if ($new_amount_paid > $estimated_total) {
            $new_amount_paid = $estimated_total;
        }
    }
    
    // Determine order status transition
    // If we are waiting for payment (TO_PAY) and they met the required amount, move to IN_PRODUCTION
    $new_order_status = $job['status'];
    if ($new_order_status === 'TO_PAY' && $new_amount_paid >= $required_payment) {
        $new_order_status = 'IN_PRODUCTION';
    }
    
    // Execute update transaction
    try {
        db_execute("UPDATE job_orders SET 
                    amount_paid = ?, 
                    payment_status = ?, 
                    payment_proof_status = 'VERIFIED',
                    payment_verified_at = NOW(),
                    payment_verified_by = ?,
                    status = ?
                    WHERE id = ?", 
        'dsisi', [$new_amount_paid, $new_payment_status, $user_id, $new_order_status, $job_id]);

        // If linked to a store order, sync the store order status
        if ($job['order_id']) {
            $store_status = 'Paid – In Process';
            if ($new_order_status === 'IN_PRODUCTION') $store_status = 'Processing';
            if ($new_order_status === 'TO_RECEIVE') $store_status = 'Ready for Pickup';
            if ($new_order_status === 'COMPLETED') $store_status = 'Completed';
            
            db_execute("UPDATE orders SET status = ?, amount_paid = ?, payment_status = ? WHERE order_id = ?", 
                'sdsi', [$store_status, $new_amount_paid, ($new_payment_status === 'PAID' ? 'Paid' : 'Partial'), $job['order_id']]);
        }
        
        // Log activity
        log_activity('job_orders', $job_id, 'Payment Verified', "Verified payment of ₱{$submitted_amount} by {$user_name}");
        create_notification($job['customer_id'], 'Customer', "Your payment proof for Custom Job #{$job_id} was verified. (₱{$submitted_amount})", 'Job Order', true, true);
        
        if ($new_order_status !== $job['status']) {
            log_activity('job_orders', $job_id, 'Status Update', "Job moved to {$new_order_status} after payment verification.");
            create_notification($job['customer_id'], 'Customer', "Custom Job #{$job_id} is now in production!", 'Job Order', true, true);
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error during verification.']);
    }

} 
// Reject Action
elseif ($action === 'reject_payment') {
    
    $reason = sanitize($_POST['reason'] ?? '');
    
    if (empty($reason)) {
        echo json_encode(['success' => false, 'error' => 'Rejection reason is required.']);
        exit;
    }
    
    // Idempotency check
    if ($job['payment_proof_status'] !== 'SUBMITTED') {
        echo json_encode(['success' => false, 'error' => 'Payment proof is not currently in SUBMITTED state.']);
        exit;
    }
    
    try {
        db_execute("UPDATE job_orders SET 
                    payment_proof_status = 'REJECTED',
                    payment_rejection_reason = ?,
                    payment_verified_at = NOW(),
                    payment_verified_by = ?
                    WHERE id = ?", 
        'sii', [$reason, $user_id, $job_id]);

        // If linked to a store order, revert to 'To Pay' so they can submit again
        if ($job['order_id']) {
            db_execute("UPDATE orders SET status = 'To Pay' WHERE order_id = ?", 'i', [$job['order_id']]);
        }
        
        // Log activity
        log_activity('job_orders', $job_id, 'Payment Rejected', "Payment proof rejected by {$user_name}. Reason: {$reason}");
        
        // Notify customer
        create_notification($job['customer_id'], 'Customer', "Your payment proof for Custom Job #{$job_id} was rejected. Please review and re-upload.", 'Payment Issue', true, true);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error during rejection.']);
    }

} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
}
