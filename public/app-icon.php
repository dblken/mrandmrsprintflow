<?php
/**
 * Dynamic app icon that uses the uploaded shop logo inside an SVG wrapper.
 */
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

require_once __DIR__ . '/../includes/shop_config.php';

if (!empty($shop_logo_url)) {
    $logoFile = basename((string)($shop_logo_file ?? ''));
    $logoPath = $logoFile !== '' ? (__DIR__ . '/assets/uploads/' . $logoFile) : '';
    $embeddedImg = '';

    if ($logoPath !== '' && is_file($logoPath) && is_readable($logoPath)) {
        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
        $raw = file_get_contents($logoPath);
        if ($raw !== false) {
            $embeddedImg = 'data:' . $mime . ';base64,' . base64_encode($raw);
        }
    }

    if ($embeddedImg === '') {
        $embeddedImg = htmlspecialchars((string)$shop_logo_url, ENT_QUOTES, 'UTF-8');
    } else {
        $embeddedImg = htmlspecialchars($embeddedImg, ENT_QUOTES, 'UTF-8');
    }

    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="512" height="512">';
    echo '<defs><clipPath id="pfRounded"><rect x="24" y="24" width="464" height="464" rx="112" ry="112"/></clipPath></defs>';
    echo '<rect x="0" y="0" width="512" height="512" rx="128" ry="128" fill="#052a33"/>';
    echo '<image href="' . $embeddedImg . '" x="24" y="24" width="464" height="464" clip-path="url(#pfRounded)" preserveAspectRatio="xMidYMid slice"/>';
    echo '</svg>';
    exit;
}

$rawName = strip_tags((string) ($shop_name ?? 'PrintFlow'));
$letter = $rawName !== '' ? mb_strtoupper(mb_substr($rawName, 0, 1)) : 'P';
$letter = htmlspecialchars($letter, ENT_QUOTES, 'UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="512" height="512">';
echo '<defs><linearGradient id="pfAppG" x1="0%" y1="0%" x2="100%" y2="100%">';
echo '<stop offset="0%" stop-color="#05303a"/><stop offset="100%" stop-color="#14b8a6"/>';
echo '</linearGradient></defs>';
echo '<rect x="0" y="0" width="512" height="512" rx="128" ry="128" fill="url(#pfAppG)"/>';
echo '<text x="256" y="320" text-anchor="middle" fill="#ffffff" font-size="220" font-weight="700" font-family="system-ui,-apple-system,sans-serif">' . $letter . '</text>';
echo '</svg>';
