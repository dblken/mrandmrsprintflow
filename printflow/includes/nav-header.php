<?php
/**
 * Shared nav header markup. Set $nav_header_class before including.
 * Used by header.php (non-landing) and index.php (landing, inside hero).
 */
$nav_header_class = $nav_header_class ?? 'bg-white/95 backdrop-blur-md shadow-lg sticky top-0 z-50 border-b border-gray-100';
?>
<header class="<?php echo htmlspecialchars($nav_header_class); ?>" id="main-header">
    <nav class="container mx-auto px-4 py-4">
        <div class="flex items-center justify-between">
            <!-- Logo -->
            <div class="flex items-center space-x-3">
                <a href="<?php echo $url_index; ?>" class="flex items-center space-x-2 group">
                    <div class="relative">
                        <svg class="w-10 h-10 text-primary-600 transform group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold bg-gradient-to-r from-primary-600 to-accent-purple bg-clip-text text-transparent">PrintFlow</span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-8">
                <?php if ($is_logged_in): ?>
                    <?php if (is_admin()): ?>
                        <a href="<?php echo $base_url; ?>/admin/dashboard.php" class="nav-link text-gray-700 hover:text-primary-600 font-medium transition-colors duration-200 relative group">
                            Dashboard
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/admin/orders_management.php" class="nav-link text-gray-700 hover:text-primary-600 font-medium transition-colors duration-200 relative group">
                            Orders
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/admin/products_management.php" class="nav-link text-gray-700 hover:text-primary-600 font-medium transition-colors duration-200 relative group">
                            Products
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/admin/customers_management.php" class="nav-link text-gray-700 hover:text-primary-600 font-medium transition-colors duration-200 relative group">
                            Customers
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                    <?php elseif (is_staff()): ?>
                        <a href="<?php echo $base_url; ?>/staff/dashboard.php" class="nav-link text-gray-700 hover:text-primary-600 font-medium transition-colors duration-200 relative group">
                            Dashboard
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/staff/orders.php" class="nav-link text-gray-700 hover:text-primary-600 font-medium transition-colors duration-200 relative group">
                            Orders
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/staff/products.php" class="nav-link text-gray-700 hover:text-primary-600 font-medium transition-colors duration-200 relative group">
                            Products
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                    <?php elseif (is_customer()): ?>
                        <a href="<?php echo $base_url; ?>/customer/dashboard.php" class="nav-link text-gray-700 hover:text-primary-600 font-medium transition-colors duration-200 relative group">
                            Dashboard
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/customer/products.php" class="nav-link text-gray-700 hover:text-primary-600 font-medium transition-colors duration-200 relative group">
                            Products
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/customer/orders.php" class="nav-link text-gray-700 hover:text-primary-600 font-medium transition-colors duration-200 relative group">
                            My Orders
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
<a href="<?php echo $url_index; ?>" class="nav-link text-gray-700 hover:text-primary-600 font-medium transition-colors duration-200 relative group">
                            Home
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                    </a>
<a href="<?php echo $url_products; ?>" class="nav-link text-gray-700 hover:text-primary-600 font-medium transition-colors duration-200 relative group">
                            Products
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                    </a>
<a href="<?php echo $url_faq; ?>" class="nav-link text-gray-700 hover:text-primary-600 font-medium transition-colors duration-200 relative group">
                            FAQ
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Right Side Icons -->
            <div class="flex items-center space-x-4">
                <?php if ($is_logged_in): ?>
                    <!-- Notifications -->
                    <a href="<?php echo $base_url; ?>/<?php echo strtolower($user_type); ?>/notifications.php" class="relative text-gray-700 hover:text-primary-600 transition-colors duration-200">
                        <svg class="w-6 h-6 hover:animate-bounce-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <?php if ($unread_count > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-gradient-to-r from-red-500 to-accent-pink text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-semibold animate-pulse-subtle shadow-glow-sm"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- User Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-2 text-gray-700 hover:text-primary-600 transition-colors duration-200 group">
                            <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-accent-purple rounded-full flex items-center justify-center text-white font-bold shadow-md group-hover:shadow-glow transition-all duration-300">
                                <?php echo $current_user ? strtoupper(substr($current_user['first_name'] ?? 'U', 0, 1)) : 'U'; ?>
                            </div>
                            <span class="hidden md:block font-medium"><?php echo $current_user ? htmlspecialchars($current_user['first_name'] ?? 'User') : 'User'; ?></span>
                            <svg class="w-4 h-4 transform transition-transform duration-200" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div x-show="open" @click.away="open = false"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-3 w-56 bg-white rounded-xl shadow-2xl py-2 z-50 border border-gray-100">
                            <a href="<?php echo $base_url; ?>/<?php echo strtolower($user_type); ?>/profile.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-600 transition-colors duration-200">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Profile Settings
                            </a>
                            <hr class="my-2 border-gray-100">
                            <a href="<?php echo $url_logout; ?>" class="flex items-center px-4 py-3 text-red-600 hover:bg-red-50 transition-colors duration-200">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                                Logout
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="#" data-auth-modal="login" class="text-gray-700 hover:text-primary-600 font-medium transition-colors duration-200">Login</a>
                    <a href="#" data-auth-modal="register" class="btn-gradient-primary px-5 py-2 text-sm">Register</a>
                <?php endif; ?>

            </div>
        </div>
    </nav>
</header>
