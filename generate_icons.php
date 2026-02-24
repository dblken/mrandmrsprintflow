<?php
// One-time script: generate all PWA icon sizes
$srcPath = __DIR__ . '/generate_icons_src.png';
$outDir  = __DIR__ . '/public/assets/images/';

$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

if (!file_exists($srcPath)) {
    die("Source image not found: $srcPath\n");
}

$src = imagecreatefrompng($srcPath);
if (!$src) die("Failed to load source PNG\n");

$sw = imagesx($src);
$sh = imagesy($src);

foreach ($sizes as $size) {
    $dst = imagecreatetruecolor($size, $size);
    // Preserve transparency
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $size, $size, $transparent);
    imagealphablending($dst, true);

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, $sw, $sh);

    $outFile = $outDir . "icon-{$size}.png";
    imagepng($dst, $outFile, 9);
    imagedestroy($dst);
    echo "Created: $outFile\n";
}

// Also copy 192 as favicon and apple-touch-icon
copy($outDir . 'icon-192.png', $outDir . 'favicon.png');
echo "Created: {$outDir}favicon.png\n";

imagedestroy($src);
echo "\nAll icons generated successfully!\n";
