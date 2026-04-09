<?php
/**
 * Admin Path Helper
 * Include this at the top of admin pages to ensure BASE_PATH is available
 */

// Load config if not already loaded
if (!defined('BASE_PATH')) {
    if (file_exists(__DIR__ . '/../config.php')) {
        require_once __DIR__ . '/../config.php';
    } else {
        // Fallback
        define('BASE_PATH', '');
        define('BASE_URL', '');
    }
}

// Helper function to build URLs
function pf_url($path = '') {
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    if (empty($path)) return $base;
    // Remove leading slash to avoid double slashes
    $path = ltrim($path, '/');
    return $base . '/' . $path;
}

// Common admin URLs
$pf_admin_base = pf_url('admin');
$pf_public_base = pf_url('public');
$pf_uploads_base = pf_url('uploads');
$pf_assets_base = pf_url('public/assets');
