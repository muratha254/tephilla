@echo off
REM ============================================================
REM Receipt Auto-Print Installation Script
REM Laravel POS System - Automatic Installation
REM ============================================================

setlocal enabledelayedexpansion

echo.
echo ============================================================
echo   Receipt Auto-Print Installation Wizard
echo   Laravel POS System
echo ============================================================
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [WARNING] Not running as Administrator
    echo Some features may require admin privileges.
    echo.
    pause
)

REM Get current directory (should be Laravel project root)
set "PROJECT_DIR=%~dp0"
cd /d "%PROJECT_DIR%"

echo [1/6] Checking prerequisites...
echo.

REM Check if Laravel project
if not exist "artisan" (
    echo [ERROR] This doesn't appear to be a Laravel project!
    echo Please run this script from your Laravel project root directory.
    echo.
    pause
    exit /b 1
)
echo [OK] Laravel project detected

REM Check if required files exist
if not exist "app\Http\Controllers\PenjualanController.php" (
    echo [ERROR] PenjualanController.php not found!
    echo Please ensure you're in the correct Laravel project.
    echo.
    pause
    exit /b 1
)
echo [OK] Required controller found

REM Check PHP
where php >nul 2>&1
if %errorLevel% neq 0 (
    echo [WARNING] PHP not found in PATH
    echo Make sure XAMPP PHP is accessible
) else (
    echo [OK] PHP found
)

echo.
echo [2/6] Backing up existing files...
echo.

REM Create backup directory
set "BACKUP_DIR=%PROJECT_DIR%backup_auto_print_%date:~-4,4%%date:~-7,2%%date:~-10,2%_%time:~0,2%%time:~3,2%%time:~6,2%"
set "BACKUP_DIR=%BACKUP_DIR: =0%"
mkdir "%BACKUP_DIR%" 2>nul

REM Backup controller if exists
if exist "app\Http\Controllers\PenjualanController.php" (
    copy "app\Http\Controllers\PenjualanController.php" "%BACKUP_DIR%\PenjualanController.php.backup" >nul 2>&1
    echo [OK] Controller backed up
)

REM Backup routes if exists
if exist "routes\web.php" (
    copy "routes\web.php" "%BACKUP_DIR%\web.php.backup" >nul 2>&1
    echo [OK] Routes backed up
)

echo.
echo [3/6] Installing receipt view...
echo.

REM Create views directory if not exists
if not exist "resources\views\penjualan" (
    mkdir "resources\views\penjualan"
    echo [OK] Created penjualan views directory
)

REM Check if receipt_auto_print.blade.php already exists
if exist "resources\views\penjualan\receipt_auto_print.blade.php" (
    echo [INFO] receipt_auto_print.blade.php already exists
    set /p OVERWRITE="Overwrite existing file? (Y/N): "
    if /i not "!OVERWRITE!"=="Y" (
        echo [SKIP] Keeping existing file
        goto :skip_view
    )
    copy "resources\views\penjualan\receipt_auto_print.blade.php" "%BACKUP_DIR%\receipt_auto_print.blade.php.backup" >nul 2>&1
)

REM Note: The view file should already be created, but we'll verify
if not exist "resources\views\penjualan\receipt_auto_print.blade.php" (
    echo [WARNING] receipt_auto_print.blade.php not found!
    echo Please ensure the file exists in resources\views\penjualan\
    echo.
    set /p CONTINUE="Continue anyway? (Y/N): "
    if /i not "!CONTINUE!"=="Y" (
        exit /b 1
    )
) else (
    echo [OK] Receipt view file found
)

:skip_view

echo.
echo [4/6] Updating controller...
echo.

REM Check if autoPrintReceipt method already exists
findstr /C:"function autoPrintReceipt" "app\Http\Controllers\PenjualanController.php" >nul 2>&1
if %errorLevel% equ 0 (
    echo [INFO] autoPrintReceipt method already exists in controller
    set /p OVERWRITE_CONTROLLER="Update controller anyway? (Y/N): "
    if /i not "!OVERWRITE_CONTROLLER!"=="Y" (
        echo [SKIP] Keeping existing controller
        goto :skip_controller
    )
)

REM Note: Controller modifications should be done manually or via script
REM For now, we'll just verify the route exists
echo [INFO] Controller modifications should be verified manually
echo [INFO] Please check that autoPrintReceipt() method exists

:skip_controller

echo.
echo [5/6] Updating routes...
echo.

REM Check if route already exists
findstr /C:"penjualan.auto_print" "routes\web.php" >nul 2>&1
if %errorLevel% equ 0 (
    echo [INFO] Auto-print route already exists
) else (
    echo [INFO] Adding auto-print route...
    REM Add route (this is a simple append - may need manual adjustment)
    echo. >> "routes\web.php"
    echo         Route::get('/penjualan/{id}/auto-print', [PenjualanController::class, 'autoPrintReceipt'])->name('penjualan.auto_print'); >> "routes\web.php"
    echo [OK] Route added (please verify in routes\web.php)
)

echo.
echo [6/6] Running Laravel setup commands...
echo.

REM Clear cache
echo [INFO] Clearing Laravel cache...
call php artisan config:clear >nul 2>&1
if %errorLevel% equ 0 (
    echo [OK] Config cache cleared
) else (
    echo [WARNING] Could not clear config cache
)

call php artisan route:clear >nul 2>&1
if %errorLevel% equ 0 (
    echo [OK] Route cache cleared
) else (
    echo [WARNING] Could not clear route cache
)

call php artisan view:clear >nul 2>&1
if %errorLevel% equ 0 (
    echo [OK] View cache cleared
) else (
    echo [WARNING] Could not clear view cache
)

echo.
echo ============================================================
echo   Installation Complete!
echo ============================================================
echo.
echo NEXT STEPS:
echo.
echo 1. Verify installation:
echo    - Check routes\web.php for auto-print route
echo    - Check app\Http\Controllers\PenjualanController.php
echo      for autoPrintReceipt() method
echo    - Verify receipt_auto_print.blade.php exists
echo.
echo 2. Test the installation:
echo    - Complete a test sale
echo    - Verify receipt opens and prints automatically
echo.
echo 3. Configure browser:
echo    - Set thermal printer as default
echo    - Allow popups for your POS URL
echo.
echo 4. Backup location: %BACKUP_DIR%
echo.
echo ============================================================
echo.
pause



