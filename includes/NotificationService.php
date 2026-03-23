<?php
/**
 * NotificationService
 * Sends notifications to customers on job order status changes.
 * PrintFlow v2
 */

require_once __DIR__ . '/functions.php';

class NotificationService {

    /** Values allowed by `notifications.type` ENUM (avoid "Data truncated for column 'type'"). */
    private static function normalizeNotificationType(string $type): string {
        $allowed = ['Order', 'Stock', 'System', 'Message', 'Job Order', 'Payment Issue', 'Design', 'Payment', 'Status'];
        return in_array($type, $allowed, true) ? $type : 'System';
    }

    /**
     * Status → human-readable notification messages.
     */
    private static $statusMessages = [
        'APPROVED'     => 'Your customization order has been reviewed and approved.',
        'TO_PAY'       => 'Your order is ready. Please proceed to payment of ₱{amount}. {order_no}',
        'VERIFY_PAY'   => 'Your payment has been received and is under verification.',
        'IN_PRODUCTION'=> 'Payment verified! Your order is now being processed.',
        'TO_RECEIVE'   => 'Great news! Your order is ready for pickup.',
        'COMPLETED'    => 'Thank you! Your order has been marked as completed.',
        'CANCELLED'    => 'Your order has been cancelled. Please contact us for assistance.',
    ];

    /**
     * Send a notification to a customer about a job order status change.
     */
    public static function sendJobOrderNotification(int $customerId, int $jobOrderId, string $newStatus, ?string $overrideMessage = null): bool {
        if (!$customerId) return false;

        $message = $overrideMessage ?? (self::$statusMessages[$newStatus] ?? null);
        if (!$message) return false;

        // Fetch order details for dynamic placeholders and linking
        $order = db_query("SELECT id, order_id, estimated_total FROM job_orders WHERE id = ?", 'i', [$jobOrderId]);
        if (!empty($order)) {
            $o = $order[0];
            $amount = Number_Format((float)($o['estimated_total'] ?? 0), 2);
            $orderNo = "#JO-" . str_pad((int)$o['id'], 5, '0', STR_PAD_LEFT);

            // Use standard order ID for linking if it exists, otherwise use job order ID
            $linkId = $o['order_id'] ?: $o['id'];
            // If it's a standard order ID, we can use type 'Order' for better join in customer/notifications.php
            $type = $o['order_id'] ? 'Order' : 'Job Order';

            if (!empty($o['order_id'])) {
                $orderNo .= " (#ORD-" . str_pad((int)$o['order_id'], 5, '0', STR_PAD_LEFT) . ")";
            }

            $message = str_replace(['{amount}', '{order_no}'], [$amount, $orderNo], $message);
        } else {
            $message = str_replace(['{amount}', '{order_no}'], ['0.00', ''], $message);
            $linkId = $jobOrderId;
            $type = 'Job Order';
        }

        return (bool) create_notification(
            $customerId,
            'Customer',
            $message,
            $type,
            false,
            false,
            $linkId
        );
    }

    /**
     * Send a generic custom notification to a customer.
     */
    public static function send(int $customerId, string $type, string $message, int $dataId = 0): bool {
        if (!$customerId) return false;

        $type = self::normalizeNotificationType($type);

        $result = db_execute(
            "INSERT INTO notifications (customer_id, type, message, data_id, is_read, created_at)
             VALUES (?, ?, ?, ?, 0, NOW())",
            'isis',
            [$customerId, $type, $message, $dataId]
        );

        return (bool) $result;
    }
}
