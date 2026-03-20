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
$where = "user_id = ? AND type != 'Message'";
$params = [$admin_id];
$types = 'i';

if ($filter === 'unread') {
    $where .= " AND is_read = 0";
} elseif (in_array($filter, ['Order', 'Stock', 'System'])) {
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
        /* ── Tab Bar ───────────────────────────────── */
        .notif-tab-bar {
            display: flex;
            gap: 0;
            border-bottom: 1px solid #e5e7eb;
            overflow-x: auto;
            margin-bottom: 0;
        }
        .notif-tab-bar::-webkit-scrollbar { display: none; }
        .notif-tab {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 12px 18px;
            font-size: 14px;
            font-weight: 500;
            color: #6b7280;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
            text-decoration: none;
            margin-bottom: -1px;
        }
        .notif-tab:hover { color: #111827; border-bottom-color: #9ca3af; }
        .notif-tab.active { color: #111827; font-weight: 600; border-bottom-color: #111827; }
        .notif-tab .tab-count {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 20px; height: 20px; padding: 0 6px;
            background: #f3f4f6; color: #6b7280;
            border-radius: 20px; font-size: 11px; font-weight: 700;
        }
        .notif-tab.active .tab-count { background: #111827; color: #fff; }
        .notif-tab.has-unread .tab-count { background: #ef4444; color: #fff; }

        /* ── Search ───────────────────────────────── */
        .notif-search-wrap {
            position: relative;
        }
        .notif-search-wrap svg {
            position: absolute; left: 12px; top: 50%;
            transform: translateY(-50%); width: 16px; height: 16px; color: #9ca3af;
        }
        .notif-search-wrap input {
            width: 100%; height: 38px;
            padding: 0 12px 0 36px;
            border: 1px solid #e5e7eb; border-radius: 10px;
            font-size: 13px; outline: none; transition: all 0.2s;
            background: #fff; color: #374151;
        }
        .notif-search-wrap input:focus { border-color: #374151; box-shadow: 0 0 0 3px rgba(17,24,39,0.06); }

        /* ── Notification Row ──────────────────────── */
        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 16px 0;
            border-bottom: 1px solid #f3f4f6;
            transition: background 0.1s;
            cursor: pointer;
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: #fafafa; margin: 0 -20px; padding: 16px 20px; border-radius: 8px; }
        .notif-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #3b82f6; flex-shrink: 0; margin-top: 6px;
        }
        .notif-dot.read { background: transparent; border: 2px solid #e5e7eb; }
        .notif-icon-wrap {
            width: 38px; height: 38px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .notif-icon-wrap.order { background: #dbeafe; color: #1e40af; }
        .notif-icon-wrap.stock { background: #fef3c7; color: #b45309; }
        .notif-icon-wrap.system { background: #f3f4f6; color: #374151; }
        .notif-body { flex: 1; min-width: 0; }
        .notif-msg {
            font-size: 13px; font-weight: 500; color: #111827;
            line-height: 1.5; margin-bottom: 4px;
        }
        .notif-item.read .notif-msg { color: #6b7280; font-weight: 400; }
        .notif-time {
            font-size: 12px; color: #9ca3af; display: flex; align-items: center; gap: 6px;
        }
        .type-pill {
            display: inline-block; padding: 2px 8px; border-radius: 20px;
            font-size: 11px; font-weight: 600;
        }
        .type-pill.order { background: #dbeafe; color: #1e40af; }
        .type-pill.stock { background: #fef3c7; color: #b45309; }
        .type-pill.system { background: #f3f4f6; color: #374151; }
        .notif-actions-wrap {
            display: flex; gap: 6px; flex-shrink: 0; align-items: flex-start; padding-top: 2px;
        }
        .notif-action-btn {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500;
            border: 1px solid #e5e7eb; background: #fff; color: #374151; cursor: pointer; transition: all 0.15s;
        }
        .notif-action-btn:hover { background: #f3f4f6; }
        .notif-action-btn.danger { color: #dc2626; border-color: #fecaca; background: #fff5f5; }
        .notif-action-btn.danger:hover { background: #fee2e2; }

        /* ── Group Label ──────────────────────────── */
        .notif-group-label {
            font-size: 11px; font-weight: 700; color: #9ca3af;
            text-transform: uppercase; letter-spacing: 0.06em;
            padding: 16px 0 8px;
        }

        /* ── Empty State ──────────────────────────── */
        .empty-notif {
            text-align: center; padding: 60px 20px;
        }
        .empty-notif-icon {
            width: 64px; height: 64px; border-radius: 50%;
            background: #f3f4f6; margin: 0 auto 16px;
            display: flex; align-items: center; justify-content: center;
        }
        .empty-notif-title { font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 6px; }
        .empty-notif-text { font-size: 13px; color: #9ca3af; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include defined('MANAGER_PANEL') ? __DIR__ . '/../includes/manager_sidebar.php' : __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <div>
                <h1 class="page-title" style="margin-bottom:4px;">Notifications</h1>
                <p style="font-size:14px;color:#6b7280;"><?php echo $unread_count; ?> unread notification<?php echo $unread_count !== 1 ? 's' : ''; ?></p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <button onclick="refreshNotifications()" class="btn-secondary" style="height:38px;padding:0 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Refresh
                </button>
                <?php if ($unread_count > 0): ?>
                <a href="?action=mark_all_read" class="btn-primary" style="height:38px;padding:0 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Mark All Read
                </a>
                <?php endif; ?>
            </div>
        </header>

        <main>
            <div class="card" style="padding:0;overflow:hidden;">
                <!-- Top Bar: Search + Tabs -->
                <div style="padding:20px 20px 0;">
                    <!-- Search -->
                    <div class="notif-search-wrap" style="margin-bottom:16px;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" id="search-input" placeholder="Search notifications..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <!-- Tab Bar -->
                    <div class="notif-tab-bar">
                        <a href="?filter=all<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="notif-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                            All <span class="tab-count"><?php echo count($notifications); ?></span>
                        </a>
                        <a href="?filter=unread<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="notif-tab <?php echo $filter === 'unread' ? 'active' : ''; ?> <?php echo $unread_count > 0 ? 'has-unread' : ''; ?>">
                            Unread <span class="tab-count"><?php echo $unread_count; ?></span>
                        </a>
                        <a href="?filter=Order<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="notif-tab <?php echo $filter === 'Order' ? 'active' : ''; ?>">
                            Orders
                        </a>
                        <a href="?filter=Stock<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="notif-tab <?php echo $filter === 'Stock' ? 'active' : ''; ?>">
                            Inventory
                        </a>
                        <a href="?filter=System<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="notif-tab <?php echo $filter === 'System' ? 'active' : ''; ?>">
                            System
                        </a>
                    </div>
                </div>

                <!-- Notification List -->
                <div style="padding:0 20px 20px;" id="notifications-container">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-notif">
                            <div class="empty-notif-icon">
                                <svg width="28" height="28" fill="none" stroke="#9ca3af" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                            </div>
                            <div class="empty-notif-title">No notifications</div>
                            <p class="empty-notif-text">
                                <?php if ($filter === 'unread'): ?>You're all caught up! No unread notifications.
                                <?php elseif (!empty($search)): ?>No results for "<?php echo htmlspecialchars($search); ?>"
                                <?php else: ?>You don't have any notifications yet.<?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $grouped = ['New' => [], 'Earlier' => []];
                        foreach ($notifications as $n) {
                            $grouped[$n['is_read'] == 0 ? 'New' : 'Earlier'][] = $n;
                        }
                        $grouped = array_filter($grouped);

                        foreach ($grouped as $group => $notifs): ?>
                            <div class="notif-group-label"><?php echo $group; ?></div>
                            <?php foreach ($notifs as $notif):
                                $type     = strtolower($notif['type']);
                                $is_unread = !$notif['is_read'];
                                $target_url = '#';
                                if ($type === 'order' && !empty($notif['data_id'])) {
                                    $target_url = "order_details.php?id=" . $notif['data_id'];
                                } elseif ($type === 'system' && strpos(strtolower($notif['message']), 'chatbot inquiry') !== false) {
                                    $target_url = "faq_chatbot_management.php?tab=inquiries";
                                }
                                $iconSvg = match($type) {
                                    'order'  => '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>',
                                    'stock'  => '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
                                    default  => '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                                };
                            ?>
                            <div class="notif-item <?php echo $is_unread ? '' : 'read'; ?>" data-id="<?php echo $notif['notification_id']; ?>">
                                <div class="notif-dot <?php echo $is_unread ? '' : 'read'; ?>"></div>
                                <div class="notif-icon-wrap <?php echo $type; ?>"><?php echo $iconSvg; ?></div>
                                <div class="notif-body">
                                    <a href="<?php echo $target_url; ?>" class="notif-msg" style="text-decoration:none;display:block;" onclick="handleNotifClick(event, <?php echo $notif['notification_id']; ?>, '<?php echo $target_url; ?>', <?php echo $is_unread ? 'true' : 'false'; ?>)">
                                        <?php echo htmlspecialchars($notif['message']); ?>
                                    </a>
                                    <div class="notif-time">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <?php echo time_ago($notif['created_at']); ?>
                                        <span class="type-pill <?php echo $type; ?>"><?php echo ucfirst($type); ?></span>
                                    </div>
                                </div>
                                <div class="notif-actions-wrap">
                                    <?php if ($is_unread): ?>
                                    <button onclick="markAsRead(<?php echo $notif['notification_id']; ?>)" class="notif-action-btn">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Read
                                    </button>
                                    <?php endif; ?>
                                    <button onclick="deleteNotification(<?php echo $notif['notification_id']; ?>)" class="notif-action-btn danger">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        Delete
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Auto-refresh every 10 seconds
let autoRefreshInterval;

function startAutoRefresh() {
    autoRefreshInterval = setInterval(checkForNewNotifications, 10000);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

function checkForNewNotifications() {
    const currentFilter = new URLSearchParams(window.location.search).get('filter') || 'all';
    const searchInput = document.getElementById('search-input');
    if (searchInput && !searchInput.value && (currentFilter === 'all' || currentFilter === 'unread')) {
        refreshPage();
    }
}

function refreshPage() {
    // Use AJAX to fetch just the content if possible, but for consistency we'll reload 
    // without resetting scroll if possible. Actually, standard reload is fine if interval is 10s.
    window.location.reload();
}

function handleNotifClick(e, notifId, url, isUnread) {
    if (isUnread) {
        e.preventDefault();
        markAsRead(notifId, url);
    } else if (url && url !== '#') {
        // Just let it navigate
    } else {
        e.preventDefault();
    }
}

function refreshNotifications() {
    window.location.reload();
}

function markAsRead(notifId, redirectUrl = null) {
    fetch(`?action=mark_read&id=${notifId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (redirectUrl && redirectUrl !== '#') {
                    window.location.href = redirectUrl;
                    return;
                }
                const item = document.querySelector(`[data-id="${notifId}"]`);
                if (item) {
                    item.classList.add('read');
                    const dot = item.querySelector('.notif-dot');
                    if (dot) dot.classList.add('read');
                    // Remove the "Read" action button
                    const readBtn = item.querySelector('.notif-action-btn:not(.danger)');
                    if (readBtn) readBtn.remove();
                }
                setTimeout(() => window.location.reload(), 500);
            }
        })
        .catch(error => console.error('Error:', error));
}

function deleteNotification(notifId) {
    if (!confirm('Delete this notification?')) return;
    
    fetch(`?action=delete&id=${notifId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const item = document.querySelector(`[data-id="${notifId}"]`);
                if (item) {
                    item.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(16px)';
                    setTimeout(() => {
                        item.remove();
                        const container = document.getElementById('notifications-container');
                        if (container && container.querySelectorAll('.notif-item').length === 0) {
                            window.location.reload();
                        }
                    }, 250);
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
