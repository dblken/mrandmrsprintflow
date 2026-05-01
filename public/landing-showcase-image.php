<?php
/**
 * Serves landing hero showcase images from project-root uploads/
 * when direct /uploads/* URLs are not reachable (common on domain-root deploys).
 */

declare(strict_types=1);

$root = dirname(__DIR__);

/** Allowed stems — basename without extension must match exactly */
$allowed_stems = ['tarpaulin', 'tshirt', 'sintraboard'];

$req = basename(isset($_GET['f']) ? (string)$_GET['f'] : '');
$stem = strtolower(pathinfo($req, PATHINFO_FILENAME));
$ext_in = strtolower((string)pathinfo($req, PATHINFO_EXTENSION));

if ($stem === '' || !in_array($stem, $allowed_stems, true)) {
    http_response_code(404);
    exit;
}

$try_exts = [];
if ($ext_in !== '') {
    $try_exts[] = $ext_in;
}
foreach (['jpg', 'jpeg', 'png', 'webp'] as $e) {
    if (!in_array($e, $try_exts, true)) {
        $try_exts[] = $e;
    }
}

$mime = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];

foreach ($try_exts as $ext) {
    if (!isset($mime[$ext])) {
        continue;
    }
    $path = $root . '/uploads/' . $stem . '.' . $ext;
    if (is_file($path)) {
        header('Content-Type: ' . $mime[$ext]);
        header('Cache-Control: public, max-age=86400');
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
