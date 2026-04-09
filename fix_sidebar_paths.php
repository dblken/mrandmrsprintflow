<?php
/**
 * Script to replace hardcoded /printflow/ paths with dynamic $base_path
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
    
    // Replace hardcoded /printflow/ paths with <?php echo $base_path; ?>
    $replacements = [
        // href="/printflow/ -> href="<?php echo $base_path; ?>/
        '/href="\/printflow\//' => 'href="<?php echo $base_path; ?>/',
        
        // src="/printflow/ -> src="<?php echo $base_path; ?>/
        '/src="\/printflow\//' => 'src="<?php echo $base_path; ?>/',
        
        // action="/printflow/ -> action="<?php echo $base_path; ?>/
        '/action="\/printflow\//' => 'action="<?php echo $base_path; ?>/',
        
        // '/printflow/public/ -> <?php echo $base_path; ?>/public/
        "'/printflow/public/" => "'<?php echo " . '$base_path' . "; ?>/public/",
        
        // '/printflow/uploads/ -> <?php echo $base_path; ?>/uploads/
        "'/printflow/uploads/" => "'<?php echo " . '$base_path' . "; ?>/uploads/",
        
        // '/printflow/staff/ -> <?php echo $base_path; ?>/staff/
        "'/printflow/staff/" => "'<?php echo " . '$base_path' . "; ?>/staff/",
        
        // '/printflow/manager/ -> <?php echo $base_path; ?>/manager/
        "'/printflow/manager/" => "'<?php echo " . '$base_path' . "; ?>/manager/",
        
        // '/printflow/admin/ -> <?php echo $base_path; ?>/admin/
        "'/printflow/admin/" => "'<?php echo " . '$base_path' . "; ?>/admin/",
    ];
    
    foreach ($replacements as $search => $replace) {
        $content = preg_replace($search, $replace, $content);
    }
    
    if ($content !== $original_content) {
        file_put_contents($file, $content);
        echo "✓ Fixed: " . basename($file) . "\n";
    } else {
        echo "○ No changes needed: " . basename($file) . "\n";
    }
}

echo "\nDone! All sidebar files have been updated.\n";
