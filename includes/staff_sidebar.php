<?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);
$user_name = $_SESSION['user_name'] ?? 'Staff';
$user_initial = strtoupper(substr($user_name, 0, 1));
$is_pending = isset($_SESSION['user_status']) && $_SESSION['user_status'] === 'Pending';
$user_id = get_user_id();
$user_type = get_user_type();
$unread_notif_count = get_unread_notification_count($user_id, $user_type);
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo $is_pending ? 'profile' : 'dashboard'; ?>" class="logo">
            <?php echo get_logo_html('30px'); ?>
            <span><?php echo $shop_name; ?></span>
        </a>
        <button id="global-sidebar-toggle" style="background:none; border:none; color:#9ca3af; cursor:pointer; font-size:16px; padding:4px;" title="Toggle Sidebar">
            <i class="fas fa-chevron-left" id="sidebar-toggle-icon"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <?php if ($is_pending): ?>
        <!-- Pending Staff: Only Profile visible -->
        <div class="nav-section">
            <div class="nav-section-title">Account</div>
            <a href="profile" class="nav-item active">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Complete Profile
            </a>
        </div>
        <div style="padding: 16px 20px;">
            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 14px; font-size: 12px; color: #92400e; line-height: 1.5;">
                <strong style="display:block; margin-bottom:4px;">⏳ Account Pending</strong>
                Complete your profile information. Once approved by an admin, you'll have full access.
            </div>
        </div>
        <?php else: ?>
        <!-- Activated Staff: Full navigation -->
        <!-- Operations -->
        <div class="nav-section">
            <div class="nav-section-title">Operations</div>
            <a href="/printflow/staff/dashboard.php" class="nav-item <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            <a href="/printflow/staff/pos.php" class="nav-item <?php echo $current_page === 'pos.php' ? 'active' : ''; ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                POS (Walk-in)
            </a>
            <a href="/printflow/staff/orders.php" class="nav-item <?php echo in_array($current_page, ['orders.php', 'order_details.php']) ? 'active' : ''; ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Store Orders
            </a>
            <a href="/printflow/staff/customizations.php" class="nav-item <?php echo $current_page === 'customizations.php' ? 'active' : ''; ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                Customizations
            </a>
            <a href="/printflow/staff/products.php" class="nav-item <?php echo $current_page === 'products.php' ? 'active' : ''; ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                Products
            </a>
            <a href="/printflow/staff/reports.php" class="nav-item <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Reports
            </a>
            <a href="/printflow/staff/reviews.php" class="nav-item <?php echo $current_page === 'reviews.php' ? 'active' : ''; ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.14 3.51a1 1 0 00.95.69h3.69c.969 0 1.371 1.24.588 1.81l-2.985 2.168a1 1 0 00-.363 1.118l1.14 3.51c.3.921-.755 1.688-1.539 1.118l-2.985-2.168a1 1 0 00-1.176 0l-2.985 2.168c-.783.57-1.838-.197-1.539-1.118l1.14-3.51a1 1 0 00-.363-1.118L2.98 8.937c-.783-.57-.38-1.81.588-1.81h3.69a1 1 0 00.95-.69l1.14-3.51z"/>
                </svg>
                Reviews
            </a>
        </div>
        
        <!-- System -->
        <div class="nav-section">
            <div class="nav-section-title">System</div>
            <a href="/printflow/staff/notifications.php" class="nav-item <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                Notifications
                <span id="sidebar-notif-badge" class="nav-badge" style="display:<?php echo ($unread_notif_count > 0 ? 'inline-flex' : 'none'); ?>;"><?php echo $unread_notif_count > 99 ? '99+' : $unread_notif_count; ?></span>
            </a>
        </div>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <a href="/printflow/staff/profile.php" class="user-profile" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 12px; padding: 8px; border-radius: 6px; transition: background 0.2s;">
            <div class="user-avatar">
                <?php echo $user_initial; ?>
            </div>
            <div class="user-info">
                <div class="user-name-display"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="user-role">Staff<?php if ($is_pending): ?> <span style="color:#f59e0b;">• Pending</span><?php endif; ?></div>
            </div>
        </a>
        <a href="/printflow/logout/" class="logout-btn">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Log out
        </a>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('global-sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const toggleIcon = document.getElementById('sidebar-toggle-icon');
    
    // Check localStorage for saved state
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        sidebar.classList.add('collapsed');
        if (toggleIcon) {
            toggleIcon.classList.remove('fa-chevron-left');
            toggleIcon.classList.add('fa-chevron-right');
        }
    }
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
            
            if (toggleIcon) {
                if (isCollapsed) {
                    toggleIcon.classList.remove('fa-chevron-left');
                    toggleIcon.classList.add('fa-chevron-right');
                } else {
                    toggleIcon.classList.remove('fa-chevron-right');
                    toggleIcon.classList.add('fa-chevron-left');
                }
            }
        });
    }

    // Notification Polling
    function updateSidebarNotifCount() {
        fetch('/printflow/public/api/notification_count.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('sidebar-notif-badge');
                    if (badge) {
                        badge.innerText = data.count > 99 ? '99+' : data.count;
                        badge.style.display = data.count > 0 ? 'inline-flex' : 'none';
                    }
                }
            })
            .catch(err => console.error('Sidebar notif error:', err));
    }
    // Poll every 10 seconds
    setInterval(updateSidebarNotifCount, 10000);
});
</script>
