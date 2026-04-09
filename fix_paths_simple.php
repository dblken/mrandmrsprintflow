<?php
$backup_dir = __DIR__ . '/backups_' . date('Ymd_His');
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

function processFile($file, $backup_dir) {
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
    
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    
    if ($ext === 'php') {
        $content = str_replace('href="/printflow/', 'href="<?php echo $base_path; ?>/', $content);
        $content = str_replace("href='/printflow/", "href='<?php echo \$base_path; ?>/", $content);
        $content = str_replace('src="/printflow/', 'src="<?php echo $base_path; ?>/', $content);
        $content = str_replace("src='/printflow/", "src='<?php echo \$base_path; ?>/", $content);
        $content = str_replace('action="/printflow/', 'action="<?php echo $base_path; ?>/', $content);
        $content = str_replace("action='/printflow/", "action='<?php echo \$base_path; ?>/", $content);
    } elseif ($ext === 'js') {
        $content = str_replace('"/printflow/', "' + (window.PFConfig?.basePath || '') + '/", $content);
        $content = str_replace("'/printflow/", "' + (window.PFConfig?.basePath || '') + '/", $content);
    }
    
    if ($content !== $original) {
        file_put_contents($file, $content);
        $fixed_count++;
        echo "✓ " . $relative . "\n";
    }
}

function scanDirectory2($dir, $backup_dir) {
    if (!is_dir($dir)) return;
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            scanDirectory2($path, $backup_dir);
        } else {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            if (in_array($ext, ['php', 'js'])) {
                $content = file_get_contents($path);
                if (strpos($content, '/printflow/') !== false) {
                    processFile($path, $backup_dir);
                }
            }
        }
    }
}

echo "Fixing paths...\n\n";

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        scanDirectory2($dir, $backup_dir);
    }
}

echo "\nFixed: $fixed_count files\n";
echo "Backups: " . basename($backup_dir) . "\n";
