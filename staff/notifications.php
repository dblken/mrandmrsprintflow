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

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    db_execute("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", 'i', [$staff_id]);
    redirect('/printflow/staff/notifications.php');
}

// Get notifications with customer names for chat header
$notifications = db_query("
    SELECT n.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name 
    FROM notifications n 
    LEFT JOIN customers c ON n.customer_id = c.customer_id 
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC 
    LIMIT 100", 'i', [$staff_id]);

$page_title = 'Notifications - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <link rel="stylesheet" href="/printflow/public/assets/css/chat.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .notif-item { padding: 16px 20px; border-radius: 10px; border: 1px solid #f3f4f6; margin-bottom: 10px; transition: all 0.15s; border-left: 4px solid transparent; }
        .notif-item:hover { border-color: #e5e7eb; }
        .notif-unread { background: #f0f9ff; border-color: #bfdbfe; border-left-color: #53C5E0; }
        .notif-badge { display:inline-block; background:#3b82f6; color:#fff; font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px; margin-bottom:6px; }
        .chat-unread-pill { display:inline-flex; align-items:center; gap:4px; background:#ef4444; color:white; font-size:10px; font-weight:700; padding:2px 8px; border-radius:99px; animation:pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.7} }

        /* Filter Tabs */
        .filter-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; padding-bottom:16px; border-bottom: 1px solid #f3f4f6; }
        .filter-tab {
            display:inline-flex; align-items:center; gap:5px;
            padding: 6px 14px; border-radius: 99px;
            font-size: 12px; font-weight: 600;
            cursor: pointer; border: 1.5px solid #e5e7eb;
            background: white; color: #6b7280;
            transition: all 0.18s; white-space: nowrap;
        }
        .filter-tab:hover { border-color: #6366f1; color: #6366f1; background: #f0f0ff; }
        .filter-tab.active { background: #6366f1; color: white; border-color: #6366f1; box-shadow: 0 2px 8px rgba(99,102,241,0.25); }
        .filter-tab .tab-count {
            background: rgba(255,255,255,0.25); color: inherit;
            font-size: 10px; font-weight: 700;
            padding: 1px 6px; border-radius: 99px; min-width: 18px; text-align:center;
        }
        .filter-tab:not(.active) .tab-count { background: #f3f4f6; color: #9ca3af; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h1 class="page-title" style="margin-bottom: 0;">Notifications</h1>
            <?php 
            $unread_count = db_query("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", 'i', [$staff_id])[0]['count'];
            if ($unread_count > 0): 
            ?>
                <a href="?mark_all_read=1" 
                   class="btn-primary" 
                   style="background: #3b82f6; color: white; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2);"
                   onmouseover="this.style.background='#2563eb'"
                   onmouseout="this.style.background='#3b82f6'">
                    <span>✅</span> MARK ALL READ
                </a>
            <?php endif; ?>
        </header>

        <main>
        <?php if (empty($notifications)): ?>
            <div class="card" style="text-align:center; padding:48px 24px;">
                <div style="font-size:48px; margin-bottom:12px;">🔔</div>
                <p style="color:#6b7280; font-size:14px;">No notifications yet</p>
            </div>
        <?php else: ?>

            <!-- Filter Bar Removed -->


            <?php
            $grouped_notifications = [
                'New' => [],
                'Earlier' => []
            ];
            foreach ($notifications as $n) {
                if ($n['is_read'] == 0) {
                    $grouped_notifications['New'][] = $n;
                } else {
                    $grouped_notifications['Earlier'][] = $n;
                }
            }
            $grouped_notifications = array_filter($grouped_notifications);
            
            foreach ($grouped_notifications as $group => $notifs): ?>
                <div style="margin-top: 24px; margin-bottom: 12px;">
                    <h3 style="font-size: 13px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 8px;"><?php echo htmlspecialchars($group); ?></h3>
                </div>
                <?php foreach ($notifs as $notif):
                // Get unread chat count for this order (staff = receiver 'User')
                $chat_unread = 0;
                if (!empty($notif['data_id']) && $notif['type'] === 'Order') {
                    $chat_unread = get_unread_chat_count($notif['data_id'], 'User');
                }
                
                // Determine redirection URL
                $redirect_url = "#";
                if (!empty($notif['data_id']) && $notif['type'] === 'Order') {
                    $redirect_url = "/printflow/staff/orders.php?order_id=" . $notif['data_id'];
                }
            ?>
                <a href="<?php echo $redirect_url; ?>" class="notif-item <?php echo $notif['is_read'] ? '' : 'notif-unread'; ?>" style="display: block; color: inherit; text-decoration: none;">
                    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap: 15px;">
                        <div style="flex: 1;">
                            <!-- Badge row -->
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:6px;">
                                <?php if (!$notif['is_read']): ?>
                                    <span class="notif-badge">NEW</span>
                                <?php endif; ?>
                                <?php if ($chat_unread > 0): ?>
                                    <span class="chat-unread-pill">
                                        💬 <?php echo $chat_unread; ?> new message<?php echo $chat_unread > 1 ? 's' : ''; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p style="font-weight:600; font-size:15px; color:#111827; line-height: 1.4;"><?php echo htmlspecialchars($notif['message']); ?></p>
                            <p style="font-size:12px; color:#6b7280; margin-top:6px; display: flex; align-items: center; gap: 4px;">
                                <span style="font-size: 14px;">🕒</span> <?php echo format_datetime($notif['created_at']); ?>
                            </p>
                            
                            <?php if (!empty($notif['data_id']) && $notif['type'] === 'Order'): ?>
                                <div style="margin-top: 12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                    <div style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; font-size: 12px; border-radius: 8px; text-decoration: none; background: #6366f1; color: white; font-weight: 600; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                                        <span>📂</span> Open Order
                                    </div>
                                    <button
                                        onclick="event.preventDefault(); event.stopPropagation(); openOrderChat(<?php echo (int)$notif['data_id']; ?>, '<?php echo addslashes($notif['customer_name'] ?? 'Customer'); ?>')"
                                        style="display:inline-flex; align-items:center; gap:5px; background:<?php echo $chat_unread > 0 ? '#ef4444' : '#4f46e5'; ?>; color:white; font-size:12px; font-weight:700; padding:6px 14px; border-radius:8px; border:none; cursor:pointer; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); transition:opacity 0.2s;"
                                        onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                                        💬 <?php echo $chat_unread > 0 ? 'Reply (' . $chat_unread . ')' : 'Message Customer'; ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                            <?php if (!$notif['is_read']): ?>
                                <div onclick="event.preventDefault(); event.stopPropagation(); window.location.href='?mark_read=<?php echo $notif['notification_id']; ?>';" 
                                   style="font-size:11px; color:#3b82f6; font-weight:700; text-transform: uppercase; letter-spacing: 0.05em; text-decoration: none; border: 1px solid #dbeafe; padding: 4px 10px; border-radius: 6px; background: white; transition: all 0.2s; cursor: pointer;"
                                   onmouseover="this.style.background='#eff6ff'; this.style.borderColor='#bfdbfe'"
                                   onmouseout="this.style.background='white'; this.style.borderColor='#dbeafe'">
                                    Mark Read
                                </div>
                            <?php else: ?>
                                <span style="font-size: 11px; color: #9ca3af; font-weight: 600; text-transform: uppercase;">Read</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; endforeach; ?>

            <!-- Shown 0 logic removed as filter is gone -->

        <?php endif; ?>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../includes/order_chat.php'; ?>

</body>
</html>
