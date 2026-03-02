<?php
/**
 * Header Component
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$current_user = get_logged_in_user();
$user_type = get_user_type();
$is_logged_in = is_logged_in();
$unread_count = $is_logged_in ? get_unread_notification_count(get_user_id(), $user_type) : 0;

require_once __DIR__ . '/shop_config.php';

// Determine base URL and asset path (works for /printflow/ and /printflow/public/)
$base_url = '/printflow';
$script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$script_dir = dirname($script_name);
// Is this script running from within the public directory?
$is_public = (strpos($script_name, '/public/') !== false);
// Asset base: if we are in public, use current dir, else point to public
// normalize $asset_base to ensure valid URL
$asset_base = '/printflow/public';

// Timestamp for cache busting
$ver = time();
$url_index    = $base_url . '/';
$url_products = $base_url . '/products/';
$url_faq      = $base_url . '/faq/';
$url_login    = $base_url . '/?auth_modal=login';
$url_register = $base_url . '/?auth_modal=register';
$url_logout   = $base_url . '/logout/';
$url_forgot_password = $base_url . '/forgot-password/';
$url_reset_password  = $base_url . '/reset-password/';
$url_google_auth    = $base_url . '/google-auth/';
?>
<!DOCTYPE html>
<html lang="en"<?php echo !empty($use_landing_css) ? ' class="lp-page"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="PrintFlow - Your trusted printing shop for tarpaulins, t-shirts, stickers, and more">
    <meta name="theme-color" content="#4F46E5">
    <title><?php echo $page_title ?? 'PrintFlow - Printing Shop'; ?></title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="<?php echo $base_url; ?>/public/manifest.json">
    
    <!-- Favicon -->
    <?php if (!empty($shop_logo_url)): ?>
        <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($shop_logo_url); ?>?t=<?php echo time(); ?>">
        <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($shop_logo_url); ?>?t=<?php echo time(); ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" href="<?php echo $asset_base; ?>/assets/images/favicon.png">
        <link rel="apple-touch-icon" href="<?php echo $asset_base; ?>/assets/images/icon-192.png">
    <?php endif; ?>
    
    <!-- Tailwind CSS - path works from both /printflow/ and /printflow/public/ -->
    <link rel="stylesheet" href="<?php echo $asset_base; ?>/assets/css/output.css?v=<?php echo $ver; ?>">
    <?php if (!empty($use_landing_css)): ?>
    <link rel="stylesheet" href="<?php echo $asset_base; ?>/assets/css/landing.css?v=<?php echo $ver; ?>">
    <?php endif; ?>
    <?php if (!empty($use_customer_css)): ?>
    <link rel="stylesheet" href="<?php echo $asset_base; ?>/assets/css/customer-theme.css?v=<?php echo $ver; ?>">
    <?php endif; ?>
    
    <!-- Critical: base link/layout so page is never unstyled -->
    <style>
        a { color: inherit; text-decoration: none; }
        a:hover { text-decoration: none; }
        body { margin: 0; background: #f9fafb; color: #111827; font-family: Inter, system-ui, sans-serif; }
        /* Only style header white on non-landing pages */
        body:not(.lp-page) #main-header { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 50; }
        body:not(.lp-page) #main-header nav > div { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; }
        body:not(.lp-page) #main-header nav > div > div:last-child { display: flex; align-items: center; gap: 1rem; }
        body:not(.lp-page) #main-header a { color: #374151; font-weight: 500; }
        body:not(.lp-page) #main-header a:hover { color: #4F46E5; }
        body:not(.lp-page) #main-header a.nav-link { color: #374151; }
        body:not(.lp-page) #main-header a.nav-link:hover { color: #4F46E5; }
        body:not(.lp-page) #main-header .text-2xl.font-bold { color: #4F46E5; }
        body:not(.lp-page) #main-header .btn-gradient-primary { background: linear-gradient(to right, #4F46E5, #A855F7); color: #fff !important; padding: 0.5rem 1.25rem; border-radius: 0.5rem; font-weight: 500; }
        /* Active nav link — mirrors hover state (non-hero pages) */
        a.nav-link.nav-active { color: #2a82a3 !important; }
        a.nav-link.nav-active > span:last-child { width: 100% !important; }
        /* Dark hero nav: force white text overriding Tailwind text-gray-700 */
        html.lp-page #main-header.lp-hero-nav a,
        html.lp-page #main-header.lp-hero-nav a.nav-link { color: rgba(255,255,255,0.85) !important; }
        html.lp-page #main-header.lp-hero-nav a.nav-link:hover { color: #53C5E0 !important; }
        html.lp-page #main-header.lp-hero-nav a.nav-link.nav-active { color: #53C5E0 !important; }
        html.lp-page #main-header.lp-hero-nav a.nav-link.nav-active > span:last-child { width: 100% !important; }
        .pwa-install-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: #374151; background: transparent; border: 1px solid #d1d5db; border-radius: 0.5rem; cursor: pointer; transition: all 0.2s; }
        .pwa-install-btn:hover { color: #4F46E5; border-color: #4F46E5; background: rgba(79,70,229,0.05); }
        .pwa-install-btn.hidden { display: none !important; }
        /* Landing-page nav needs flex layout too */
        #main-header nav > div { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; }
        #main-header nav > div > div:last-child { display: flex; align-items: center; gap: 1rem; }
    </style>
</head>
<body class="bg-gray-50<?php echo !empty($use_landing_css) ? ' lp-page' : ''; ?><?php echo !empty($use_customer_css) ? ' customer-theme' : ''; ?>">
    <!-- Skip to main content (accessibility) - hidden until focused -->
    <a href="#main-content" style="position:absolute;left:-9999px;z-index:9999;padding:0.5rem 1rem;background:#4F46E5;color:#fff;font-weight:500;" id="skip-link">Skip to main content</a>
    <script>document.getElementById('skip-link').addEventListener('focus',function(){ this.style.left='0'; }); document.getElementById('skip-link').addEventListener('blur',function(){ this.style.left='-9999px'; });</script>

    <?php if (empty($use_landing_css)): ?>
    <?php $nav_header_class = 'bg-white/95 backdrop-blur-md shadow-lg sticky top-0 z-50 border-b border-gray-100'; require __DIR__ . '/nav-header.php'; ?>
    <?php endif; ?>

    <!-- Main Content -->
    <main id="main-content" class="min-h-screen">
