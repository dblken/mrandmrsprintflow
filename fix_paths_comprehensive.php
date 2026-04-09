<?php
$backup_dir = __DIR__ . '/backups_final_' . date('Ymd_His');
mkdir($backup_dir, 0755, true);

$directories = [
    __DIR__ . '/admin',
    __DIR__ . '/staff',
    __DIR__ . '/manager',
    __DIR__ . '/customer',
    __DIR__ . '/includes',
    __DIR__ . '/public',
];

$fixed_count = 0;
$base_path_var = '<?php echo $base_path; ?>';

function processPhpFile($file, $backup_dir) {
    global $fixed_count, $base_path_var;
    
    $content = file_get_contents($file);
    $original = $content;
    
    $relative = str_replace(__DIR__ . '/', '', $file);
    $backup_path = $backup_dir . '/' . $relative;
    $backup_dir_path = dirname($backup_path);
    
    if (!is_dir($backup_dir_path)) {
        mkdir($backup_dir_path, 0755, true);
    }
    
    file_put_contents($backup_path, $content);
    
    // Multiple replacement patterns
    $patterns = [
        // HTML attributes with double quotes
        '~href="(/printflow/)~' => 'href="' . $base_path_var . '/',
        '~src="(/printflow/)~' => 'src="' . $base_path_var . '/',
        '~action="(/printflow/)~' => 'action="' . $base_path_var . '/',
        
        // HTML attributes with single quotes  
        "~href='(/printflow/)~" => "href='" . $base_path_var . "/",
        "~src='(/printflow/)~" => "src='" . $base_path_var . "/",
        "~action='(/printflow/)~" => "action='" . $base_path_var . "/",
        
        // JavaScript/PHP strings with double quotes
        '~"(/printflow/[^"]+)"~' => '"' . $base_path_var . '/$1"',
        
        // JavaScript/PHP strings with single quotes
        "~'(/printflow/[^']+)'~" => "'" . $base_path_var . "/$1'",
        
        // window.location assignments
        '~window\.location\s*=\s*["\'](/printflow/)~' => 'window.location = "' . $base_path_var . '/',
        
        // fetch() calls
        '~fetch\(["\'](/printflow/)~' => 'fetch("' . $base_path_var . '/',
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }
    
    if ($content !== $original) {
        file_put_contents($file, $content);
        $fixed_count++;
        echo "✓ " . $relative . "\n";
        return true;
    }
    
    return false;
}

function processJsFile($file, $backup_dir) {
    global $fixed_count;
    
    $content = file_get_contents($file);
    $original = $content;
    
    $relative = str_replace(__DIR__ . '/', '', $file);
    $backup_path = $backup_dir . '/' . $relative;
    $backup_dir_path = dirname($backup_path);
    
    if (!is_dir($backup_dir_path)) {
        mkdir($backup_dir_path, 0755, true);
    }
    
    file_put_contents($backup_path, $content);
    
    // For JS files, use window.PFConfig.basePath
    $js_base = "' + (window.PFConfig?.basePath || '') + '";
    
    $content = preg_replace('~["\'](/printflow/)~', "'" . $js_base . "/", $content);
    
    if ($content !== $original) {
        file_put_contents($file, $content);
        $fixed_count++;
        echo "✓ " . $relative . "\n";
        return true;
    }
    
    return false;
}

function scanDirectory3($dir, $backup_dir) {
    if (!is_dir($dir)) return;
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            scanDirectory3($path, $backup_dir);
        } else {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            if ($ext === 'php') {
                $content = file_get_contents($path);
                if (strpos($content, '/printflow/') !== false) {
                    processPhpFile($path, $backup_dir);
                }
            } elseif ($ext === 'js') {
                $content = file_get_contents($path);
                if (strpos($content, '/printflow/') !== false) {
                    processJsFile($path, $backup_dir);
                }
            }
        }
    }
}

echo "Comprehensive path fix...\n\n";

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        scanDirectory3($dir, $backup_dir);
    }
}

echo "\nFixed: $fixed_count files\n";
echo "Backups: " . basename($backup_dir) . "\n";
