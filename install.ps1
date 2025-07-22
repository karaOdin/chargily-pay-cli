Write-Host "Installing Chargily Pay CLI..." -ForegroundColor Green
Write-Host

# Check if composer is installed
try {
    $null = Get-Command composer -ErrorAction Stop
    Write-Host "✓ Composer found" -ForegroundColor Green
} catch {
    Write-Host "ERROR: Composer is not installed or not in PATH" -ForegroundColor Red
    Write-Host "Please install Composer first: https://getcomposer.org/download/" -ForegroundColor Yellow
    Read-Host "Press Enter to exit"
    exit 1
}

# Install the CLI globally
Write-Host "Installing via Composer..." -ForegroundColor Yellow
& composer global require karaodin/chargily-pay-cli

if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Installation failed" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

# Create a simple wrapper script in current directory
$wrapperContent = @"
@echo off
php "%APPDATA%\Composer\vendor\karaodin\chargily-pay-cli\chargily" %*
"@

$wrapperContent | Out-File -FilePath "chargily.bat" -Encoding ASCII

Write-Host
Write-Host "✅ Installation complete!" -ForegroundColor Green
Write-Host
Write-Host "You can now run: .\chargily.bat" -ForegroundColor Cyan
Write-Host "Or just: chargily.bat" -ForegroundColor Cyan
Write-Host
Read-Host "Press Enter to continue"