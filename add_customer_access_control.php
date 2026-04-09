<?php
/**
 * Script to add require_customer() to all customer pages
 */

$customer_dir = __DIR__ . '/customer';
$files = glob($customer_dir . '/*.php');

$api_files = ['api_', 'get_', 'process_', 'reupload_'];
$skip_files = ['customer_order_api.php'];

foreach ($files as $file) {
    $basename = basename($file);
    
    // Skip API files and specific files
    $is_api = false;
    foreach ($api_files as $prefix) {
        if (strpos($basename, $prefix) === 0) {
            $is_api = true;
            break;
        }
    }
    
    if ($is_api || in_array($basename, $skip_files)) {
        echo "Skipping API/process file: $basename\n";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check if already has require_customer()
    if (strpos($content, 'require_customer()') !== false) {
        echo "Already protected: $basename\n";
        continue;
    }
    
    // Find the position after includes
    $patterns = [
        "/require_once __DIR__ \. '\/\.\.\/includes\/auth\.php';/",
        "/require_once __DIR__ \. '\/\.\.\/includes\/functions\.php';/"
    ];
    
    $found = false;
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);
            
            // Find the end of the line
            $newline_pos = strpos($content, "\n", $pos);
            if ($newline_pos !== false) {
                $pos = $newline_pos + 1;
            }
            
            // Insert require_customer() after the includes
            $before = substr($content, 0, $pos);
            $after = substr($content, $pos);
            
            // Check if there's already a blank line
            if (substr($after, 0, 2) === "\r\n" || substr($after, 0, 1) === "\n") {
                $new_content = $before . "\n// Require customer access only\nrequire_customer();\n" . $after;
            } else {
                $new_content = $before . "\n// Require customer access only\nrequire_customer();\n\n" . $after;
            }
            
            file_put_contents($file, $new_content);
            echo "Protected: $basename\n";
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "Could not find insertion point: $basename\n";
    }
}

echo "\nDone! Customer pages are now protected.\n";
