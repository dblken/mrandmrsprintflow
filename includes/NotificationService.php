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
        'TO_PAY'       => 'Your order price has been finalized. Please upload proof of payment.',
        'VERIFY_PAY'   => 'Your payment proof has been received and is under review.',
        'IN_PRODUCTION'=> 'Your payment was verified! Your order is now in production.',
        'TO_RECEIVE'   => 'Great news! Your order is ready for pickup.',
        'COMPLETED'    => 'Thank you! Your order has been marked as completed.',
        'CANCELLED'    => 'Your order has been cancelled. Please contact us for assistance.',
    ];

    /**
     * Send a notification to a customer about a job order status change.
     *
     * @param int    $customerId  Customer's user ID.
     * @param int    $jobOrderId  Job order ID for linking.
     * @param string $newStatus   New DB status string.
     * @param string|null $overrideMessage  Optional custom message.
     * @return bool
     */
    public static function sendJobOrderNotification(int $customerId, int $jobOrderId, string $newStatus, ?string $overrideMessage = null): bool {
        if (!$customerId) return false;

        $message = $overrideMessage ?? (self::$statusMessages[$newStatus] ?? null);
        if (!$message) return false; // No message for this status (e.g. PENDING = no notification)

        // Append order reference
        $message = $message . " (Order #JO-" . str_pad($jobOrderId, 5, '0', STR_PAD_LEFT) . ")";

        // Use shared helper + type "Order" (always in ENUM); some DBs omit "Job Order" and truncate.
        return (bool) create_notification(
            $customerId,
            'Customer',
            $message,
            'Order',
            false,
            false,
            $jobOrderId
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
