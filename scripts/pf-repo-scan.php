<?php
/**
 * PrintFlow — quick repo health scan (CLI).
 * Usage: php scripts/pf-repo-scan.php [--markers-only]
 *
 * Checks: unresolved git merge markers, PHP syntax on *.php (excludes vendor/node_modules).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = 0;
$markersOnly = in_array('--markers-only', $argv ?? [], true);

echo "PrintFlow repo scan: {$root}\n\n";

// 1) Merge conflict markers (git) — build strings so this file does not contain the raw markers
$mStart = str_repeat('<', 7) . ' ';
$mEnd   = str_repeat('>', 7) . ' ';

$ri = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($ri as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
        || str_contains($path, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR)) {
        continue;
    }
    $ext = strtolower($file->getExtension());
    if (!in_array($ext, ['php', 'js', 'css', 'html', 'htm', 'sql', 'md', 'txt', 'json'], true)) {
        continue;
    }
    $c = @file_get_contents($path);
    if ($c === false) {
        continue;
    }
    if (str_contains($c, $mStart) || str_contains($c, $mEnd)) {
        fwrite(STDERR, "MERGE MARKERS: {$path}\n");
        $errors++;
    }
}

if (!$markersOnly) {
    $phpFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($phpFiles as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
            || str_contains($path, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR)) {
            continue;
        }
        $out = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            fwrite(STDERR, implode("\n", $out) . "\n");
            $errors++;
        }
    }
}

if ($errors === 0) {
    if ($markersOnly) {
        echo "OK — no merge markers found.\n";
    } else {
        echo "OK — no merge markers found; all PHP files pass php -l.\n";
    }
    exit(0);
}

echo "\nDone with {$errors} issue(s).\n";
exit(1);
