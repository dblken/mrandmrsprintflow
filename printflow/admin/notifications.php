<?php
/**
 * Admin Notifications Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$admin_id = get_user_id();

// Mark as read
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?", 'ii', [$notification_id, $admin_id]);
    redirect('/printflow/admin/notifications.php');
}

// Get notifications for admin
$notifications = db_query("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100", 'i', [$admin_id]);

$page_title = 'Notifications - Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Notifications</h1>
            <button class="btn-primary" onclick="alert('Feature coming soon')">
                + Unread (0)
            </button>
        </header>

        <main>
            <?php if (empty($notifications)): ?>
                <div class="card text-center py-12">
                    <p class="text-gray-600">No notifications</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($notifications as $notif): ?>
                        <div class="card <?php echo $notif['is_read'] ? '' : 'bg-blue-50 border-l-4 border-blue-500'; ?>">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <?php if (!$notif['is_read']): ?>
                                        <span class="badge bg-blue-500 text-white text-xs mb-2">NEW</span>
                                    <?php endif; ?>
                                    
                                    <p class="font-medium text-gray-900 mb-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <div class="flex items-center gap-4 text-sm text-gray-600">
                                        <span><?php echo format_datetime($notif['created_at']); ?></span>
                                        <span class="badge bg-gray-100 text-gray-700"><?php echo $notif['notification_type']; ?></span>
                                    </div>
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
        </main>
    </div>
</div>

</body>
</html>
