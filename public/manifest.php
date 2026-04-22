<?php
// Load config for environment detection
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/../includes/shop_config.php';

$base_path = defined('BASE_PATH') ? BASE_PATH : '/printflow';
$asset_path = $base_path . '/public/assets/images';
$logo_src = !empty($shop_logo_url) ? $shop_logo_url : ($asset_path . '/icon-192.png');
$logo_ext = strtolower(pathinfo(parse_url($logo_src, PHP_URL_PATH) ?: $logo_src, PATHINFO_EXTENSION));
$logo_type = 'image/png';
if ($logo_ext === 'jpg' || $logo_ext === 'jpeg') {
    $logo_type = 'image/jpeg';
} elseif ($logo_ext === 'svg') {
    $logo_type = 'image/svg+xml';
} elseif ($logo_ext === 'webp') {
    $logo_type = 'image/webp';
}

header('Content-Type: application/json');

$manifest = [
    'name' => 'PrintFlow - Printing Shop',
    'short_name' => 'PrintFlow',
    'description' => 'Pickup-only printing shop for tarpaulins, t-shirts, stickers, and custom designs',
    'start_url' => $base_path . '/',
    'display' => 'standalone',
    'background_color' => '#ffffff',
    'theme_color' => '#4F46E5',
    'orientation' => 'portrait-primary',
    'icons' => [
        [
            'src' => $logo_src,
            'sizes' => '72x72',
            'type' => $logo_type,
            'purpose' => 'any maskable'
        ],
        [
            'src' => $logo_src,
            'sizes' => '96x96',
            'type' => $logo_type,
            'purpose' => 'any maskable'
        ],
        [
            'src' => $logo_src,
            'sizes' => '128x128',
            'type' => $logo_type,
            'purpose' => 'any maskable'
        ],
        [
            'src' => $logo_src,
            'sizes' => '144x144',
            'type' => $logo_type,
            'purpose' => 'any maskable'
        ],
        [
            'src' => $logo_src,
            'sizes' => '152x152',
            'type' => $logo_type,
            'purpose' => 'any maskable'
        ],
        [
            'src' => $logo_src,
            'sizes' => '192x192',
            'type' => $logo_type,
            'purpose' => 'any maskable'
        ],
        [
            'src' => $logo_src,
            'sizes' => '384x384',
            'type' => $logo_type,
            'purpose' => 'any maskable'
        ],
        [
            'src' => $logo_src,
            'sizes' => '512x512',
            'type' => $logo_type,
            'purpose' => 'any maskable'
        ]
    ],
    'categories' => ['business', 'productivity'],
    'screenshots' => []
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
