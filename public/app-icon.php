<?php
/**
 * Dynamic app icon that returns a valid PNG image.
 */
// Prevent any accidental output
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/shop_config.php';

// Clear any buffers before sending headers
while (ob_get_level()) ob_end_clean();

header("Content-Type: image/png");
header("Cache-Control: public, max-age=3600");

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
            // Convert to PNG if not already
            $raw = file_get_contents($logoPath);
            $im = imagecreatefromstring($raw);
            if ($im) {
                // Optionally add the branded background here if we wanted parity with SVG
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
    $bg = imagecolorallocate($im, 5, 48, 58); // #05303a
    imagefill($im, 0, 0, $bg);
    
    $white = imagecolorallocate($im, 255, 255, 255);
    $rawName = strip_tags((string)($shop_name ?? 'PrintFlow'));
    $letter = $rawName !== '' ? mb_strtoupper(mb_substr($rawName, 0, 1)) : 'P';
    
    // Use a basic font if ttf isn't available, or just fill
    // Since we don't know paths to fonts, we'll just use built-in font 5
    $fontSize = 5;
    $fw = imagefontwidth($fontSize);
    $fh = imagefontheight($fontSize);
    // Scale it up if possible? built-in fonts are small.
    // Let's just draw a big letter using rectangles if needed, but 
    // usually icon-512.png will exist.
    
    imagestring($im, 5, 256 - ($fw/2), 256 - ($fh/2), $letter, $white);
    
    imagepng($im);
    imagedestroy($im);
    exit;
}

// Ultimate fallback
exit;
