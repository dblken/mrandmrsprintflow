<?php
/**
 * Script to replace hardcoded /printflow/ paths with dynamic base_path
 */

$files_to_fix = [
    __DIR__ . '/includes/staff_sidebar.php',
    __DIR__ . '/includes/manager_sidebar.php',
];

foreach ($files_to_fix as $file) {
    if (!file_exists($file)) {
        echo "File not found: " . basename($file) . "\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $original_content = $content;
    
    // Simple string replacements
    $content = str_replace('href="/printflow/', 'href="<?php echo $base_path; ?>/', $content);
    $content = str_replace('src="/printflow/', 'src="<?php echo $base_path; ?>/', $content);
    $content = str_replace('action="/printflow/', 'action="<?php echo $base_path; ?>/', $content);
    
    if ($content !== $original_content) {
        file_put_contents($file, $content);
        echo "✓ Fixed: " . basename($file) . "\n";
    } else {
        echo "○ No changes needed: " . basename($file) . "\n";
    }
}

echo "\nDone! All sidebar files have been updated.\n";
