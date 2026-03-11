<?php
echo "Current directory: " . __DIR__ . "\n";
$target = __DIR__ . '/../includes/header.php';
echo "Target path: " . $target . "\n";
echo "Exists: " . (file_exists($target) ? "YES" : "NO") . "\n";
echo "Is file: " . (is_file($target) ? "YES" : "NO") . "\n";
echo "Real path: " . realpath($target) . "\n";

echo "\nListing includes directory:\n";
$incl = __DIR__ . '/../includes';
if (is_dir($incl)) {
    $files = scandir($incl);
    foreach ($files as $f) {
        if ($f === 'header.php') {
            echo "FOUND: [$f]\n";
        } elseif (stripos($f, 'header') !== false) {
            echo "PARTIAL MATCH: [$f]\n";
        }
    }
} else {
    echo "Includes directory NOT found at $incl\n";
}
?>
