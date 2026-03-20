<?php
/**
 * SVG favicon: shop logo clipped to a circle (browser tab icon).
 */
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

require_once __DIR__ . '/../includes/shop_config.php';

$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$origin = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

if (!empty($shop_logo_url)) {
    $img = htmlspecialchars($origin . $shop_logo_url, ENT_QUOTES, 'UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">';
    echo '<defs><clipPath id="pfCircle"><circle cx="32" cy="32" r="30"/></clipPath></defs>';
    echo '<circle cx="32" cy="32" r="32" fill="#00232b"/>';
    echo '<image href="' . $img . '" x="2" y="2" width="60" height="60" clip-path="url(#pfCircle)" preserveAspectRatio="xMidYMid slice"/>';
    echo '</svg>';
    exit;
}

$raw_name = strip_tags((string) ($shop_name ?? 'PrintFlow'));
$letter = $raw_name !== '' ? mb_strtoupper(mb_substr($raw_name, 0, 1)) : 'P';
$letter = htmlspecialchars($letter, ENT_QUOTES, 'UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">';
echo '<defs><linearGradient id="pfG" x1="0%" y1="0%" x2="100%" y2="0%">';
echo '<stop offset="0%" stop-color="#00232b"/><stop offset="100%" stop-color="#53C5E0"/>';
echo '</linearGradient></defs>';
echo '<circle cx="32" cy="32" r="32" fill="url(#pfG)"/>';
echo '<text x="32" y="42" text-anchor="middle" fill="#ffffff" font-size="30" font-weight="700" font-family="system-ui,-apple-system,sans-serif">' . $letter . '</text>';
echo '</svg>';
