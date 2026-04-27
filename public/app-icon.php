<?php
declare(strict_types=1);
/**
 * Dynamic app icon that returns a valid PNG image.
 */
ob_start();
error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/shop_config.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');

// Try to use shop logo if available
if (!empty($shop_logo_file)) {
    $logoFile = basename((string)$shop_logo_file);
    $logoPath = __DIR__ . '/assets/uploads/' . $logoFile;
    
    if (is_file($logoPath) && is_readable($logoPath)) {
        $info = @getimagesize($logoPath);
        if ($info && $info[2] === IMAGETYPE_PNG) {
            readfile($logoPath);
            exit;
        } elseif ($info && function_exists('imagecreatefromstring')) {
            $raw = @file_get_contents($logoPath);
            $im = $raw !== false ? @imagecreatefromstring($raw) : false;
            if ($im) {
                imagepng($im);
                imagedestroy($im);
                exit;
            }
        }
    }
}

// Fallback to static icon if exists
$fallback = __DIR__ . '/assets/images/icon-512.png';
if (is_file($fallback)) {
    readfile($fallback);
    exit;
}

// Last resort: Create a simple dynamic PNG with the first letter
if (function_exists('imagecreatetruecolor')) {
    $im = imagecreatetruecolor(512, 512);
    $bg = imagecolorallocate($im, 5, 48, 58);
    imagefill($im, 0, 0, $bg);
    
    $white = imagecolorallocate($im, 255, 255, 255);
    $rawName = strip_tags((string)($shop_name ?? 'PrintFlow'));
    $letter = $rawName !== '' ? mb_strtoupper(mb_substr($rawName, 0, 1)) : 'P';
    
    $fontSize = 5;
    $fw = imagefontwidth($fontSize);
    $fh = imagefontheight($fontSize);
    imagestring($im, 5, (int)(256 - ($fw / 2)), (int)(256 - ($fh / 2)), $letter, $white);
    
    imagepng($im);
    imagedestroy($im);
    exit;
}

// Ultimate fallback
exit;
