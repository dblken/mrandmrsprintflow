<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    http_response_code(403);
    exit('Forbidden');
}

$review_id = (int)($_GET['review_id'] ?? 0);
if ($review_id <= 0) {
    http_response_code(400);
    exit('Invalid review id');
}

$review = db_query("SELECT video_path FROM reviews WHERE id = ? LIMIT 1", 'i', [$review_id]);
$video_path = trim((string)($review[0]['video_path'] ?? ''));
if ($video_path === '') {
    http_response_code(404);
    exit('Video not found');
}

$normalized = str_replace('\\', '/', $video_path);
$candidates = [];

if (preg_match('#^https?://#i', $normalized)) {
    $parts = parse_url($normalized);
    $normalized = (string)($parts['path'] ?? '');
}

if ($normalized !== '') {
    $normalized = preg_replace('#^[A-Za-z]:#', '', $normalized);
    $normalized = '/' . ltrim($normalized, '/');

    $uploadsPos = strpos($normalized, '/uploads/');
    if ($uploadsPos !== false) {
        $relativeFromUploads = substr($normalized, $uploadsPos + 9);
        $candidates[] = realpath(__DIR__ . '/../uploads/' . $relativeFromUploads) ?: (__DIR__ . '/../uploads/' . $relativeFromUploads);
    }

    $publicPos = strpos($normalized, '/public/');
    if ($publicPos !== false) {
        $relativeFromPublic = substr($normalized, $publicPos + 8);
        $candidates[] = realpath(__DIR__ . '/' . $relativeFromPublic) ?: (__DIR__ . '/' . $relativeFromPublic);
    }

    $basename = basename($normalized);
    if ($basename !== '') {
        $candidates[] = realpath(__DIR__ . '/../uploads/reviews_videos/' . $basename) ?: (__DIR__ . '/../uploads/reviews_videos/' . $basename);
    }
}

$fullPath = '';
foreach ($candidates as $candidate) {
    if ($candidate && is_file($candidate)) {
        $fullPath = $candidate;
        break;
    }
}

if ($fullPath === '') {
    http_response_code(404);
    exit('Video file missing');
}

$size = filesize($fullPath);
$start = 0;
$end = $size - 1;

header('Content-Type: video/mp4');
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');

if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/', (string)$_SERVER['HTTP_RANGE'], $matches)) {
    $start = (int)$matches[1];
    if ($matches[2] !== '') {
        $end = (int)$matches[2];
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header("Content-Range: bytes */{$size}");
        exit;
    }

    http_response_code(206);
    header("Content-Range: bytes {$start}-{$end}/{$size}");
    header('Content-Length: ' . (($end - $start) + 1));
} else {
    header('Content-Length: ' . $size);
}

$fp = fopen($fullPath, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit('Unable to read video');
}

fseek($fp, $start);
$remaining = $end - $start + 1;
$chunkSize = 8192;

while (!feof($fp) && $remaining > 0) {
    $readLength = min($chunkSize, $remaining);
    $buffer = fread($fp, $readLength);
    if ($buffer === false) {
        break;
    }
    echo $buffer;
    flush();
    $remaining -= strlen($buffer);
}

fclose($fp);
exit;
