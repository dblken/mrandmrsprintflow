<?php
/**
 * Fix all hardcoded /printflow/ paths throughout the application
 * This script will:
 * 1. Create backups of all files
 * 2. Replace hardcoded paths with dynamic ones
 * 3. Handle PHP and JavaScript files differently
 */

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

$extensions = ['php', 'js', 'html'];
$fixed_count = 0;
$error_count = 0;

function fixPhpFile($content) {
    // For PHP files, replace with dynamic PHP code
    
    // href="/printflow/ -> href="<?php echo $base_path; ?>/
    $content = preg_replace('/href=(["\'])\/printflow\//', 'href=$1<?php echo $base_path; ?>/', $content);
    
    // src="/printflow/ -> src="<?php echo $base_path; ?>/
    $content = preg_replace('/src=(["\'])\/printflow\//', 'src=$1<?php echo $base_path; ?>/', $content);
    
    // action="/printflow/ -> action="<?php echo $base_path; ?>/
    $content = preg_replace('/action=(["\'])\/printflow\//', 'action=$1<?php echo $base_path; ?>/', $content);
    
    // '/printflow/ in strings -> ' . $base_path . '/
    $content = preg_replace("/(['\"])\/printflow\//", "$1<?php echo \$base_path; ?>/", $content);
    
    // window.location = '/printflow/ -> window.location = '<?php echo $base_path; ?>/
    $content = preg_replace("/window\.location\s*=\s*(['\"])\/printflow\//", "window.location = $1<?php echo \$base_path; ?>/", $content);
    
    // fetch('/printflow/ -> fetch('<?php echo $base_path; ?>/
    $content = preg_replace("/fetch\((['\"])\/printflow\//", "fetch($1<?php echo \$base_path; ?>/", $content);
    
    return $content;
}

function fixJsFile($content) {
    // For pure JS files, we need a different approach
    // Replace /printflow/ with a variable that will be set globally
    
    // Use window.PFConfig.basePath which is set in the sidebar
    $content = preg_replace("/(['\"])\/printflow\//", "$1' + (window.PFConfig?.basePath || '') + '/", $content);
    
    return $content;
}

function fixHtmlFile($content) {
    // For HTML files, we can't use PHP, so we'll use JavaScript
    // This is a limitation - HTML files should ideally be converted to PHP
    
    // For now, just document which HTML files need manual attention
    return $content;
}

function processFile($file, $backup_dir) {
    global $fixed_count, $error_count;
    
    $content = file_get_contents($file);
    $original_content = $content;
    
    // Create backup
    $relative_path = str_replace(__DIR__ . '/', '', $file);
    $backup_path = $backup_dir . '/' . $relative_path;
    $backup_dir_path = dirname($backup_path);
    
    if (!is_dir($backup_dir_path)) {
        mkdir($backup_dir_path, 0755, true);
    }
    
    file_put_contents($backup_path, $content);
    
    // Determine file type and apply appropriate fixes
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    
    if ($ext === 'php') {
        $content = fixPhpFile($content);
    } elseif ($ext === 'js') {
        $content = fixJsFile($content);
    } elseif ($ext === 'html') {
        $content = fixHtmlFile($content);
    }
    
    // Only write if content changed
    if ($content !== $original_content) {
        if (file_put_contents($file, $content)) {
            $fixed_count++;
            echo "✓ Fixed: " . $relative_path . "\n";
        } else {
            $error_count++;
            echo "✗ Error: " . $relative_path . "\n";
        }
    }
}

function scanAndFix($dir, $extensions, $backup_dir) {
    if (!is_dir($dir)) return;
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            scanAndFix($path, $extensions, $backup_dir);
        } else {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            if (in_array($ext, $extensions)) {
                $content = file_get_contents($path);
                // Check if file has hardcoded paths
                if (preg_match('/["\']\/printflow\//', $content) || 
                    preg_match('/href=["\']\\/printflow/', $content) ||
                    preg_match('/src=["\']\\/printflow/', $content) ||
                    preg_match('/action=["\']\\/printflow/', $content)) {
                    processFile($path, $backup_dir);
                }
            }
        }
    }
}

echo "Starting comprehensive path fix...\n";
echo "Backup directory: " . basename($backup_dir) . "\n\n";

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        echo "Processing: " . basename($dir) . "/\n";
        scanAndFix($dir, $extensions, $backup_dir);
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Summary:\n";
echo "  Fixed: $fixed_count files\n";
echo "  Errors: $error_count files\n";
echo "  Backups: $backup_dir\n";
echo str_repeat("=", 50) . "\n";

if ($error_count > 0) {
    echo "\n⚠ Some files had errors. Check the output above.\n";
} else {
    echo "\n✓ All files fixed successfully!\n";
}

echo "\nNote: HTML files may need manual conversion to PHP for dynamic paths.\n";
