# Start Apache on Port 8000 Script
# Run this script to configure and start Apache for port 8000

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Apache Port 8000 Configuration" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$projectPath = "C:/xampp/htdocs/UTAMADUNI NEW SYSTEM/UPDATED UTAMADUNI"
$httpdConf = "C:\xampp\apache\conf\httpd.conf"
$vhostsConf = "C:\xampp\apache\conf\extra\httpd-vhosts.conf"

# Check if XAMPP exists
if (-not (Test-Path "C:\xampp")) {
    Write-Host "ERROR: XAMPP not found at C:\xampp" -ForegroundColor Red
    Write-Host "Please install XAMPP or update the path in this script" -ForegroundColor Yellow
    exit 1
}

Write-Host "Step 1: Checking Apache configuration..." -ForegroundColor Yellow

# Check if Listen 8000 exists in httpd.conf
$httpdContent = Get-Content $httpdConf -Raw
if ($httpdContent -notmatch "Listen\s+8000") {
    Write-Host "  → Adding Listen 8000 to httpd.conf..." -ForegroundColor White
    
    # Find Listen directive and add 8000
    if ($httpdContent -match "Listen\s+80") {
        $httpdContent = $httpdContent -replace "(Listen\s+80)", "`$1`nListen 8000"
        Set-Content -Path $httpdConf -Value $httpdContent -NoNewline
        Write-Host "  ✓ Added Listen 8000" -ForegroundColor Green
    } else {
        Write-Host "  ⚠ Could not find Listen directive. Please add 'Listen 8000' manually" -ForegroundColor Yellow
    }
} else {
    Write-Host "  ✓ Listen 8000 already configured" -ForegroundColor Green
}

Write-Host ""
Write-Host "Step 2: Checking VirtualHost configuration..." -ForegroundColor Yellow

# Check if VirtualHost for 8000 exists
if (Test-Path $vhostsConf) {
    $vhostsContent = Get-Content $vhostsConf -Raw
    
    if ($vhostsContent -notmatch "<VirtualHost\s+\*:8000>") {
        Write-Host "  → Adding VirtualHost for port 8000..." -ForegroundColor White
        
        $newVhost = @"

# VirtualHost for Port 8000 - Added automatically
<VirtualHost *:8000>
    ServerName 127.0.0.1:8000
    DocumentRoot "$projectPath/public"
    
    <Directory "$projectPath/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        RewriteEngine On
    </Directory>
    
    <FilesMatch \.php$>
        SetHandler application/x-httpd-php
    </FilesMatch>
    
    ErrorLog "C:/xampp/apache/logs/utamaduni_8000_error.log"
    CustomLog "C:/xampp/apache/logs/utamaduni_8000_access.log" common
</VirtualHost>

"@
        
        Add-Content -Path $vhostsConf -Value $newVhost
        Write-Host "  ✓ Added VirtualHost for port 8000" -ForegroundColor Green
    } else {
        Write-Host "  ✓ VirtualHost for port 8000 already exists" -ForegroundColor Green
    }
} else {
    Write-Host "  ✗ httpd-vhosts.conf not found" -ForegroundColor Red
    Write-Host "  → Creating file..." -ForegroundColor Yellow
    
    $newVhost = @"
# VirtualHost for Port 8000
<VirtualHost *:8000>
    ServerName 127.0.0.1:8000
    DocumentRoot "$projectPath/public"
    
    <Directory "$projectPath/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        RewriteEngine On
    </Directory>
    
    <FilesMatch \.php$>
        SetHandler application/x-httpd-php
    </FilesMatch>
</VirtualHost>
"@
    
    Set-Content -Path $vhostsConf -Value $newVhost
    Write-Host "  ✓ Created httpd-vhosts.conf" -ForegroundColor Green
}

Write-Host ""
Write-Host "Step 3: Instructions to start Apache" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Open XAMPP Control Panel" -ForegroundColor White
Write-Host "2. Click 'Start' next to Apache" -ForegroundColor White
Write-Host "3. Wait for Apache to show 'Running' (green)" -ForegroundColor White
Write-Host "4. Access: http://127.0.0.1:8000" -ForegroundColor White
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Configuration complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan



