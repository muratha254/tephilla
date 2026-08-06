# Apache Connection Fix Script
# Run this script as Administrator

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Apache Connection Fix Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    exit 1
}

# Step 1: Check what's listening on port 80
Write-Host "Step 1: Checking what's listening on port 80..." -ForegroundColor Yellow
$port80 = netstat -an | Select-String ":80" | Select-String "LISTENING"
if ($port80) {
    Write-Host "Found processes on port 80:" -ForegroundColor Green
    $port80 | ForEach-Object { Write-Host "  $_" -ForegroundColor White }
    
    # Check if it's listening on all interfaces
    if ($port80 -match "0\.0\.0\.0:80") {
        Write-Host "  ✓ Port 80 is listening on all interfaces (0.0.0.0:80)" -ForegroundColor Green
    } elseif ($port80 -match "127\.0\.0\.1:80") {
        Write-Host "  ✗ Port 80 is only listening on localhost (127.0.0.1:80)" -ForegroundColor Red
        Write-Host "  → You need to change httpd.conf: Listen *:80" -ForegroundColor Yellow
    }
} else {
    Write-Host "  ✗ Nothing is listening on port 80" -ForegroundColor Red
    Write-Host "  → Apache may not be running or configured incorrectly" -ForegroundColor Yellow
}
Write-Host ""

# Step 2: Check firewall rules
Write-Host "Step 2: Checking Windows Firewall rules..." -ForegroundColor Yellow
$firewallRule = netsh advfirewall firewall show rule name="Apache HTTP Server" 2>$null
if ($firewallRule) {
    Write-Host "  ✓ Firewall rule 'Apache HTTP Server' exists" -ForegroundColor Green
} else {
    Write-Host "  ✗ Firewall rule not found" -ForegroundColor Red
    Write-Host "  → Adding firewall rule..." -ForegroundColor Yellow
    netsh advfirewall firewall add rule name="Apache HTTP Server" dir=in action=allow protocol=TCP localport=80
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  ✓ Firewall rule added successfully" -ForegroundColor Green
    } else {
        Write-Host "  ✗ Failed to add firewall rule" -ForegroundColor Red
    }
}
Write-Host ""

# Step 3: Check Apache configuration file location
Write-Host "Step 3: Checking Apache configuration..." -ForegroundColor Yellow
$httpdConf = "C:\xampp\apache\conf\httpd.conf"
if (Test-Path $httpdConf) {
    Write-Host "  ✓ Found httpd.conf at: $httpdConf" -ForegroundColor Green
    
    # Check Listen directive
    $listenLine = Select-String -Path $httpdConf -Pattern "^Listen" | Select-Object -First 1
    if ($listenLine) {
        Write-Host "  Current Listen directive: $($listenLine.Line)" -ForegroundColor White
        if ($listenLine.Line -match "Listen\s+\*:80" -or $listenLine.Line -match "Listen\s+192\.168\.1\.118:80") {
            Write-Host "  ✓ Listen directive is correct" -ForegroundColor Green
        } else {
            Write-Host "  ✗ Listen directive needs to be changed" -ForegroundColor Red
            Write-Host "  → Change to: Listen *:80" -ForegroundColor Yellow
            Write-Host "  → Or: Listen 192.168.1.118:80" -ForegroundColor Yellow
        }
    }
    
    # Check if VirtualHosts is included
    $vhostsInclude = Select-String -Path $httpdConf -Pattern "^Include.*httpd-vhosts\.conf"
    if ($vhostsInclude) {
        Write-Host "  ✓ VirtualHosts file is included" -ForegroundColor Green
    } else {
        Write-Host "  ✗ VirtualHosts file is NOT included" -ForegroundColor Red
        Write-Host "  → Uncomment: Include conf/extra/httpd-vhosts.conf" -ForegroundColor Yellow
    }
} else {
    Write-Host "  ✗ httpd.conf not found at: $httpdConf" -ForegroundColor Red
    Write-Host "  → Please check your XAMPP installation path" -ForegroundColor Yellow
}
Write-Host ""

# Step 4: Check VirtualHosts file
Write-Host "Step 4: Checking VirtualHosts configuration..." -ForegroundColor Yellow
$vhostsConf = "C:\xampp\apache\conf\extra\httpd-vhosts.conf"
if (Test-Path $vhostsConf) {
    Write-Host "  ✓ Found httpd-vhosts.conf" -ForegroundColor Green
    
    # Check if ServerName matches
    $serverName = Select-String -Path $vhostsConf -Pattern "ServerName\s+192\.168\.1\.118"
    if ($serverName) {
        Write-Host "  ✓ ServerName is set to 192.168.1.118" -ForegroundColor Green
    } else {
        Write-Host "  ✗ ServerName is not set to 192.168.1.118" -ForegroundColor Red
        Write-Host "  → Update ServerName in VirtualHost configuration" -ForegroundColor Yellow
    }
} else {
    Write-Host "  ✗ httpd-vhosts.conf not found" -ForegroundColor Red
    Write-Host "  → Copy content from apache-vhost.conf to httpd-vhosts.conf" -ForegroundColor Yellow
}
Write-Host ""

# Summary
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Summary and Next Steps" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "To fix the connection issue:" -ForegroundColor Yellow
Write-Host "1. Open C:\xampp\apache\conf\httpd.conf" -ForegroundColor White
Write-Host "2. Find 'Listen' directive and change to: Listen *:80" -ForegroundColor White
Write-Host "3. Make sure this line is NOT commented: Include conf/extra/httpd-vhosts.conf" -ForegroundColor White
Write-Host "4. Copy your apache-vhost.conf content to C:\xampp\apache\conf\extra\httpd-vhosts.conf" -ForegroundColor White
Write-Host "5. Restart Apache in XAMPP Control Panel" -ForegroundColor White
Write-Host "6. Test: http://192.168.1.118" -ForegroundColor White
Write-Host ""





