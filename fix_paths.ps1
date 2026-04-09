$files = @(
    "includes\customer_service_catalog.php",
    "includes\footer.php",
    "includes\JobOrderService.php",
    "includes\logout_modal.php",
    "includes\manager_sidebar.php",
    "includes\nav-header.php",
    "includes\order_chat.php",
    "includes\order_ui_helper.php",
    "public\complete_profile.php",
    "public\google_auth.php",
    "public\verify_email.php"
)

$basePath = "c:\Users\user\Documents\mrandmrsprintflow"
$count = 0

foreach ($file in $files) {
    $fullPath = Join-Path $basePath $file
    if (Test-Path $fullPath) {
        $content = Get-Content $fullPath -Raw
        $original = $content
        
        # Replace invalid PHP template strings
        $content = $content -replace "'<\?php echo \`$base_path; \?>//printflow/public/", "BASE_PATH . '/public/"
        $content = $content -replace "'<\?php echo \`$base_path; \?>//printflow/", "BASE_PATH . '/"
        $content = $content -replace "<\?php echo \`$base_path; \?>//printflow/public/", "<?php echo BASE_PATH; ?>/public/"
        $content = $content -replace "<\?php echo \`$base_path; \?>//printflow/", "<?php echo BASE_PATH; ?>/"
        $content = $content -replace "`/printflow/public/", "<?php echo BASE_PATH; ?>/public/"
        
        if ($content -ne $original) {
            Set-Content -Path $fullPath -Value $content -NoNewline
            Write-Host "Fixed: $file"
            $count++
        }
    }
}

Write-Host "`nTotal files fixed: $count"
