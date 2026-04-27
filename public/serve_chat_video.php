<?php
/**
 * Serve chat videos securely from the root uploads folder.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    http_response_code(403);
    exit('Forbidden');
}

$file = basename($_GET['file'] ?? '');
if ($file === '') {
    http_response_code(400);
    exit('Missing file');
}

// Check multiple potential locations for the video
$locations = [
    __DIR__ . '/uploads/chat/videos/' . $file,
    __DIR__ . '/../uploads/chat/videos/' . $file,
    dirname(__DIR__) . '/uploads/chat/videos/' . $file
];

$foundPath = null;
foreach ($locations as $path) {
    if (file_exists($path)) {
        $foundPath = $path;
        break;
    }
}

if (!$foundPath) {
    http_response_code(404);
    error_log("[PrintFlow][Video] Not found: " . $file);
    exit('Video not found');
}

$mime = 'video/mp4';
$ext = strtolower(pathinfo($foundPath, PATHINFO_EXTENSION));
if ($ext === 'webm') $mime = 'video/webm';
if ($ext === 'mov') $mime = 'video/quicktime';

header("Content-Type: $mime");
header("Content-Length: " . filesize($foundPath));
header("Accept-Ranges: bytes");
readfile($foundPath);
exit;
