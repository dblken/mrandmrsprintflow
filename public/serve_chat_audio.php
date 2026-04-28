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
    // Common locations for audio uploads
    __DIR__ . '/uploads/chat/audio/' . $file,
    __DIR__ . '/../uploads/chat/audio/' . $file,
    dirname(__DIR__) . '/uploads/chat/audio/' . $file,
    // Some deployments store webm/voice files in a "videos" folder
    __DIR__ . '/uploads/chat/videos/' . $file,
    __DIR__ . '/../uploads/chat/videos/' . $file,
    dirname(__DIR__) . '/uploads/chat/videos/' . $file,
    // Fallback to any chat uploads folder sibling
    __DIR__ . '/uploads/chat/' . $file,
    __DIR__ . '/../uploads/chat/' . $file,
    dirname(__DIR__) . '/uploads/chat/' . $file,
];

$foundPath = null;
foreach ($locations as $path) {
    if (is_file($path)) {
        $foundPath = $path;
        break;
    }
}

if (!$foundPath) {
    // Attempt a limited recursive search in common uploads roots as a fallback.
    $searchRoots = [
        __DIR__ . '/uploads',
        __DIR__ . '/../uploads',
        dirname(__DIR__) . '/uploads',
    ];

    $found = null;
    $maxDepth = 4;
    $searchAttempts = [];

    $finder = function ($dir, $target, $depth) use (&$finder, &$found, &$searchAttempts, $maxDepth) {
        if ($found !== null) return;
        if ($depth > $maxDepth) return;
        if (!is_dir($dir)) return;
        $items = @scandir($dir);
        if ($items === false) return;
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;
            $p = $dir . '/' . $it;
            $searchAttempts[] = $p;
            if (is_file($p) && basename($p) === $target) {
                $found = $p;
                return;
            }
            if (is_dir($p)) {
                $finder($p, $target, $depth + 1);
                if ($found !== null) return;
            }
        }
    };

    foreach ($searchRoots as $root) {
        $finder($root, $file, 0);
        if ($found !== null) {
            $foundPath = $found;
            break;
        }
    }

    if (!$foundPath) {
        http_response_code(404);
        error_log('[PrintFlow][Audio] Not found: ' . $file . ' — attempted paths: ' . implode(', ', array_slice($searchAttempts, 0, 100)));
        exit('Audio not found');
    }
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
