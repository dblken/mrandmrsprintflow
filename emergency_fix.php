<?php
/**
 * EMERGENCY HOTFIX for Production
 * This file temporarily fixes the path issues until you can upload all fixed files
 * 
 * Upload this to your server root and run it once: https://mrandmrsprintflow.com/emergency_fix.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PrintFlow Emergency Path Fix</h1>";
echo "<p>This will fix the hardcoded /printflow/ paths in your production files.</p>";

// Security check - remove this file after running
if (file_exists(__DIR__ . '/emergency_fix_completed.txt')) {
    die("<p style='color:red;'>This fix has already been run. Delete emergency_fix_completed.txt to run again.</p>");
}

$files_to_fix = [
    'includes/functions.php',
    'includes/staff_pending_check.php',
    'admin/services.php',
];

$fixes_applied = 0;
$errors = [];

foreach ($files_to_fix as $file) {
    $path = __DIR__ . '/' . $file;
    
    if (!file_exists($path)) {
        $errors[] = "File not found: $file";
        continue;
    }
    
    $content = file_get_contents($path);
    $original = $content;
    
    // Fix 1: Remove invalid PHP template strings
    $content = str_replace("'<?php echo \$base_path; ?>//printflow/", "''.", $content);
    $content = str_replace('<?php echo $base_path; ?>//printflow/', '', $content);
    
    // Fix 2: Fix upload_file function
    if (strpos($file, 'functions.php') !== false) {
        // Fix the upload_file relative_path line
        $old = "\$relative_path = '<?php echo \$base_path; ?>//printflow/uploads/' . \$destination . '/' . \$new_name;";
        $new = "\$base = defined('BASE_PATH') ? BASE_PATH : (defined('BASE_URL') ? BASE_URL : '');\n    \$relative_path = \$base . '/uploads/' . \$destination . '/' . \$new_name;";
        $content = str_replace($old, $new, $content);
        
        // Fix get_services_image_map
        $old = "function get_services_image_map() {\n    \$base = '<?php echo \$base_path; ?>//printflow/public';";
        $new = "function get_services_image_map() {\n    \$base = defined('BASE_PATH') ? BASE_PATH : (defined('BASE_URL') ? BASE_URL : '');\n    \$base .= '/public';";
        $content = str_replace($old, $new, $content);
        
        // Fix get_service_image_url
        $old = "if (\$cat === '') return '<?php echo \$base_path; ?>//printflow/public/assets/images/services/default.png';";
        $new = "\$base = defined('BASE_PATH') ? BASE_PATH : (defined('BASE_URL') ? BASE_URL : '');\n    if (\$cat === '') return \$base . '/public/assets/images/services/default.png';";
        $content = str_replace($old, $new, $content);
        
        $old = "return '<?php echo \$base_path; ?>//printflow/public/assets/images/services/default.png';";
        $new = "return \$base . '/public/assets/images/services/default.png';";
        $content = str_replace($old, $new, $content);
    }
    
    // Fix 3: Fix staff_pending_check.php
    if (strpos($file, 'staff_pending_check.php') !== false) {
        $old = "redirect('<?php echo \$base_path; ?>//printflow/staff/profile.php');";
        $new = "\$base = defined('BASE_PATH') ? BASE_PATH : (defined('BASE_URL') ? BASE_URL : '');\n    redirect(\$base . '/staff/profile.php');";
        $content = str_replace($old, $new, $content);
    }
    
    // Fix 4: Fix admin/services.php
    if (strpos($file, 'admin/services.php') !== false) {
        $old = "header('Location: /printflow/admin/services_management.php', true, 302);";
        $new = "header('Location: /admin/services_management.php', true, 302);";
        $content = str_replace($old, $new, $content);
    }
    
    if ($content !== $original) {
        if (file_put_contents($path, $content)) {
            echo "<p style='color:green;'>✓ Fixed: $file</p>";
            $fixes_applied++;
        } else {
            $errors[] = "Failed to write: $file";
        }
    } else {
        echo "<p style='color:orange;'>○ No changes needed: $file</p>";
    }
}

// Mark as completed
file_put_contents(__DIR__ . '/emergency_fix_completed.txt', date('Y-m-d H:i:s'));

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p>Fixes applied: $fixes_applied</p>";

if (!empty($errors)) {
    echo "<h3 style='color:red;'>Errors:</h3><ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}

echo "<hr>";
echo "<p><strong>IMPORTANT:</strong> Delete this file (emergency_fix.php) after running for security!</p>";
echo "<p>Then clear your browser cache and test the site.</p>";
