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

$review = db_query("SELECT video_path, created_at FROM reviews WHERE id = ? LIMIT 1", 'i', [$review_id]);
$video_path = trim((string)($review[0]['video_path'] ?? ''));
if ($video_path === '') {
    http_response_code(404);
    exit('Video not found');
}

$fullPath = pf_resolve_review_video_file($video_path);
if ($fullPath === '') {
    $timeRaw = trim((string)($review[0]['created_at'] ?? ''));
    $timeProbe = preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $video_path) ? $video_path : $timeRaw;
    $stamp = '';
    if ($timeProbe !== '') {
        $ts = strtotime($timeProbe);
        if ($ts !== false) {
            $stamp = date('Ymd_His', $ts);
        }
    }
    if ($stamp !== '') {
        $roots = [
            __DIR__ . '/../uploads/reviews_videos',
            __DIR__ . '/../public/uploads/reviews_videos',
            __DIR__ . '/../public/assets/uploads/reviews_videos',
        ];
        foreach ($roots as $root) {
            $root = rtrim((string)$root, '/\\');
            if (!is_dir($root)) {
                continue;
            }
            $matches = glob($root . '/review_' . $stamp . '_*.mp4');
            if (!empty($matches[0]) && is_file($matches[0])) {
                $fullPath = $matches[0];
                break;
            }
        }
    }
}
if ($fullPath === '') {
    http_response_code(404);
    exit('Video file missing');
}

$size = filesize($fullPath);
$start = 0;
$end = $size - 1;

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'webm' => 'video/webm',
    'mov' => 'video/quicktime',
    'ogv' => 'video/ogg',
    'avi' => 'video/x-msvideo',
    default => 'video/mp4',
};

header('Content-Type: ' . $mime);
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
