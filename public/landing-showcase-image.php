<?php
/**
 * Serves landing-page hero showcase images from ../uploads (basename only; no path traversal).
 */
declare(strict_types=1);

$f = isset($_GET['f']) ? basename((string) $_GET['f']) : '';
if ($f === '' || !preg_match('/^[a-z0-9_-]+\.(jpe?g|png|webp)$/i', $f)) {
    http_response_code(404);
    exit;
}

$path = dirname(__DIR__) . '/uploads/' . $f;
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$mime = @mime_content_type($path);
if (!is_string($mime) || $mime === '') {
    $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
