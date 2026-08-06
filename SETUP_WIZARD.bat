@echo off
REM ============================================================
REM Receipt Auto-Print Setup Wizard
REM One-Click Installation for Windows
REM ============================================================

setlocal enabledelayedexpansion

title Receipt Auto-Print Setup Wizard

echo.
echo ============================================================
echo   Receipt Auto-Print Setup Wizard
echo   Laravel POS System
echo ============================================================
echo.
echo This wizard will install the receipt auto-print feature.
echo.
pause

REM Check if in Laravel project
if not exist "artisan" (
    echo.
    echo [ERROR] This doesn't appear to be a Laravel project!
    echo Please run this script from your Laravel project root.
    echo.
    pause
    exit /b 1
)

echo.
echo [1/4] Running installation script...
echo.

call install_auto_print.bat

echo.
echo [2/4] Verifying installation...
echo.

php verify_installation.php

echo.
echo [3/4] Installation complete!
echo.

echo.
echo [4/4] Next Steps:
echo.
echo 1. Configure your browser:
echo    - Set thermal printer as default
echo    - Allow popups for your POS URL
echo.
echo 2. Test the installation:
echo    - Complete a test sale
echo    - Verify receipt prints automatically
echo.
echo 3. Review documentation:
echo    - INSTALLATION_GUIDE.md
echo    - RECEIPT_AUTO_PRINT_GUIDE.md
echo.
echo ============================================================
echo   Setup Complete!
echo ============================================================
echo.
pause



