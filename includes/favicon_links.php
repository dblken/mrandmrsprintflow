<?php
/**
 * Global favicon: General Settings shop logo (when set), else circular SVG + PNG fallbacks.
 * Include once in <head> after <title>.
 */
require_once __DIR__ . '/shop_config.php';
/* Stable cache key — avoid time() so the tab icon URL does not change every request (fixes missing/blinking favicon). */
$cfg_path = __DIR__ . '/shop_config.php';
$fv = (string) (is_file($cfg_path) ? (int) filemtime($cfg_path) : 1);
$app_base = '/printflow';
if (defined('AUTH_REDIRECT_BASE')) {
    $app_base = rtrim(AUTH_REDIRECT_BASE, '/');
}

$shop_logo_icon_href = '';
$shop_logo_icon_ext = 'png';
if (!empty($shop_logo_file) && is_string($shop_logo_file)) {
    $safe_logo = basename(str_replace(['\\', "\0"], '/', $shop_logo_file));
    if ($safe_logo !== '' && $safe_logo !== '.' && $safe_logo !== '..') {
        $shop_logo_icon_href = rtrim($app_base, '/') . '/public/assets/uploads/' . rawurlencode($safe_logo);
        $shop_logo_icon_ext = strtolower(pathinfo($safe_logo, PATHINFO_EXTENSION));
    }
}

if ($shop_logo_icon_href !== '') {
    if (in_array($shop_logo_icon_ext, ['jpg', 'jpeg'], true)) {
        $logo_mime = 'image/jpeg';
    } elseif ($shop_logo_icon_ext === 'gif') {
        $logo_mime = 'image/gif';
    } elseif ($shop_logo_icon_ext === 'webp') {
        $logo_mime = 'image/webp';
    } elseif ($shop_logo_icon_ext === 'svg') {
        $logo_mime = 'image/svg+xml';
    } else {
        $logo_mime = 'image/png';
    }
    $logo_q = htmlspecialchars($shop_logo_icon_href, ENT_QUOTES, 'UTF-8') . '?v=' . rawurlencode($fv);
    echo '<link rel="icon" type="' . htmlspecialchars($logo_mime, ENT_QUOTES, 'UTF-8') . '" href="' . $logo_q . '">' . "\n";
    echo '<link rel="shortcut icon" href="' . $logo_q . '">' . "\n";
    echo '<link rel="apple-touch-icon" href="' . $logo_q . '">' . "\n";
} else {
    $svg_href = $app_base . '/public/favicon-circular.php?v=' . rawurlencode($fv);
    echo '<link rel="icon" type="image/svg+xml" href="' . htmlspecialchars($svg_href, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<link rel="shortcut icon" href="' . htmlspecialchars($svg_href, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    $png_fallback = $app_base . '/public/assets/images/favicon.png';
    echo '<link rel="icon" type="image/png" sizes="32x32" href="' . htmlspecialchars($png_fallback, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    $apple = $app_base . '/public/assets/images/icon-192.png';
    echo '<link rel="apple-touch-icon" href="' . htmlspecialchars($apple, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}
