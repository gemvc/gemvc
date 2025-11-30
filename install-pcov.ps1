# PCOV Installation Script for Windows/XAMPP
# PHP 8.2 ZTS (Thread Safe)

Write-Host "PCOV Installation Script for PHP 8.2 ZTS" -ForegroundColor Green
Write-Host "=========================================" -ForegroundColor Green
Write-Host ""

# Check PHP version
$phpVersion = php -r "echo PHP_VERSION;"
Write-Host "Detected PHP Version: $phpVersion" -ForegroundColor Cyan

# Get extension directory
$extDir = php -r "echo ini_get('extension_dir');"
Write-Host "Extension Directory: $extDir" -ForegroundColor Cyan
Write-Host ""

# Check if PCOV is already installed
$pcovInstalled = php -m | Select-String -Pattern "pcov"
if ($pcovInstalled) {
    Write-Host "PCOV is already installed!" -ForegroundColor Green
    php -m | Select-String -Pattern "pcov"
    exit 0
}

Write-Host "PCOV is not installed. Installation steps:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Download PCOV DLL:" -ForegroundColor Yellow
Write-Host "   Visit: https://windows.php.net/downloads/pecl/releases/pcov/" -ForegroundColor Cyan
Write-Host "   Look for: php_pcov-X.X.X-8.2-ts-vs16-x64.zip" -ForegroundColor Cyan
Write-Host "   (X.X.X = latest version, ts = Thread Safe)" -ForegroundColor Gray
Write-Host ""
Write-Host "2. Extract and copy php_pcov.dll to:" -ForegroundColor Yellow
Write-Host "   $extDir" -ForegroundColor Cyan
Write-Host ""
Write-Host "3. Edit php.ini (C:\xampp\php\php.ini) and add:" -ForegroundColor Yellow
Write-Host "   extension=pcov" -ForegroundColor Cyan
Write-Host "   pcov.enabled=1" -ForegroundColor Cyan
Write-Host "   pcov.directory=." -ForegroundColor Cyan
Write-Host ""
Write-Host "4. Restart your web server (if running)" -ForegroundColor Yellow
Write-Host ""
Write-Host "5. Verify installation:" -ForegroundColor Yellow
Write-Host "   php -m | findstr pcov" -ForegroundColor Cyan
Write-Host ""

# Try to open the download page
$response = Read-Host "Would you like to open the download page? (y/n)"
if ($response -eq 'y' -or $response -eq 'Y') {
    Start-Process "https://windows.php.net/downloads/pecl/releases/pcov/"
}

Write-Host ""
Write-Host "After installation, run this script again to verify." -ForegroundColor Green

