<?php
// Diagnostic Script - Check PHP Configuration
header('Content-Type: text/plain');

echo "==============================================\n";
echo "        PHP DIAGNOSTIC REPORT\n";
echo "==============================================\n\n";

echo "1. PHP VERSION\n";
echo "   " . phpversion() . "\n\n";

echo "2. SERVER SOFTWARE\n";
echo "   " . $_SERVER['SERVER_SOFTWARE'] . "\n\n";

echo "3. PHP SAPI\n";
echo "   " . php_sapi_name() . "\n\n";

echo "4. CURRENT FILE PATH\n";
echo "   " . __FILE__ . "\n\n";

echo "5. DOCUMENT ROOT\n";
echo "   " . $_SERVER['DOCUMENT_ROOT'] . "\n\n";

echo "6. REQUEST URI\n";
echo "   " . $_SERVER['REQUEST_URI'] . "\n\n";

echo "7. LOADED PHP.INI\n";
echo "   " . php_ini_loaded_file() . "\n\n";

echo "8. PHP EXTENSIONS (Loaded)\n";
$extensions = get_loaded_extensions();
foreach ($extensions as $ext) {
    echo "   - $ext\n";
}

echo "\n9. APACHE MODULES (If Available)\n";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    foreach ($modules as $module) {
        echo "   - $module\n";
    }
} else {
    echo "   apache_get_modules() not available\n";
}

echo "\n10. CONTENT TYPE HEADERS\n";
foreach (headers_list() as $header) {
    echo "   $header\n";
}

echo "\n==============================================\n";
echo "If you can read this, PHP IS WORKING!\n";
echo "==============================================\n";
?>
