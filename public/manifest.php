<?php
// Load config for environment detection
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/../includes/shop_config.php';

$base_path = defined('BASE_PATH') ? BASE_PATH : '/printflow';
$asset_path = $base_path . '/public/assets/images';
$svg_logo_src = $base_path . '/public/app-icon.php';

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
            'src' => $svg_logo_src,
            'sizes' => 'any',
            'type' => 'image/svg+xml',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $asset_path . '/icon-72.png',
            'sizes' => '72x72',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $asset_path . '/icon-96.png',
            'sizes' => '96x96',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $asset_path . '/icon-128.png',
            'sizes' => '128x128',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $asset_path . '/icon-144.png',
            'sizes' => '144x144',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $asset_path . '/icon-152.png',
            'sizes' => '152x152',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $asset_path . '/icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $asset_path . '/icon-384.png',
            'sizes' => '384x384',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $asset_path . '/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ]
    ],
    'categories' => ['business', 'productivity'],
    'screenshots' => []
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
