<?php
/**
 * Find all files with hardcoded /printflow/ paths
 */

$directories = [
    __DIR__ . '/admin',
    __DIR__ . '/staff',
    __DIR__ . '/manager',
    __DIR__ . '/customer',
    __DIR__ . '/includes',
    __DIR__ . '/public',
];

$extensions = ['php', 'js', 'html'];
$found_files = [];

function scanDirectory($dir, $extensions) {
    $results = [];
    if (!is_dir($dir)) return $results;
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            $results = array_merge($results, scanDirectory($path, $extensions));
        } else {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            if (in_array($ext, $extensions)) {
                $content = file_get_contents($path);
                // Look for /printflow/ in various contexts
                if (preg_match('/["\']\/printflow\//', $content) || 
                    preg_match('/href=["\']\\/printflow/', $content) ||
                    preg_match('/src=["\']\\/printflow/', $content) ||
                    preg_match('/action=["\']\\/printflow/', $content) ||
                    preg_match('/url\(["\']?\/printflow/', $content)) {
                    $results[] = $path;
                }
            }
        }
    }
    return $results;
}

echo "Scanning for hardcoded /printflow/ paths...\n\n";

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        $files = scanDirectory($dir, $extensions);
        $found_files = array_merge($found_files, $files);
    }
}

if (empty($found_files)) {
    echo "✓ No hardcoded /printflow/ paths found!\n";
} else {
    echo "Found " . count($found_files) . " files with hardcoded paths:\n\n";
    foreach ($found_files as $file) {
        $relative = str_replace(__DIR__ . '/', '', $file);
        echo "  - " . $relative . "\n";
    }
    
    echo "\n\nWould you like to fix these files? (This will replace /printflow/ with dynamic paths)\n";
    echo "Files will be backed up before modification.\n";
}
