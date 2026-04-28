<?php
/**
 * Public store image endpoint.
 * Serves uploads/store_pict.* even when /uploads is not web-accessible.
 */

$root = dirname(__DIR__);
$candidates = [
    $root . '/uploads/store_pict.jpg'  => 'image/jpeg',
    $root . '/uploads/store_pict.jpeg' => 'image/jpeg',
    $root . '/uploads/store_pict.png'  => 'image/png',
    $root . '/uploads/store_pict.webp' => 'image/webp',
];

foreach ($candidates as $path => $mime) {
    if (is_file($path)) {
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=300');
        readfile($path);
        exit;
    }
}

http_response_code(404);
header('Content-Type: image/png');
$fallback = $root . '/public/assets/uploads/profiles/default.png';
if (is_file($fallback)) {
    readfile($fallback);
}
