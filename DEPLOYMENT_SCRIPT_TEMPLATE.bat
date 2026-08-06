@echo off
REM ============================================================
REM Laravel LAN Deployment Script Template
REM ============================================================
REM This script automates common deployment tasks
REM Customize variables at the top before running
REM ============================================================

setlocal enabledelayedexpansion

REM ============================================================
REM CONFIGURATION - UPDATE THESE VALUES
REM ============================================================
set PROJECT_NAME=UTAMADUNI
set SERVER_IP=192.168.1.100
set PROJECT_PATH=C:\xampp\htdocs\YOUR_PROJECT_PATH
set DB_NAME=your_database_name
set DB_USER=root
set DB_PASS=

REM ============================================================
REM SCRIPT START
REM ============================================================

echo.
echo ============================================================
echo Laravel LAN Deployment Script
echo ============================================================
echo Project: %PROJECT_NAME%
echo Server IP: %SERVER_IP%
echo Project Path: %PROJECT_PATH%
echo ============================================================
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: This script must be run as Administrator
    echo Right-click and select "Run as administrator"
    pause
    exit /b 1
)

REM Change to project directory
cd /d "%PROJECT_PATH%"
if %errorLevel% neq 0 (
    echo ERROR: Cannot access project directory: %PROJECT_PATH%
    pause
    exit /b 1
)

echo [1/8] Checking prerequisites...
echo.

REM Check if XAMPP is installed
if not exist "C:\xampp\apache\bin\httpd.exe" (
    echo ERROR: XAMPP not found at C:\xampp
    pause
    exit /b 1
)
echo [OK] XAMPP found

REM Check if project exists
if not exist "%PROJECT_PATH%\artisan" (
    echo ERROR: Laravel project not found at %PROJECT_PATH%
    pause
    exit /b 1
)
echo [OK] Laravel project found

echo.
echo [2/8] Configuring .env file...
echo.

REM Backup existing .env
if exist ".env" (
    copy ".env" ".env.backup.%date:~-4,4%%date:~-7,2%%date:~-10,2%" >nul
    echo [OK] .env backed up
)

REM Update .env file (requires manual editing or sed/awk)
echo [INFO] Please manually update .env file with:
echo   APP_URL=http://%SERVER_IP%
echo   APP_ENV=production
echo   APP_DEBUG=false
echo   DB_HOST=127.0.0.1
echo   DB_DATABASE=%DB_NAME%
echo   DB_USERNAME=%DB_USER%
echo   DB_PASSWORD=%DB_PASS%
echo.
set /p CONTINUE="Press Enter after updating .env file..."

echo.
echo [3/8] Setting file permissions...
echo.

REM Set storage permissions
icacls "storage" /grant Everyone:(OI)(CI)F /T >nul 2>&1
if %errorLevel% equ 0 (
    echo [OK] Storage permissions set
) else (
    echo [WARNING] Could not set storage permissions automatically
    echo Please set manually: Right-click storage folder ^> Properties ^> Security
)

REM Set bootstrap/cache permissions
icacls "bootstrap\cache" /grant Everyone:(OI)(CI)F /T >nul 2>&1
if %errorLevel% equ 0 (
    echo [OK] Bootstrap cache permissions set
) else (
    echo [WARNING] Could not set bootstrap/cache permissions automatically
)

echo.
echo [4/8] Clearing Laravel cache...
echo.

call php artisan config:clear
if %errorLevel% equ 0 (
    echo [OK] Config cache cleared
) else (
    echo [ERROR] Failed to clear config cache
)

call php artisan cache:clear
if %errorLevel% equ 0 (
    echo [OK] Application cache cleared
) else (
    echo [ERROR] Failed to clear application cache
)

call php artisan route:clear
if %errorLevel% equ 0 (
    echo [OK] Route cache cleared
) else (
    echo [ERROR] Failed to clear route cache
)

call php artisan view:clear
if %errorLevel% equ 0 (
    echo [OK] View cache cleared
) else (
    echo [ERROR] Failed to clear view cache
)

echo.
echo [5/8] Creating storage link...
echo.

call php artisan storage:link
if %errorLevel% equ 0 (
    echo [OK] Storage link created
) else (
    echo [WARNING] Storage link may already exist or failed
)

echo.
echo [6/8] Configuring Windows Firewall...
echo.

REM Check if rule already exists
netsh advfirewall firewall show rule name="Apache HTTP Server" >nul 2>&1
if %errorLevel% equ 0 (
    echo [INFO] Firewall rule already exists
) else (
    netsh advfirewall firewall add rule name="Apache HTTP Server" dir=in action=allow protocol=TCP localport=80 >nul 2>&1
    if %errorLevel% equ 0 (
        echo [OK] Firewall rule added
    ) else (
        echo [ERROR] Failed to add firewall rule
    )
)

echo.
echo [7/8] Verifying Apache configuration...
echo.

echo [INFO] Please verify Apache configuration:
echo   1. Open C:\xampp\apache\conf\httpd.conf
echo   2. Ensure mod_rewrite is enabled
echo   3. Ensure VirtualHosts are enabled
echo   4. Add VirtualHost configuration (see apache-vhost.conf)
echo   5. Restart Apache in XAMPP Control Panel
echo.
set /p CONTINUE="Press Enter after configuring Apache..."

echo.
echo [8/8] Testing deployment...
echo.

REM Test health check endpoint
echo Testing health check endpoint...
curl -s http://%SERVER_IP%/ping >nul 2>&1
if %errorLevel% equ 0 (
    echo [OK] Health check endpoint accessible
) else (
    echo [WARNING] Health check endpoint not accessible
    echo Please verify:
    echo   - Apache is running
    echo   - VirtualHost is configured
    echo   - Firewall allows port 80
)

echo.
echo ============================================================
echo Deployment script completed!
echo ============================================================
echo.
echo NEXT STEPS:
echo 1. Verify Apache VirtualHost configuration
echo 2. Restart Apache in XAMPP Control Panel
echo 3. Test from server: http://%SERVER_IP%
echo 4. Test from client: http://%SERVER_IP%
echo 5. Test health check: http://%SERVER_IP%/ping
echo.
echo For detailed instructions, see: LAN_DEPLOYMENT_GUIDE.md
echo.
pause



