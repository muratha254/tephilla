# MySQL Status Check Script

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "MySQL Status Check" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if MySQL port is listening
Write-Host "Checking MySQL port 3306..." -ForegroundColor Yellow
$mysqlPort = netstat -an | Select-String ":3306" | Select-String "LISTENING"

if ($mysqlPort) {
    Write-Host "  ✓ MySQL is RUNNING on port 3306" -ForegroundColor Green
    Write-Host "  Connection: $mysqlPort" -ForegroundColor White
} else {
    Write-Host "  ✗ MySQL is NOT running" -ForegroundColor Red
    Write-Host ""
    Write-Host "SOLUTION:" -ForegroundColor Yellow
    Write-Host "1. Open XAMPP Control Panel" -ForegroundColor White
    Write-Host "2. Click 'Start' next to MySQL" -ForegroundColor White
    Write-Host "3. Wait for it to show 'Running' (green)" -ForegroundColor White
}

Write-Host ""

# Check for MySQL processes
Write-Host "Checking MySQL processes..." -ForegroundColor Yellow
$mysqlProcesses = Get-Process | Where-Object {$_.ProcessName -like "*mysql*" -or $_.ProcessName -like "*mariadb*"}

if ($mysqlProcesses) {
    Write-Host "  ✓ Found MySQL processes:" -ForegroundColor Green
    $mysqlProcesses | ForEach-Object {
        Write-Host "    - $($_.ProcessName) (PID: $($_.Id))" -ForegroundColor White
    }
} else {
    Write-Host "  ✗ No MySQL processes found" -ForegroundColor Red
    Write-Host "  → MySQL is not running" -ForegroundColor Yellow
}

Write-Host ""

# Test database connection
Write-Host "Testing database connection..." -ForegroundColor Yellow
if (Test-Path "test-db-connection.php") {
    $result = php test-db-connection.php 2>&1
    if ($result -match "SUCCESS") {
        Write-Host "  ✓ Database connection successful!" -ForegroundColor Green
    } else {
        Write-Host "  ✗ Database connection failed" -ForegroundColor Red
        Write-Host ""
        Write-Host "  Error details:" -ForegroundColor Yellow
        $result | Select-String "ERROR" | ForEach-Object { Write-Host "    $_" -ForegroundColor Red }
    }
} else {
    Write-Host "  ⚠ test-db-connection.php not found" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Next Steps:" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "If MySQL is not running:" -ForegroundColor Yellow
Write-Host "1. Open XAMPP Control Panel" -ForegroundColor White
Write-Host "2. Start MySQL service" -ForegroundColor White
Write-Host "3. Run this script again to verify" -ForegroundColor White
Write-Host ""
Write-Host "If MySQL is running but connection fails:" -ForegroundColor Yellow
Write-Host "1. Check .env file database credentials" -ForegroundColor White
Write-Host "2. Verify database exists in phpMyAdmin" -ForegroundColor White
Write-Host "3. Check MySQL error logs" -ForegroundColor White
Write-Host ""



