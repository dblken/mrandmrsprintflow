<?php
/**
 * API: Verify Job Order Payment Proofs
 * Handles the staff/admin verification logic for uploaded payment proofs.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';
require_once __DIR__ . '/../includes/db.php';

// Staff, Manager, Admin (same as customizations page)
if (!in_array($_SESSION['user_type'] ?? '', ['Admin', 'Staff', 'Manager'], true)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$job_id = (int)($_POST['id'] ?? 0);
$request_order_id = (int)($_POST['order_id'] ?? 0);

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
$resolvedBranchId = (int)($job['branch_id'] ?? 0);
$linkedOrderId = (int)($job['order_id'] ?? 0);
if ($linkedOrderId <= 0 && $request_order_id > 0) {
    $linkedOrderId = $request_order_id;
}
if ($resolvedBranchId <= 0 && $linkedOrderId > 0) {
    $branchRow = db_query("SELECT branch_id FROM orders WHERE order_id = ? LIMIT 1", 'i', [$linkedOrderId]);
    $resolvedBranchId = (int)($branchRow[0]['branch_id'] ?? 0);
}
$viewerBranch = printflow_branch_filter_for_user();
if (get_user_type() !== 'Admin' && $viewerBranch) {
    $jobBranchRow = db_query(
        "SELECT COALESCE(jo.branch_id, o.branch_id) AS branch_id
         FROM job_orders jo
         LEFT JOIN orders o ON o.order_id = jo.order_id
         WHERE jo.id = ? LIMIT 1",
        'i',
        [$job_id]
    );
    $jobBranchId = (int)($jobBranchRow[0]['branch_id'] ?? 0);
    $branchMatches =
        ($jobBranchId > 0 && $jobBranchId === (int)$viewerBranch) ||
        ($resolvedBranchId > 0 && $resolvedBranchId === (int)$viewerBranch) ||
        ($linkedOrderId > 0 && printflow_order_in_branch($linkedOrderId, (int)$viewerBranch));
    if (!$branchMatches) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    if ($jobBranchId <= 0 && $resolvedBranchId > 0) {
        db_execute("UPDATE job_orders SET branch_id = ? WHERE id = ? AND (branch_id IS NULL OR branch_id = 0)", 'ii', [$resolvedBranchId, $job_id]);
    }
}

$user_id = $_SESSION['user_id'];
$user_first = $_SESSION['user_first_name'] ?? '';
$user_last = $_SESSION['user_last_name'] ?? '';
$user_name = trim($user_first . ' ' . $user_last);
if ($user_name === '') $user_name = 'Staff';

// Normalize workflow status so variants like "To Verify" / "to-verify"
// are treated the same as enum-style keys.
$normalize_workflow_status = static function ($s): string {
    $s = strtoupper((string)$s);
    $s = str_replace(['–', '-'], '_', $s);
    $s = preg_replace('/\s+/', '_', $s);
    return trim((string)$s, '_');
};

// Verify Action
if ($action === 'verify_payment') {
    
    $payment_proof_status = strtoupper((string)($job['payment_proof_status'] ?? ''));
    $job_status = $normalize_workflow_status($job['status'] ?? '');
    $submitted_amount = (float)($job['payment_submitted_amount'] ?? 0);
    $has_proof_path = !empty($job['payment_proof_path']) || !empty($job['payment_proof']);
    $already_verified = ($payment_proof_status === 'VERIFIED');
    $already_in_post_verify_stage = in_array($job_status, ['IN_PRODUCTION', 'PROCESSING', 'PRINTING', 'TO_RECEIVE', 'COMPLETED'], true);

    // Idempotency / readiness check:
    // Normally we only verify when payment_proof_status is exactly SUBMITTED.
    // But some rows can be inconsistent (e.g., status=VERIFY_PAY with mismatched proof_status),
    // so if we clearly have proof + amount, treat it as SUBMITTED for this verification.
    if ($payment_proof_status !== 'SUBMITTED') {
        // Already verified — idempotent success (job is in correct state)
        if ($already_verified && $already_in_post_verify_stage) {
            echo json_encode(['success' => true]);
            exit;
        }

        $can_treat_as_submitted =
            $already_verified ||
            (
                in_array($job_status, ['TO_PAY', 'VERIFY_PAY', 'TO_VERIFY', 'PENDING_VERIFICATION', 'DOWNPAYMENT_SUBMITTED'], true) &&
                $submitted_amount > 0 &&
                $has_proof_path
            );

        if (!$can_treat_as_submitted) {
            $msg = "Payment proof is not currently in SUBMITTED state (Current: {$payment_proof_status}, Job: {$job_status}, Amount: {$submitted_amount}, Proof: " . ($has_proof_path ? 'Yes' : 'No') . ").";
            echo json_encode(['success' => false, 'error' => $msg]);
            exit;
        }

        if (!$already_verified) {
            // Normalize proof_status so downstream logic and UI remain consistent.
            db_execute("UPDATE job_orders SET payment_proof_status = 'SUBMITTED' WHERE id = ?", 'i', [$job_id]);
            $payment_proof_status = 'SUBMITTED';
        }
    }
    
    if (!$already_verified && $submitted_amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot verify: Valid submitted amount not found.']);
        exit;
    }
    
    $current_paid = (float)$job['amount_paid'];
    $estimated_total = (float)$job['estimated_total'];
    $required_payment = (float)$job['required_payment'];
    
    // Calculate new amounts
    $new_amount_paid = $already_verified ? $current_paid : ($current_paid + $submitted_amount);
    
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
    
    // Once staff approves a submitted proof, this workflow should leave verification
    // immediately. Keep related rows in sync to avoid the UI "coming back" to To Verify.
    $new_order_status = 'IN_PRODUCTION';
    $should_move_to_production = true;
    
    // Execute update transaction
    try {
        global $conn;
        $conn->begin_transaction();
        $linkedJobRows = [];
        if (!empty($job['order_id'])) {
            $linkedJobRows = db_query(
                "SELECT id FROM job_orders WHERE order_id = ? AND status NOT IN ('COMPLETED', 'CANCELLED') ORDER BY id ASC",
                'i',
                [(int)$job['order_id']]
            ) ?: [];
        }
        if (empty($linkedJobRows)) {
            $linkedJobRows = [['id' => $job_id]];
        }

        if ($should_move_to_production) {
            require_once __DIR__ . '/../includes/JobOrderService.php';
            foreach ($linkedJobRows as $linkedJobRow) {
                $linkedJobId = (int)($linkedJobRow['id'] ?? 0);
                if ($linkedJobId <= 0) continue;
                $linkedJobOrder = JobOrderService::getOrder($linkedJobId);
                $linkedReadiness = strtoupper((string)($linkedJobOrder['readiness'] ?? 'READY'));
                if (in_array($linkedReadiness, ['LOW', 'MISSING'], true)) {
                    $jobCode = printflow_format_job_code($linkedJobId);
                    $message = $linkedReadiness === 'MISSING'
                        ? "Cannot approve payment for JO-{$jobCode}: inventory stock is missing."
                        : "Cannot approve payment for JO-{$jobCode}: inventory stock is not enough.";
                    throw new Exception($message);
                }
            }
        }

        // Update payment fields first
        db_execute("UPDATE job_orders SET 
                    amount_paid = ?, 
                    payment_status = ?, 
                    payment_proof_status = 'VERIFIED',
                    payment_verified_at = NOW(),
                    payment_verified_by = ?
                    WHERE id = ?", 
        'dsii', [$new_amount_paid, $new_payment_status, $user_id, $job_id]);
        
        // Move every linked job for this order into production so stale sibling
        // job rows do not remain in VERIFY_PAY and reappear in the dashboard.
        if ($should_move_to_production) {
            foreach ($linkedJobRows as $linkedJobRow) {
                $linkedJobId = (int)($linkedJobRow['id'] ?? 0);
                if ($linkedJobId <= 0) continue;
                if ($resolvedBranchId > 0) {
                    db_execute("UPDATE job_orders SET branch_id = ? WHERE id = ?", 'ii', [$resolvedBranchId, $linkedJobId]);
                }
                db_execute(
                    "UPDATE job_orders
                     SET payment_proof_status = 'VERIFIED',
                         payment_status = ?,
                         payment_verified_at = NOW(),
                         payment_verified_by = ?
                     WHERE id = ?",
                    'sii',
                    [$new_payment_status, $user_id, $linkedJobId]
                );
                JobOrderService::updateStatus($linkedJobId, 'IN_PRODUCTION');
            }
            if (!empty($job['order_id'])) {
                db_execute(
                    "UPDATE orders
                     SET status = 'Processing', payment_status = ?
                     WHERE order_id = ?",
                    'si',
                    [($new_payment_status === 'PAID' ? 'Paid' : 'Partial'), (int)$job['order_id']]
                );
                db_execute(
                    "UPDATE customizations
                     SET status = 'Processing', updated_at = NOW()
                     WHERE order_id = ? AND status NOT IN ('Completed', 'Cancelled', 'Rejected')",
                    'i',
                    [(int)$job['order_id']]
                );
            }
        }

        // If linked to a store order, sync the store order status
        if ($job['order_id'] && !$should_move_to_production) {
            $store_status = 'Paid – In Process';
            if ($new_order_status === 'APPROVED') $store_status = 'Approved';
            if ($new_order_status === 'TO_RECEIVE') $store_status = 'Ready for Pickup';
            if ($new_order_status === 'COMPLETED') $store_status = 'Completed';
            
            db_execute("UPDATE orders SET status = ?, payment_status = ? WHERE order_id = ?", 
                'ssi', [$store_status, ($new_payment_status === 'PAID' ? 'Paid' : 'Partial'), $job['order_id']]);
        } elseif ($job['order_id'] && $should_move_to_production) {
            db_execute("UPDATE orders SET payment_status = ? WHERE order_id = ?", 
                'si', [($new_payment_status === 'PAID' ? 'Paid' : 'Partial'), $job['order_id']]);
        }
        
        // Log activity (user_id must be a valid staff users.user_id)
        if ($user_id > 0) {
            log_activity($user_id, 'Job payment verified', "Job #{$job_id}: verified ₱{$submitted_amount} ({$user_name})");
        }
        if (!empty($job['customer_id'])) {
            create_notification((int)$job['customer_id'], 'Customer', "Your payment proof for Custom Job #{$job_id} was verified. (₱{$submitted_amount})", 'Job Order', true, true);
        }

        if ($new_order_status !== $job['status'] && $user_id > 0) {
            log_activity($user_id, 'Job status update', "Job #{$job_id} moved to {$new_order_status} after payment verification.");
        }
        if ($new_order_status !== $job['status'] && !empty($job['customer_id'])) {
            create_notification((int)$job['customer_id'], 'Customer', "Custom Job #{$job_id} payment verified and moved into production!", 'Job Order', true, true);
        }

        $conn->commit();
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if (($conn->in_transaction ?? false)) {
            $conn->rollback();
        }
        echo json_encode(['success' => false, 'error' => 'Database error during verification: ' . $e->getMessage()]);
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
    $payment_proof_status = strtoupper((string)($job['payment_proof_status'] ?? ''));
    $job_status = $normalize_workflow_status($job['status'] ?? '');
    if ($payment_proof_status !== 'SUBMITTED' && !in_array($job_status, ['TO_PAY', 'VERIFY_PAY', 'TO_VERIFY', 'PENDING_VERIFICATION', 'DOWNPAYMENT_SUBMITTED'], true)) {
        echo json_encode(['success' => false, 'error' => 'Payment proof is not currently in SUBMITTED state for rejection.']);
        exit;
    }
    
    try {
        db_execute("UPDATE job_orders SET 
                    status = 'REJECTED',
                    payment_proof_status = 'REJECTED',
                    payment_rejection_reason = ?,
                    payment_verified_at = NOW(),
                    payment_verified_by = ?
                    WHERE id = ?", 
        'sii', [$reason, $user_id, $job_id]);

        // If linked to a store order, revert to 'To Pay' so they can submit again
        if ($job['order_id']) {
            db_execute("UPDATE orders SET status = 'Rejected' WHERE order_id = ?", 'i', [$job['order_id']]);
            db_execute(
                "UPDATE customizations
                 SET status = 'Rejected', updated_at = NOW()
                 WHERE order_id = ? AND status NOT IN ('Completed', 'Cancelled', 'Rejected')",
                'i',
                [$job['order_id']]
            );
            db_execute(
                "UPDATE job_orders
                 SET status = 'REJECTED'
                 WHERE order_id = ? AND status NOT IN ('COMPLETED', 'CANCELLED')",
                'i',
                [$job['order_id']]
            );
        }
        
        if ($user_id > 0) {
            log_activity($user_id, 'Job payment rejected', "Job #{$job_id}: rejected by {$user_name}. {$reason}");
        }
        if (!empty($job['customer_id'])) {
            $data_id = (int)($job['order_id'] ?: $job_id);
            create_notification((int)$job['customer_id'], 'Customer', "Your payment proof for Custom Job #{$job_id} was rejected. Please review and re-upload.", 'Payment Issue', true, true, $data_id);
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error during rejection: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
}
