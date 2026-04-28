<?php
/**
 * Serve chat audio files securely from the uploads folder.
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

$locations = [
    __DIR__ . '/uploads/chat/audio/' . $file,
    __DIR__ . '/../uploads/chat/audio/' . $file,
    dirname(__DIR__) . '/uploads/chat/audio/' . $file,
];

$foundPath = null;
foreach ($locations as $path) {
    if (is_file($path)) {
        $foundPath = $path;
        break;
    }
}

if (!$foundPath) {
    http_response_code(404);
    error_log('[PrintFlow][Audio] Not found: ' . $file);
    exit('Audio not found');
}

$mime = 'audio/webm';
$ext = strtolower(pathinfo($foundPath, PATHINFO_EXTENSION));
if ($ext === 'mp3') {
    $mime = 'audio/mpeg';
} elseif ($ext === 'wav') {
    $mime = 'audio/wav';
} elseif ($ext === 'ogg') {
    $mime = 'audio/ogg';
}

header("Content-Type: $mime");
header('Content-Length: ' . filesize($foundPath));
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=300');
readfile($foundPath);
exit;
