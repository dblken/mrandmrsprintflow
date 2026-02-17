# Automatic PHP Configuration Fix Script
# This script will fix the Apache PHP handler configuration

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  PrintFlow - PHP Configuration Fix" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host ""
    Write-Host "To run as Administrator:" -ForegroundColor Yellow
    Write-Host "1. Right-click PowerShell" -ForegroundColor Yellow
    Write-Host "2. Select 'Run as Administrator'" -ForegroundColor Yellow
    Write-Host "3. Navigate to: cd C:\xampp\htdocs\printflow" -ForegroundColor Yellow
    Write-Host "4. Run: .\fix_php_config.ps1" -ForegroundColor Yellow
    Write-Host ""
    pause
    exit 1
}

# Paths
$configFile = "C:\xampp\apache\conf\extra\httpd-xampp.conf"
$backupFile = "C:\xampp\apache\conf\extra\httpd-xampp.conf.backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
$xamppControl = "C:\xampp\xampp-control.exe"

Write-Host "Step 1: Backing up configuration..." -ForegroundColor Yellow
Copy-Item $configFile $backupFile
Write-Host "  ✓ Backup created: $backupFile" -ForegroundColor Green
Write-Host ""

Write-Host "Step 2: Reading current configuration..." -ForegroundColor Yellow
$content = Get-Content $configFile -Raw

# Check if the problematic line exists
if ($content -match "AddType text/html \.php") {
    Write-Host "  ✓ Found problematic configuration!" -ForegroundColor Green
    Write-Host "    Current: AddType text/html .php .phps" -ForegroundColor Red
    Write-Host ""
    
    Write-Host "Step 3: Applying fix..." -ForegroundColor Yellow
    
    # Replace the incorrect line
    $content = $content -replace "AddType text/html \.php \.phps", "AddType application/x-httpd-php .php`r`nAddType application/x-httpd-php-source .phps"
    
    # Save the fixed configuration
    $content | Set-Content $configFile -NoNewline
    Write-Host "  ✓ Configuration fixed!" -ForegroundColor Green
    Write-Host "    New: AddType application/x-httpd-php .php" -ForegroundColor Green
    Write-Host ""
} else {
    Write-Host "  ℹ Configuration seems correct already" -ForegroundColor Green
    Write-Host ""
}

Write-Host "Step 4: Restarting Apache..." -ForegroundColor Yellow

# Stop Apache
Write-Host "  Stopping Apache..." -ForegroundColor Gray
$stopResult = & "C:\xampp\apache_stop.bat" 2>&1
Start-Sleep -Seconds 3

# Start Apache
Write-Host "  Starting Apache..." -ForegroundColor Gray
$startResult = & "C:\xampp\apache_start.bat" 2>&1
Start-Sleep -Seconds 3

# Check if Apache is running
$apacheProcess = Get-Process -Name "httpd" -ErrorAction SilentlyContinue

if ($apacheProcess) {
    Write-Host "  ✓ Apache restarted successfully!" -ForegroundColor Green
} else {
    Write-Host "  ⚠ Apache may not have started. Please check XAMPP Control Panel!" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Fix Applied Successfully!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Clear your browser cache (Ctrl+Shift+Delete)" -ForegroundColor White
Write-Host "2. Visit: http://localhost/printflow/" -ForegroundColor White
Write-Host "3. You should now see the beautiful landing page!" -ForegroundColor White
Write-Host ""
Write-Host "If you still see raw PHP code:" -ForegroundColor Yellow
Write-Host "- Try a different browser" -ForegroundColor White
Write-Host "- Check Apache error log: C:\xampp\apache\logs\error.log" -ForegroundColor White
Write-Host "- Restore backup: Copy $backupFile to $configFile" -ForegroundColor White
Write-Host ""

pause
