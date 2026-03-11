<?php
/**
 * Handle Order Cancellation
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['confirm_cancel']) || isset($_POST['ajax']))) {
    $is_ajax = isset($_POST['ajax']);

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        die("Invalid CSRF token");
    }

    $order_id = (int)($_POST['order_id'] ?? 0);
    $customer_id = get_user_id();
    $reason = $_POST['reason'] ?? 'Other';
    $details = $_POST['details'] ?? '';
    
    // Combine reason and details if it's "Other"
    if ($reason === 'Other' && !empty($details)) {
        $cancel_reason = "Other: " . $details;
    } else {
        $cancel_reason = $reason;
    }

    // Verify order ownership and status
    $order_result = db_query("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?", 'ii', [$order_id, $customer_id]);

    if (empty($order_result)) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'error' => 'Order not found']);
            exit;
        }
        redirect('orders.php');
    }
    $order = $order_result[0];

    // STRICT RULE: Check if order can be cancelled based on status
    if (!can_customer_cancel_order($order)) {
        $msg = "Order #{$order_id} can no longer be cancelled (it is already ready to pay or in production).";
        if ($is_ajax) {
            echo json_encode(['success' => false, 'error' => $msg]);
            exit;
        }
        $_SESSION['error'] = $msg;
        redirect("order_details.php?id=$order_id");
    }

    // Update order status
    $sql = "UPDATE orders SET status = 'Cancelled', cancelled_by = 'Customer', cancel_reason = ?, cancelled_at = NOW() WHERE order_id = ?";
    $success = db_execute($sql, 'si', [$cancel_reason, $order_id]);

    if ($success) {
        // Increment Customer Cancel Count
        db_execute("UPDATE customers SET cancel_count = cancel_count + 1 WHERE customer_id = ?", 'i', [$customer_id]);
        
        // Check for 7-cancel permanent block
        $new_count_result = db_query("SELECT cancel_count FROM customers WHERE customer_id = ?", 'i', [$customer_id]);
        $new_count = $new_count_result[0]['cancel_count'] ?? 0;
        
        if ($new_count >= 7) {
            db_execute("UPDATE customers SET is_restricted = 1 WHERE customer_id = ?", 'i', [$customer_id]);
            log_activity($customer_id, 'Account Restricted', "Customer reached $new_count cancellations and is now permanently blocked.");
        }

        // Notify Customer
        create_notification($customer_id, 'Customer', "Order #{$order_id} has been cancelled.", 'Order', false, false, $order_id);

        // Notify Staff
        $staff_users = db_query("SELECT user_id FROM users WHERE role = 'Staff' AND status = 'Activated'");
        foreach ($staff_users as $staff) {
            create_notification($staff['user_id'], 'Staff', "Order #{$order_id} was cancelled by the customer. Reason: $cancel_reason", 'Order', false, false, $order_id);
        }

        if ($is_ajax) {
            echo json_encode(['success' => true]);
            exit;
        }
        $_SESSION['success'] = "Order #{$order_id} has been successfully cancelled. The shop staff has been notified.";
    } else {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'error' => 'Failed to cancel order. Please try again.']);
            exit;
        }
        $_SESSION['error'] = "Failed to cancel order. Please try again.";
    }

    redirect("order_details.php?id=$order_id");
} else {
    redirect('orders.php');
}

