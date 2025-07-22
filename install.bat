@echo off
echo Installing Chargily Pay CLI...
echo.

REM Check if composer is installed
composer --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Composer is not installed or not in PATH
    echo Please install Composer first: https://getcomposer.org/download/
    pause
    exit /b 1
)

REM Install the CLI globally
echo Installing via Composer...
composer global require karaodin/chargily-pay-cli

if %errorlevel% neq 0 (
    echo ERROR: Installation failed
    pause
    exit /b 1
)

REM Create a simple wrapper script in current directory
echo @echo off > chargily.bat
echo php "%%APPDATA%%\Composer\vendor\karaodin\chargily-pay-cli\chargily" %%* >> chargily.bat

echo.
echo ✅ Installation complete!
echo.
echo You can now run: chargily
echo.
pause