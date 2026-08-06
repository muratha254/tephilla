@echo off
REM Laravel scheduler entry point for Windows Task Scheduler.
REM Task must run schedule:run EVERY MINUTE (not every 5 min). "At startup only" is not enough.
REM Triggers: Daily + "Repeat task every: 1 minute" indefinitely — see Backup settings page in the app.
cd /d "%~dp0"

REM Prefer PHP on PATH; fall back to common XAMPP install.
where php >nul 2>&1
if %ERRORLEVEL% equ 0 (
    php artisan schedule:run >> "storage\logs\scheduler.log" 2>&1
    exit /b %ERRORLEVEL%
)

if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" artisan schedule:run >> "storage\logs\scheduler.log" 2>&1
    exit /b %ERRORLEVEL%
)

echo schedule-run.bat: php.exe not found. Add PHP to PATH or edit this script. >> "storage\logs\scheduler.log"
exit /b 1
