<?php
/**
 * Shared nav header markup. Set $nav_header_class before including.
 * Used by header.php (non-landing) and index.php (landing, inside hero).
 */
$nav_header_class = $nav_header_class ?? 'bg-[#0a2530] backdrop-blur-md shadow-lg sticky top-0 z-50 border-b border-white/5';
require_once __DIR__ . '/shop_config.php';
?>
<header class="<?php echo htmlspecialchars($nav_header_class); ?>" id="main-header">
    <nav class="container mx-auto px-4 py-4">
        <div class="flex items-center justify-between">
            <!-- Logo -->
            <div class="flex items-center space-x-3">
                <a href="<?php echo $url_index; ?>" class="flex items-center space-x-2 group">
                    <?php if (!empty($shop_logo_url)): ?>
                        <img src="<?php echo htmlspecialchars($shop_logo_url); ?>?t=<?php echo time(); ?>"
                             alt="<?php echo $shop_name; ?>"
                             style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;transition:transform 0.3s;flex-shrink:0;"
                             class="group-hover:scale-105">
                        <span class="text-xl font-bold bg-gradient-to-r from-primary-600 to-accent-purple bg-clip-text text-transparent"><?php echo $shop_name; ?></span>
                    <?php else: ?>
                        <div class="relative">
                            <svg class="w-10 h-10 text-primary-600 transform group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                        </div>
                        <span class="text-2xl font-bold bg-gradient-to-r from-primary-600 to-accent-purple bg-clip-text text-transparent"><?php echo $shop_name; ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-8">
                <?php if ($is_logged_in): ?>
                    <?php if (is_admin()): ?>
                        <a href="<?php echo $base_url; ?>/admin/dashboard.php" class="nav-link text-white/80 hover:text-white font-medium transition-colors duration-200 relative group">
                            Dashboard
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/admin/orders_management.php" class="nav-link text-white/80 hover:text-white font-medium transition-colors duration-200 relative group">
                            Orders
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/admin/products_management.php" class="nav-link text-white/80 hover:text-white font-medium transition-colors duration-200 relative group">
                            Products
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/admin/customers_management.php" class="nav-link text-white/80 hover:text-white font-medium transition-colors duration-200 relative group">
                            Customers
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                    <?php elseif (is_staff()): ?>
                        <a href="<?php echo $base_url; ?>/staff/dashboard.php" class="nav-link text-white/80 hover:text-white font-medium transition-colors duration-200 relative group">
                            Dashboard
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/staff/orders.php" class="nav-link text-white/80 hover:text-white font-medium transition-colors duration-200 relative group">
                            Orders
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/staff/products.php" class="nav-link text-white/80 hover:text-white font-medium transition-colors duration-200 relative group">
                            Products
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                    <?php elseif (is_customer()): ?>
                        <a href="<?php echo $base_url; ?>/customer/services.php" class="nav-link font-medium transition-colors duration-200 relative group" style="color:inherit;">
                            Services
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/customer/products.php" class="nav-link font-medium transition-colors duration-200 relative group" style="color:inherit;">
                            Products
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/customer/custom_orders.php" class="nav-link font-medium transition-colors duration-200 relative group" style="color:inherit;">
                            Custom Orders
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/customer/orders.php" class="nav-link font-medium transition-colors duration-200 relative group" style="color:inherit;">
                            My Orders
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
<a href="<?php echo $url_index; ?>" class="nav-link font-medium transition-colors duration-200 relative group" style="color:inherit;">
                            Home
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                    </a>
<a href="<?php echo $base_url; ?>/public/services.php" class="nav-link font-medium transition-colors duration-200 relative group" style="color:inherit;">
                            Services
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                    </a>
<a href="<?php echo $base_url; ?>/public/about.php" class="nav-link font-medium transition-colors duration-200 relative group" style="color:inherit;">
                            About
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                    </a>
<a href="<?php echo $url_products; ?>" class="nav-link font-medium transition-colors duration-200 relative group" style="color:inherit;">
                            Products
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                    </a>
<a href="<?php echo $url_faq; ?>" class="nav-link font-medium transition-colors duration-200 relative group" style="color:inherit;">
                            FAQ
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Right Side Icons -->
            <div class="flex items-center space-x-4">
                <?php if ($is_logged_in): ?>
                    <!-- Cart icon (customer only) -->
                    <?php if (is_customer()): ?>
                    <a href="<?php echo $base_url; ?>/customer/cart.php" title="My Cart" class="nav-link relative text-white hover:text-[#53C5E0] transition-colors duration-200" style="color:white;">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <?php
                        $cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
                        $cart_display = $cart_count > 99 ? '99+' : $cart_count;
                        ?>
                        <span id="cart-count-badge" style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;font-size:0.55rem;font-weight:800;border-radius:9999px;min-width:16px;height:16px;padding:0 3px;display:<?php echo ($cart_count > 0 ? 'flex' : 'none'); ?>;align-items:center;justify-content:center;box-shadow:0 1px 6px rgba(239,68,68,0.55);line-height:1;"><?php echo $cart_display; ?></span>
                    </a>
                    <?php endif; ?>
                    <!-- Notifications -->
                    <a href="<?php echo $base_url; ?>/<?php echo strtolower($user_type); ?>/notifications.php" 
                       title="Notifications"
                       class="nav-link w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 group hover:text-[#53C5E0]" 
                       style="color: white; position: relative;">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: inherit;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <?php 
                        $notif_display = $unread_count > 99 ? '99+' : $unread_count; 
                        ?>
                        <span id="nav-notif-badge" style="position:absolute;top:2px;right:2px;background:#ef4444;color:#fff;font-size:0.55rem;font-weight:800;border-radius:9999px;min-width:16px;height:16px;padding:0 3px;display:<?php echo ($unread_count > 0 ? 'flex' : 'none'); ?>;align-items:center;justify-content:center;box-shadow:0 1px 6px rgba(239,68,68,0.55);line-height:1;"><?php echo $notif_display; ?></span>
                    </a>

                    <!-- User Dropdown -->
                    <div class="relative" x-data="{ open: false, isProfilePage: window.location.pathname.includes('/profile.php') }">
                        <button @click="open = !open" class="flex items-center transition-colors duration-200 group" style="background:none;border:none;cursor:pointer;padding:0;" title="My Account">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 text-white group-hover:text-[#53C5E0]"
                                 :style="(open || isProfilePage) ? 'color: #53C5E0;' : ''">
                                <svg style="width:24px;height:24px;color:inherit;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                        </button>

                        <!-- Dropdown Menu -->
                        <div x-show="open" @click.away="open = false"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-44 rounded-xl py-1 z-50"
                             style="background:#0a2530;border:1px solid rgba(83,197,224,0.2);box-shadow:0 8px 32px rgba(0,0,0,0.4);">
                            <a href="<?php echo $base_url; ?>/<?php echo strtolower($user_type); ?>/profile.php"
                               style="display:flex;align-items:center;padding:0.5rem 1rem;font-size:0.8125rem;color:rgba(255,255,255,0.8);transition:all 0.15s;"
                               onmouseover="this.style.background='rgba(83,197,224,0.1)';this.style.color='#53c5e0'"
                               onmouseout="this.style.background='';this.style.color='rgba(255,255,255,0.8)'">
                                <svg style="width:14px;height:14px;margin-right:8px;opacity:0.6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Profile
                            </a>
                            <a href="<?php echo $base_url; ?>/<?php echo strtolower($user_type); ?>/profile.php#change-password"
                               style="display:flex;align-items:center;padding:0.5rem 1rem;font-size:0.8125rem;color:rgba(255,255,255,0.8);transition:all 0.15s;"
                               onmouseover="this.style.background='rgba(83,197,224,0.1)';this.style.color='#53c5e0'"
                               onmouseout="this.style.background='';this.style.color='rgba(255,255,255,0.8)'">
                                <svg style="width:14px;height:14px;margin-right:8px;opacity:0.6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                                Change Password
                            </a>
                            <div style="height:1px;background:rgba(83,197,224,0.1);margin:0.25rem 0;"></div>
                            <button onclick="document.getElementById('logout-confirm-modal').style.display='flex'" type="button"
                               style="display:flex;align-items:center;width:100%;padding:0.5rem 1rem;font-size:0.8125rem;color:rgba(239,68,68,0.85);transition:all 0.15s;background:transparent;border:none;cursor:pointer;text-align:left;"
                               onmouseover="this.style.background='rgba(239,68,68,0.08)';this.style.color='#ef4444'"
                               onmouseout="this.style.background='transparent';this.style.color='rgba(239,68,68,0.85)'">
                                <svg style="width:14px;height:14px;margin-right:8px;opacity:0.7;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                                Logout
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <button type="button" id="pwa-install-btn" aria-label="Install PrintFlow app"
                        style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.45rem 1rem;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;font-size:0.82rem;font-weight:700;border:none;border-radius:8px;cursor:pointer;transition:all 0.2s;box-shadow:0 2px 10px rgba(34,197,94,0.35);white-space:nowrap;"
                        onmouseover="this.style.boxShadow='0 4px 16px rgba(34,197,94,0.5)';this.style.transform='translateY(-1px)'"
                        onmouseout="this.style.boxShadow='0 2px 10px rgba(34,197,94,0.35)';this.style.transform='translateY(0)'">
                        <svg style="width:15px;height:15px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Install App
                    </button>
                    <a href="#" data-auth-modal="login" class="font-medium transition-colors duration-200" style="color:inherit;">Login</a>
                    <a href="#" data-auth-modal="register" class="btn-gradient-primary px-5 py-2 text-sm">Register</a>
                <?php endif; ?>

            </div>
        </div>
    </nav>
</header>

<?php if ($is_logged_in): ?>
<!-- Logout Confirmation Modal -->
<div id="logout-confirm-modal"
     style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:1rem;"
     onclick="if(event.target===this)this.style.display='none'">
    <!-- Backdrop -->
    <div style="position:absolute;inset:0;background:rgba(0,0,0,0.75);"></div>
    <!-- Card -->
    <div style="position:relative;background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;padding:2.5rem 2rem 2rem;max-width:380px;width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,0.15);text-align:center;">
        <!-- Icon -->
        <div style="width:64px;height:64px;background:rgba(239,68,68,0.08);border:1.5px solid rgba(239,68,68,0.2);border-radius:18px;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;transform:rotate(-5deg);">
            <svg style="width:30px;height:30px;color:#ef4444;" fill="none" stroke="#ef4444" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
        </div>
        <h3 style="font-size:1.25rem;font-weight:800;color:#0f172a;margin:0 0 0.75rem;letter-spacing:-0.02em;">Sign Out</h3>
        <p style="font-size:0.95rem;color:#64748b;margin:0 0 2rem;line-height:1.6;">Are you sure you want to sign out of your account?</p>
        <!-- Buttons -->
        <div style="display:flex;gap:1rem;">
            <button onclick="document.getElementById('logout-confirm-modal').style.display='none'" type="button"
                    style="flex:1;padding:0.75rem 1rem;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;color:#64748b;font-size:0.875rem;font-weight:700;cursor:pointer;transition:all 0.2s;"
                    onmouseover="this.style.background='#f1f5f9';this.style.color='#1e293b'"
                    onmouseout="this.style.background='#f8fafc';this.style.color='#64748b'">
                Cancel
            </button>
            <a href="<?php echo $url_logout; ?>"
               style="flex:1;padding:0.75rem 1rem;border-radius:12px;background:#ef4444;color:#fff;font-size:0.875rem;font-weight:700;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;transition:all 0.2s;box-shadow:0 8px 15px -3px rgba(239,68,68,0.3);"
               onmouseover="this.style.background='#dc2626';this.style.transform='translateY(-1px)'"
               onmouseout="this.style.background='#ef4444';this.style.transform='translateY(0)'">
                Sign Out
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function(){
    var p = window.location.pathname.toLowerCase();
    document.querySelectorAll('a.nav-link').forEach(function(a){
        var h = (a.getAttribute('href') || '').toLowerCase().replace(/\/$/, '');
        // Detect home links by checking if href is the base URL (not a sub-page)
        var isHome = (h === '/printflow' || h === '' || /\/index\.php$/.test(h));
        if (isHome) {
            if (p === '/printflow/' || p === '/printflow' || p.endsWith('/index.php')) a.classList.add('nav-active');
        } else {
            var seg = h.split('/').filter(Boolean).pop() || '';
            seg = seg.replace('.php', '');
            if (seg && p.indexOf('/' + seg) !== -1) a.classList.add('nav-active');
        }
    });
    // Notification Polling
    function updateNotifCount() {
        fetch('/printflow/public/api/notification_count.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('nav-notif-badge');
                    if (badge) {
                        badge.innerText = data.count > 99 ? '99+' : data.count;
                        badge.style.display = data.count > 0 ? 'flex' : 'none';
                    }
                }
            })
            .catch(err => console.error('Notif error:', err));
    }
    // Poll every 10 seconds
    setInterval(updateNotifCount, 10000);
}());
</script>
