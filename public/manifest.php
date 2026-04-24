<?php
// Load config for environment detection
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/../includes/shop_config.php';

$base_path = defined('BASE_PATH') ? BASE_PATH : '/printflow';
$asset_path = $base_path . '/public/assets/images';
$logo_version = rawurlencode(printflow_logo_version());
$svg_logo_src = $base_path . '/public/app-icon.php?v=' . $logo_version;
$uploaded_logo_src = !empty($shop_logo_url) ? ($shop_logo_url . '?v=' . $logo_version) : '';
$uploaded_logo_type = 'image/png';

if ($uploaded_logo_src !== '') {
    $uploaded_logo_ext = strtolower(pathinfo(parse_url($uploaded_logo_src, PHP_URL_PATH) ?: $uploaded_logo_src, PATHINFO_EXTENSION));
    if ($uploaded_logo_ext === 'jpg' || $uploaded_logo_ext === 'jpeg') {
        $uploaded_logo_type = 'image/jpeg';
    } elseif ($uploaded_logo_ext === 'webp') {
        $uploaded_logo_type = 'image/webp';
    } elseif ($uploaded_logo_ext === 'gif') {
        $uploaded_logo_type = 'image/gif';
    } elseif ($uploaded_logo_ext === 'svg') {
        $uploaded_logo_type = 'image/svg+xml';
    }
}

header('Content-Type: application/json');

$manifest_icons = [
    [
        'src' => $svg_logo_src,
        'sizes' => 'any',
        'type' => 'image/svg+xml',
        'purpose' => 'any maskable'
    ],
];

$icon_sizes = ['72x72', '96x96', '128x128', '144x144', '152x152', '192x192', '384x384', '512x512'];
foreach ($icon_sizes as $size) {
    $manifest_icons[] = [
        'src' => $uploaded_logo_src !== '' ? $uploaded_logo_src : $svg_logo_src,
        'sizes' => $size,
        'type' => $uploaded_logo_src !== '' ? $uploaded_logo_type : 'image/svg+xml',
        'purpose' => 'any maskable'
    ];
}

$manifest = [
    'name' => 'PrintFlow - Printing Shop',
    'short_name' => 'PrintFlow',
    'description' => 'Pickup-only printing shop for tarpaulins, t-shirts, stickers, and custom designs',
    'start_url' => $base_path . '/',
    'display' => 'standalone',
    'background_color' => '#ffffff',
    'theme_color' => '#4F46E5',
    'orientation' => 'portrait-primary',
    'icons' => $manifest_icons,
    'categories' => ['business', 'productivity'],
    'screenshots' => []
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
