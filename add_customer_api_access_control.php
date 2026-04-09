<?php
/**
 * Script to add require_customer() to customer API files
 */

$customer_dir = __DIR__ . '/customer';
$api_files = [
    'api_address.php',
    'api_add_to_cart_reflectorized.php',
    'api_add_to_cart_souvenirs.php',
    'api_cart.php',
    'api_customer_orders.php',
    'api_profile.php',
    'api_reflectorized_order.php',
    'api_submit_payment.php',
    'api_track.php',
    'customer_order_api.php'
];

foreach ($api_files as $filename) {
    $file = $customer_dir . '/' . $filename;
    
    if (!file_exists($file)) {
        echo "File not found: $filename\n";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check if already has require_customer()
    if (strpos($content, 'require_customer()') !== false) {
        echo "Already protected: $filename\n";
        continue;
    }
    
    // Check if it has auth.php include
    if (strpos($content, "require_once __DIR__ . '/../includes/auth.php'") === false) {
        // Add auth.php include at the beginning after <?php
        $content = preg_replace(
            '/^<\?php\s*\n/',
            "<?php\nrequire_once __DIR__ . '/../includes/auth.php';\nrequire_customer();\n\n",
            $content,
            1
        );
        echo "Added auth include and protection: $filename\n";
    } else {
        // Find position after auth.php include
        if (preg_match("/require_once __DIR__ \. '\/\.\.\/includes\/auth\.php';/", $content, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);
            
            // Find the end of the line
            $newline_pos = strpos($content, "\n", $pos);
            if ($newline_pos !== false) {
                $pos = $newline_pos + 1;
            }
            
            // Insert require_customer()
            $before = substr($content, 0, $pos);
            $after = substr($content, $pos);
            $content = $before . "require_customer();\n" . $after;
            
            echo "Protected: $filename\n";
        } else {
            echo "Could not find insertion point: $filename\n";
            continue;
        }
    }
    
    file_put_contents($file, $content);
}

echo "\nDone! Customer API files are now protected.\n";
