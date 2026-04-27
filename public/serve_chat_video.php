<?php
/**
 * Serve chat videos securely from the root uploads folder.
 * Supports Byte-Range requests for streaming large files.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    http_response_code(403);
    exit('Forbidden');
}

$file = $_GET['file'] ?? '';
if ($file === '') {
    http_response_code(400);
    exit('Missing file parameter');
}

// Security: Prevent directory traversal
$file = basename($file);
$fullPath = __DIR__ . '/../uploads/chat/videos/' . $file;

if (!is_file($fullPath)) {
    // Check fallback locations
    $fallbacks = [
        __DIR__ . '/../public/uploads/chat/videos/' . $file,
        __DIR__ . '/../public/assets/uploads/chat/videos/' . $file
    ];
    foreach ($fallbacks as $fb) {
        if (is_file($fb)) {
            $fullPath = $fb;
            break;
        }
    }
}

if (!is_file($fullPath)) {
    http_response_code(404);
    error_log("[PrintFlow][Video] File missing: " . $fullPath);
    exit('Video file missing');
}

if (!is_readable($fullPath)) {
    http_response_code(503);
    error_log("[PrintFlow][Video] Permission denied: " . $fullPath);
    exit('Permission denied');
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
?>
