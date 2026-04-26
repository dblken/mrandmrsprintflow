<?php
/**
 * Shared Shop Configuration Helper
 * PrintFlow - Printing Shop PWA
 * 
 * Usage: include this file, then use $shop_name and get_logo_html()
 */

// Load config for environment detection
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

$_shop_cfg_path = __DIR__ . '/../public/assets/uploads/shop_config.json';
$_shop_cfg = file_exists($_shop_cfg_path)
    ? (json_decode(file_get_contents($_shop_cfg_path), true) ?: [])
    : [];

$shop_name = !empty($_shop_cfg['name']) ? htmlspecialchars($_shop_cfg['name']) : 'PrintFlow';
$shop_logo_file = $_shop_cfg['logo'] ?? '';

// Use dynamic base path
$base_path = defined('BASE_PATH') ? BASE_PATH : '/printflow';
$shop_logo_url  = !empty($shop_logo_file)
    ? $base_path . '/public/assets/uploads/' . $shop_logo_file
    : '';

function printflow_logo_version(): string {
    static $version = null;
    if ($version !== null) {
        return $version;
    }

    $cfgPath = __DIR__ . '/../public/assets/uploads/shop_config.json';
    $logoPath = '';
    if (is_file($cfgPath)) {
        $cfg = json_decode((string) file_get_contents($cfgPath), true);
        $logoFile = is_array($cfg) ? (string) ($cfg['logo'] ?? '') : '';
        if ($logoFile !== '') {
            $logoPath = __DIR__ . '/../public/assets/uploads/' . basename($logoFile);
        }
    }

    $parts = [];
    $parts[] = is_file($cfgPath) ? (string) filemtime($cfgPath) : '0';
    $parts[] = ($logoPath !== '' && is_file($logoPath)) ? (string) filemtime($logoPath) : '0';
    $version = implode('-', $parts);
    return $version;
}

/**
 * Returns the logo HTML:
 * - If a logo is uploaded: <img> tag
 * - Fallback: coloured icon with first letter of shop name
 */
function get_logo_html(string $size = '32px'): string {
    global $shop_name, $shop_logo_url;
    if (!empty($shop_logo_url)) {
        return '<img src="' . htmlspecialchars($shop_logo_url) . '?t=' . time()
            . '" alt="' . htmlspecialchars($shop_name) . '"'
            . ' style="width:' . $size . ';height:' . $size . ';border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;flex-shrink:0;display:block;"'
            . ' onerror="this.style.display=\'none\';">';
    }
    $tag = strip_tags($shop_name);
    if (function_exists('mb_substr')) {
        $first = strtoupper(mb_substr($tag, 0, 1, 'UTF-8'));
    } else {
        $first = strtoupper(substr($tag, 0, 1));
    }
    return '<div class="logo-icon">' . $first . '</div>';
}
