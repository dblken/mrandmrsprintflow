<?php
$directories = [
    __DIR__ . '/admin',
    __DIR__ . '/staff',
    __DIR__ . '/manager',
];

$base_path_code = "
// Ensure \$base_path is defined
if (!isset(\$base_path)) {
    if (file_exists(__DIR__ . '/../config.php')) {
        require_once __DIR__ . '/../config.php';
    }
    \$base_path = defined('BASE_PATH') ? BASE_PATH : '/printflow';
}";

$fixed = 0;

foreach ($directories as $dir) {
    if (!is_dir($dir)) continue;
    
    $files = glob($dir . '/*.php');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        
        // Skip if already has base_path definition
        if (strpos($content, 'Ensure $base_path is defined') !== false) {
            continue;
        }
        
        // Skip if doesn't use base_path
        if (strpos($content, '$base_path') === false && strpos($content, 'echo $base_path') === false) {
            continue;
        }
        
        // Find position after require_role or last require_once
        if (preg_match('/(require_role\([^)]+\);)/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);
        } elseif (preg_match('/(require_once[^;]+;)(?!.*require_once)/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);
        } else {
            continue;
        }
        
        // Insert base_path definition
        $before = substr($content, 0, $pos);
        $after = substr($content, $pos);
        $new_content = $before . $base_path_code . $after;
        
        file_put_contents($file, $new_content);
        echo "✓ " . basename($file) . "\n";
        $fixed++;
    }
}

echo "\nFixed: $fixed files\n";
