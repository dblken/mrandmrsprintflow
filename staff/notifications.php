<?php
/**
 * Staff Notifications Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

$staff_id = get_user_id();

// Mark as read
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?", 'ii', [$notification_id, $staff_id]);
    redirect('/printflow/staff/notifications.php');
}

// Get notifications
$notifications = db_query("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50", 'i', [$staff_id]);

$page_title = 'Notifications - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .notif-item { padding: 16px 20px; border-radius: 10px; border: 1px solid #f3f4f6; margin-bottom: 10px; transition: all 0.15s; }
        .notif-item:hover { border-color: #e5e7eb; }
        .notif-unread { background: #eff6ff; border-color: #bfdbfe; border-left: 4px solid #3b82f6; }
        .notif-badge { display:inline-block; background:#3b82f6; color:#fff; font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px; margin-bottom:6px; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Notifications</h1>
        </header>

        <main>
            <?php if (empty($notifications)): ?>
                <div class="card" style="text-align:center; padding:48px 24px;">
                    <div style="font-size:48px; margin-bottom:12px;">🔔</div>
                    <p style="color:#6b7280; font-size:14px;">No notifications yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notif-item <?php echo $notif['is_read'] ? '' : 'notif-unread'; ?>">
                        <div style="display:flex; align-items:flex-start; justify-content:space-between;">
                            <div>
                                <?php if (!$notif['is_read']): ?>
                                    <span class="notif-badge">NEW</span>
                                <?php endif; ?>
                                <p style="font-weight:500; font-size:14px; color:#1f2937;"><?php echo htmlspecialchars($notif['message']); ?></p>
                                <p style="font-size:12px; color:#9ca3af; margin-top:4px;"><?php echo format_datetime($notif['created_at']); ?></p>
                            </div>
                            <?php if (!$notif['is_read']): ?>
                                <a href="?mark_read=<?php echo $notif['notification_id']; ?>" style="font-size:12px; color:#3b82f6; font-weight:500; text-decoration:none; white-space:nowrap;">Mark Read</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

</body>
</html>
