Add-Type -AssemblyName System.Drawing

$srcPath = 'C:\xampp\htdocs\printflow\generate_icons_src.png'
$outDir  = 'C:\xampp\htdocs\printflow\public\assets\images'

$src = [System.Drawing.Image]::FromFile($srcPath)
$sizes = @(72, 96, 128, 144, 152, 192, 384, 512)

foreach ($size in $sizes) {
    $bmp = New-Object System.Drawing.Bitmap($size, $size)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $g.DrawImage($src, 0, 0, $size, $size)
    $g.Dispose()
    $outPath = Join-Path $outDir "icon-$size.png"
    $bmp.Save($outPath, [System.Drawing.Imaging.ImageFormat]::Png)
    $bmp.Dispose()
    Write-Host "Created: $outPath"
}

# Also save as favicon
$faviconPath = Join-Path $outDir 'favicon.png'
Copy-Item (Join-Path $outDir 'icon-192.png') $faviconPath -Force
Write-Host "Created: $faviconPath"

$src.Dispose()
Write-Host "All PWA icons generated successfully!"
