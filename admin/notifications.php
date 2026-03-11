<?php
/**
 * Admin Notifications Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Manager']);

$admin_id = get_user_id();

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'mark_read' && isset($_GET['id'])) {
        $notification_id = (int)$_GET['id'];
        db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?", 'ii', [$notification_id, $admin_id]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'mark_all_read') {
        db_execute("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", 'i', [$admin_id]);
        redirect('/printflow/admin/notifications.php?success=All notifications marked as read');
    }
    
    if ($action === 'delete' && isset($_GET['id'])) {
        $notification_id = (int)$_GET['id'];
        db_execute("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?", 'ii', [$notification_id, $admin_id]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$where = "user_id = ?";
$params = [$admin_id];
$types = 'i';

if ($filter === 'unread') {
    $where .= " AND is_read = 0";
} elseif (in_array($filter, ['Order', 'Stock', 'System', 'Message'])) {
    $where .= " AND type = ?";
    $params[] = $filter;
    $types .= 's';
}

if (!empty($search)) {
    $where .= " AND message LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}

$notifications = db_query("SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT 100", $types, $params);

// Get unread count
$unread_result = db_query("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", 'i', [$admin_id]);
$unread_count = $unread_result[0]['count'] ?? 0;

// Get counts by type
$type_counts = [
    'all' => count($notifications),
    'unread' => $unread_count,
];

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
    <style>
        .notif-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e5e7eb;
            overflow-x: auto;
            padding-bottom: 0;
        }
        .notif-tab {
            padding: 0.75rem 1.25rem;
            font-size: 0.9375rem;
            font-weight: 600;
            color: #6b7280;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            text-decoration: none;
            margin-bottom: -2px;
        }
        .notif-tab:hover {
            color: #111827;
            border-bottom-color: #d1d5db;
        }
        .notif-tab.active {
            color: #6366f1;
            border-bottom-color: #6366f1;
        }
        .notif-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 0.75rem;
            transition: all 0.2s;
            position: relative;
            border-left: 4px solid transparent;
        }
        .notif-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateY(-1px);
        }
        .notif-card.unread {
            background: #f0f9ff;
            border-left-color: #53C5E0;
        }
        .notif-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.25rem;
        }
        .notif-icon.order { background: #dbeafe; color: #1e40af; }
        .notif-icon.stock { background: #fed7aa; color: #c2410c; }
        .notif-icon.system { background: #e5e7eb; color: #374151; }
        .notif-icon.message { background: #e9d5ff; color: #7e22ce; }
        .notif-header {
            display: flex;
            align-items: start;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }
        .notif-content {
            flex: 1;
        }
        .notif-title {
            font-size: 0.9375rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.25rem;
        }
        .notif-message {
            font-size: 0.875rem;
            color: #6b7280;
            line-height: 1.5;
        }
        .notif-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 0.75rem;
            font-size: 0.8125rem;
            color: #9ca3af;
        }
        .notif-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.625rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .notif-badge.order { background: #dbeafe; color: #1e40af; }
        .notif-badge.stock { background: #fed7aa; color: #c2410c; }
        .notif-badge.system { background: #e5e7eb; color: #374151; }
        .notif-badge.message { background: #e9d5ff; color: #7e22ce; }
        .notif-badge.new { background: #3b82f6; color: white; }
        .notif-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }
        .notif-btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
            font-weight: 600;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .notif-btn-read {
            background: #f3f4f6;
            color: #374151;
        }
        .notif-btn-read:hover {
            background: #e5e7eb;
        }
        .notif-btn-delete {
            background: #fef2f2;
            color: #dc2626;
        }
        .notif-btn-delete:hover {
            background: #fee2e2;
        }
        .search-box {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.9375rem;
            outline: none;
            transition: all 0.2s;
        }
        .search-box input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .search-box svg {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #9ca3af;
        }
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }
        .empty-state-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: #f3f4f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
        }
        .empty-state-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.5rem;
        }
        .empty-state-text {
            font-size: 0.9375rem;
            color: #6b7280;
        }
        .header-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include defined('MANAGER_PANEL') ? __DIR__ . '/../includes/manager_sidebar.php' : __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1 class="page-title" style="margin-bottom: 0.25rem;">Notifications</h1>
                <p style="color: #6b7280; font-size: 0.9375rem;">
                    <?php echo $unread_count; ?> unread notification<?php echo $unread_count !== 1 ? 's' : ''; ?>
                </p>
            </div>
            <div class="header-actions">
                <button onclick="refreshNotifications()" class="btn-secondary" style="padding: 0.625rem 1rem;">
                    <svg style="width: 16px; height: 16px; display: inline-block; margin-right: 0.375rem; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh
                </button>
                <?php if ($unread_count > 0): ?>
                <a href="?action=mark_all_read" class="btn-primary" style="padding: 0.625rem 1rem;">
                    Mark All as Read
                </a>
                <?php endif; ?>
            </div>
        </header>

        <main>
            <!-- Search -->
            <div class="search-box">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text" id="search-input" placeholder="Search notifications..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <!-- Filter Tabs -->
            <div class="notif-tabs">
                <a href="?filter=all" class="notif-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    All (<?php echo count($notifications); ?>)
                </a>
                <a href="?filter=unread" class="notif-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                    Unread (<?php echo $unread_count; ?>)
                </a>
                <a href="?filter=Order" class="notif-tab <?php echo $filter === 'Order' ? 'active' : ''; ?>">
                    📦 Orders
                </a>
                <a href="?filter=Stock" class="notif-tab <?php echo $filter === 'Stock' ? 'active' : ''; ?>">
                    📊 Inventory
                </a>
                <a href="?filter=System" class="notif-tab <?php echo $filter === 'System' ? 'active' : ''; ?>">
                    ⚙️ System
                </a>
                <a href="?filter=Message" class="notif-tab <?php echo $filter === 'Message' ? 'active' : ''; ?>">
                    💬 Messages
                </a>
            </div>

            <!-- Notifications List -->
            <?php if (empty($notifications)): ?>
                <div class="card">
                    <div class="empty-state">
                        <div class="empty-state-icon">🔔</div>
                        <h2 class="empty-state-title">No notifications</h2>
                        <p class="empty-state-text">
                            <?php if ($filter === 'unread'): ?>
                                You're all caught up! No unread notifications.
                            <?php elseif (!empty($search)): ?>
                                No notifications found matching "<?php echo htmlspecialchars($search); ?>"
                            <?php else: ?>
                                You don't have any notifications yet.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div id="notifications-container">
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
                        <div style="margin-top: 1.5rem; margin-bottom: 0.75rem;">
                            <h3 style="font-size: 0.8125rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 0.5rem;"><?php echo htmlspecialchars($group); ?></h3>
                        </div>
                        <?php foreach ($notifs as $notif): 
                        $type = strtolower($notif['type']);
                        $is_unread = !$notif['is_read'];
                        
                        // Get icon and title based on type
                        $icons = [
                            'order' => '📦',
                            'stock' => '📊',
                            'system' => '⚙️',
                            'message' => '💬'
                        ];
                        $icon = $icons[$type] ?? '🔔';
                    ?>
                    <div class="notif-card <?php echo $is_unread ? 'unread' : ''; ?>" data-id="<?php echo $notif['notification_id']; ?>">
                        <div class="notif-header">
                            <div class="notif-icon <?php echo $type; ?>">
                                <?php echo $icon; ?>
                            </div>
                            <div class="notif-content">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div style="flex: 1;">
                                        <div class="notif-title"><?php echo htmlspecialchars($notif['message']); ?></div>
                                    </div>
                                    <?php if ($is_unread): ?>
                                        <span class="notif-badge new">NEW</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="notif-meta">
                                    <span>
                                        <svg style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 0.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <?php echo time_ago($notif['created_at']); ?>
                                    </span>
                                    <span class="notif-badge <?php echo $type; ?>">
                                        <?php echo ucfirst($type); ?>
                                    </span>
                                </div>

                                <div class="notif-actions">
                                    <?php if ($is_unread): ?>
                                    <button onclick="markAsRead(<?php echo $notif['notification_id']; ?>)" class="notif-btn notif-btn-read">
                                        Mark as Read
                                    </button>
                                    <?php endif; ?>
                                    <button onclick="deleteNotification(<?php echo $notif['notification_id']; ?>)" class="notif-btn notif-btn-delete">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
// Auto-refresh every 30 seconds
let autoRefreshInterval;

function startAutoRefresh() {
    autoRefreshInterval = setInterval(checkForNewNotifications, 30000);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

function checkForNewNotifications() {
    // Simple check - just reload if on "all" or "unread" tab
    const currentFilter = new URLSearchParams(window.location.search).get('filter') || 'all';
    if (currentFilter === 'all' || currentFilter === 'unread') {
        window.location.reload();
    }
}

function refreshNotifications() {
    window.location.reload();
}

function markAsRead(notifId) {
    fetch(`?action=mark_read&id=${notifId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const card = document.querySelector(`[data-id="${notifId}"]`);
                if (card) {
                    card.classList.remove('unread');
                    const badge = card.querySelector('.notif-badge.new');
                    if (badge) badge.remove();
                    const readBtn = card.querySelector('.notif-btn-read');
                    if (readBtn) readBtn.remove();
                }
                // Update unread count
                setTimeout(() => window.location.reload(), 500);
            }
        })
        .catch(error => console.error('Error:', error));
}

function deleteNotification(notifId) {
    if (!confirm('Are you sure you want to delete this notification?')) return;
    
    fetch(`?action=delete&id=${notifId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const card = document.querySelector(`[data-id="${notifId}"]`);
                if (card) {
                    card.style.transition = 'all 0.3s ease-out';
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        card.remove();
                        // Check if no notifications left
                        const container = document.getElementById('notifications-container');
                        if (container && container.children.length === 0) {
                            window.location.reload();
                        }
                    }, 300);
                }
            }
        })
        .catch(error => console.error('Error:', error));
}

// Search functionality
let searchTimeout;
document.getElementById('search-input').addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const search = e.target.value;
        const url = new URL(window.location);
        if (search) {
            url.searchParams.set('search', search);
        } else {
            url.searchParams.delete('search');
        }
        window.location = url;
    }, 500);
});

// Start auto-refresh
startAutoRefresh();

// Stop auto-refresh when page is hidden
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoRefresh();
    } else {
        startAutoRefresh();
    }
});
</script>

</body>
</html>
