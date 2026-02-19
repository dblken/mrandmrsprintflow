<?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);
$user_name = $_SESSION['user_name'] ?? 'Staff';
$user_initial = strtoupper(substr($user_name, 0, 1));
$is_pending = isset($_SESSION['user_status']) && $_SESSION['user_status'] === 'Pending';
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo $is_pending ? 'profile' : 'dashboard'; ?>" class="logo">
            <div class="logo-icon">P</div>
            <span>PrintFlow</span>
        </a>
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
            <a href="dashboard" class="nav-item <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            <a href="orders" class="nav-item <?php echo in_array($current_page, ['orders.php', 'order_details.php']) ? 'active' : ''; ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Orders
            </a>
            <a href="products" class="nav-item <?php echo $current_page === 'products.php' ? 'active' : ''; ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                Products
            </a>
            <a href="reports" class="nav-item <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Reports
            </a>
        </div>
        
        <!-- System -->
        <div class="nav-section">
            <div class="nav-section-title">System</div>
            <a href="notifications" class="nav-item <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                Notifications
            </a>
        </div>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <a href="profile" class="user-profile" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 12px; padding: 8px; border-radius: 6px; transition: background 0.2s;">
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
