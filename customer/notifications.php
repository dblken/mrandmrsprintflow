<?php
/**
 * Customer Notifications Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$customer_id = get_user_id();

// Mark notification as read
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notification_id, $customer_id]);
    redirect('/printflow/customer/notifications.php');
}

// Get all notifications
$notifications = db_query("SELECT * FROM notifications WHERE customer_id = ? ORDER BY created_at DESC LIMIT 50", 'i', [$customer_id]);

$page_title = 'Notifications - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Hero Banner -->
<div style="background:#00151b;position:relative;overflow:hidden;padding:2.75rem 0 3.5rem;">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:700px;height:220px;background:radial-gradient(ellipse at center,rgba(50,161,196,0.18) 0%,rgba(83,197,224,0.06) 50%,transparent 75%);pointer-events:none;z-index:0;"></div>
    <div class="container mx-auto px-4" style="max-width:900px;position:relative;z-index:1;text-align:center;">
        <p style="font-size:0.7rem;font-weight:700;color:rgba(83,197,224,0.8);text-transform:uppercase;letter-spacing:.12em;margin:0 0 .6rem;">Updates</p>
        <h1 style="font-size:clamp(1.75rem,3.5vw,2.75rem);font-weight:800;color:#fff;letter-spacing:-0.03em;margin:0 0 .75rem;line-height:1.1;">Notifications</h1>
        <p style="font-size:0.9rem;color:rgba(255,255,255,0.45);max-width:420px;margin:0 auto;line-height:1.65;">Stay informed with the latest updates on your orders and account activity.</p>
    </div>
</div>

<div class="min-h-screen" style="background:#f5f9fa;padding-top:2.5rem;padding-bottom:3rem;">
    <div class="container mx-auto px-4" style="max-width:900px;">

        <?php if (empty($notifications)): ?>
            <div class="card text-center py-12">
                <p class="text-gray-600">No notifications yet</p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($notifications as $notif): ?>
                    <div class="card <?php echo $notif['is_read'] ? 'bg-white' : 'bg-blue-50 border-l-4 border-blue-500'; ?>">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <?php if (!$notif['is_read']): ?>
                                    <span class="badge bg-blue-500 text-white text-xs mb-2">NEW</span>
                                <?php endif; ?>
                                <p class="font-medium text-gray-900 mb-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                                <p class="text-sm text-gray-600"><?php echo format_datetime($notif['created_at']); ?></p>
                            </div>
                            <?php if (!$notif['is_read']): ?>
                                <a href="?mark_read=<?php echo $notif['notification_id']; ?>" class="text-sm text-blue-600 hover:text-blue-700 ml-4">
                                    Mark as Read
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
