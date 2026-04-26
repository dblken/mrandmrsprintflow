<?php
/**
 * Bulk fix PHP 8 compatibility issues (match, str_contains, etc.)
 */
$root = __DIR__ . '/..';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$fixedCount = 0;

foreach ($files as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;
    if (strpos($file->getPathname(), 'vendor') !== false) continue;
    if (strpos($file->getPathname(), '.gemini') !== false) continue;

    $content = file_get_contents($file->getPathname());
    $original = $content;

    // 1. Replace (strpos($h, $n) !== false) with (strpos($h, $n) !== false)
    $content = preg_replace('/str_contains\s*\(\s*(.+?)\s*,\s*(.+?)\s*\)/', '(strpos($1, $2) !== false)', $content);

    // 2. Replace (strncmp($h, $n, strlen($n)) === 0) with (strncmp($h, $n, strlen($n)) === 0)
    // Simplified regex, might need more care for nested calls
    $content = preg_replace('/str_starts_with\s*\(\s*(.+?)\s*,\s*(.+?)\s*\)/', '(strncmp($1, $2, strlen($2)) === 0)', $content);

    // 3. Replace (substr($h, -strlen($n)) === $n) with (substr($h, -strlen($n)) === $n)
    $content = preg_replace('/str_ends_with\s*\(\s*(.+?)\s*,\s*(.+?)\s*\)/', '(substr($1, -strlen($2)) === $2)', $content);

    if ($content !== $original) {
        file_put_contents($file->getPathname(), $content);
        echo "Fixed: " . $file->getPathname() . "\n";
        $fixedCount++;
    }
}

echo "Total files fixed: $fixedCount\n";
?>
