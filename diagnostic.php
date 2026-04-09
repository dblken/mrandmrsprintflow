<?php
/**
 * Diagnostic Test for job_orders_api.php
 * Upload this to your server to see what's causing the 500 error
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== PrintFlow Diagnostic Test ===\n\n";

// Test 1: Check if config.php exists and loads
echo "1. Testing config.php...\n";
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    echo "   ✓ config.php loaded\n";
    echo "   BASE_PATH: " . (defined('BASE_PATH') ? BASE_PATH : 'NOT DEFINED') . "\n";
    echo "   BASE_URL: " . (defined('BASE_URL') ? BASE_URL : 'NOT DEFINED') . "\n";
} else {
    echo "   ✗ config.php NOT FOUND\n";
}

// Test 2: Check if includes/auth.php exists
echo "\n2. Testing includes/auth.php...\n";
if (file_exists(__DIR__ . '/includes/auth.php')) {
    echo "   ✓ includes/auth.php exists\n";
    try {
        require_once __DIR__ . '/includes/auth.php';
        echo "   ✓ includes/auth.php loaded successfully\n";
    } catch (Throwable $e) {
        echo "   ✗ Error loading auth.php: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ includes/auth.php NOT FOUND\n";
}

// Test 3: Check if includes/functions.php exists
echo "\n3. Testing includes/functions.php...\n";
if (file_exists(__DIR__ . '/includes/functions.php')) {
    echo "   ✓ includes/functions.php exists\n";
    try {
        require_once __DIR__ . '/includes/functions.php';
        echo "   ✓ includes/functions.php loaded successfully\n";
        
        // Test the functions we modified
        if (function_exists('get_services_image_map')) {
            echo "   ✓ get_services_image_map() exists\n";
            $map = get_services_image_map();
            echo "   Sample image path: " . ($map['tarpaulin'] ?? 'ERROR') . "\n";
        }
    } catch (Throwable $e) {
        echo "   ✗ Error loading functions.php: " . $e->getMessage() . "\n";
        echo "   Stack trace: " . $e->getTraceAsString() . "\n";
    }
} else {
    echo "   ✗ includes/functions.php NOT FOUND\n";
}

// Test 4: Check if admin/job_orders_api.php exists
echo "\n4. Testing admin/job_orders_api.php...\n";
if (file_exists(__DIR__ . '/admin/job_orders_api.php')) {
    echo "   ✓ admin/job_orders_api.php exists\n";
} else {
    echo "   ✗ admin/job_orders_api.php NOT FOUND\n";
}

// Test 5: Check PHP version
echo "\n5. PHP Environment:\n";
echo "   PHP Version: " . PHP_VERSION . "\n";
echo "   Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";

echo "\n=== End of Diagnostic Test ===\n";
