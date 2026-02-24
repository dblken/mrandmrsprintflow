<?php
$files = glob(__DIR__ . '/public/assets/css/*.css');
$replacements = [
    '/#4f46e5/i' => '#53c5e0',
    '/#3f38b7/i' => '#32a1c4',
    '/#2f2a89/i' => '#266986',
    '/#100e2e/i' => '#00232b',
    '/#a855f7/i' => '#32a1c4',
    '/rgba\(79,\s*70,\s*229/i' => 'rgba(83, 197, 224'
];

foreach ($files as $file) {
    if (is_file($file)) {
        $content = file_get_contents($file);
        $content = preg_replace(array_keys($replacements), array_values($replacements), $content);
        file_put_contents($file, $content);
        echo "Replaced in " . basename($file) . "\n";
    }
}
echo "Done.\n";
