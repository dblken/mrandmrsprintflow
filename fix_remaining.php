<?php
$files = [
    'staff/customizations.php',
    'staff/job_orders_management.php',
    'staff/orders.php',
    'staff/reviews.php',
    'customer/notifications.php',
    'customer/order_dynamic.php',
    'includes/push_helper.php',
    'public/404.php',
    'public/api/chat/fetch_media.php',
    'public/api/chat/fetch_messages.php',
    'public/api/chat/order_details.php',
    'public/api/header_search.php',
    'public/api_login.php',
];

$fixed = 0;

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) continue;
    
    $content = file_get_contents($path);
    $original = $content;
    
    // Fix double slashes: //printflow/ -> /
    $content = str_replace('//printflow/', '/', $content);
    
    // Fix Alpine.js :href with /printflow/
    $content = preg_replace('~:href="[\'"]/printflow/~', ':href="\' + (window.PFConfig?.basePath || \'\') + \'/', $content);
    
    // Fix remaining /printflow/ in strings
    $content = preg_replace('~(["\'])/printflow/~', '$1<?php echo $base_path; ?>/', $content);
    
    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "✓ $file\n";
        $fixed++;
    }
}

echo "\nFixed: $fixed files\n";
