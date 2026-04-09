@echo off
echo Fixing all invalid PHP template strings...
echo.

REM Fix all files with the pattern '<?php echo $base_path; ?>//printflow/
powershell -Command "$files = Get-ChildItem -Path 'c:\Users\user\Documents\mrandmrsprintflow' -Include '*.php' -Recurse | Where-Object { $_.FullName -notmatch 'backups' -and $_.FullName -notmatch 'vendor' }; $count = 0; foreach ($file in $files) { $content = Get-Content $file.FullName -Raw; if ($content -match \"'<\?php echo \$base_path; \?>//printflow/\") { $newContent = $content -replace \"'<\?php echo \$base_path; \?>//printflow/\", \"'<?php echo BASE_PATH; ?>/\"; Set-Content -Path $file.FullName -Value $newContent -NoNewline; Write-Host \"Fixed: $($file.Name)\"; $count++ } }; Write-Host \"`nTotal files fixed: $count\""

echo.
echo Done! All invalid PHP template strings have been fixed.
pause
