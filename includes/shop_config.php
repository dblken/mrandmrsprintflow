<?php
/**
 * Shared Shop Configuration Helper
 * PrintFlow - Printing Shop PWA
 * 
 * Usage: include this file, then use $shop_name and get_logo_html()
 */

$_shop_cfg_path = __DIR__ . '/../public/assets/uploads/shop_config.json';
$_shop_cfg = file_exists($_shop_cfg_path)
    ? (json_decode(file_get_contents($_shop_cfg_path), true) ?: [])
    : [];

$shop_name = !empty($_shop_cfg['name']) ? htmlspecialchars($_shop_cfg['name']) : 'PrintFlow';
$shop_logo_file = $_shop_cfg['logo'] ?? '';
$shop_logo_url  = !empty($shop_logo_file)
    ? '/printflow/public/assets/uploads/' . $shop_logo_file
    : '';

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
            . ' style="width:' . $size . ';height:' . $size . ';border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;flex-shrink:0;display:block;">';
    }
    $first = strtoupper(mb_substr(strip_tags($shop_name), 0, 1));
    return '<div class="logo-icon">' . $first . '</div>';
}
