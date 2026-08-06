# Fix Apache Not Found - Create Symbolic Link
# Run this script as Administrator

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Fix XAMPP Apache Not Found" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    exit 1
}

$xamppPath = "C:\xampp"
$apachePath = "$xamppPath\apache"
$apacheTarget = "$xamppPath\apache8.3"  # Using latest version

# Check if XAMPP exists
if (-not (Test-Path $xamppPath)) {
    Write-Host "ERROR: XAMPP not found at $xamppPath" -ForegroundColor Red
    exit 1
}

Write-Host "Step 1: Checking Apache versions..." -ForegroundColor Yellow

# Check which Apache versions exist
$apacheVersions = @("apache8.3", "apache8.1", "apache7.3", "apache7.2", "apache7.1")
$foundVersion = $null

foreach ($version in $apacheVersions) {
    $versionPath = "$xamppPath\$version"
    if (Test-Path "$versionPath\bin\httpd.exe") {
        $foundVersion = $version
        Write-Host "  ✓ Found: $version" -ForegroundColor Green
        break
    }
}

if (-not $foundVersion) {
    Write-Host "  ✗ No Apache version found!" -ForegroundColor Red
    exit 1
}

$apacheTarget = "$xamppPath\$foundVersion"
Write-Host "  → Using: $foundVersion" -ForegroundColor White

Write-Host ""
Write-Host "Step 2: Checking existing apache directory..." -ForegroundColor Yellow

# Remove existing apache if it exists (but not if it's the target)
if (Test-Path $apachePath) {
    $apacheItem = Get-Item $apachePath
    
    if ($apacheItem.LinkType -eq "SymbolicLink") {
        Write-Host "  → Removing existing symbolic link..." -ForegroundColor White
        Remove-Item $apachePath -Force
    } elseif ($apacheItem.PSIsContainer) {
        Write-Host "  → Removing existing directory..." -ForegroundColor White
        Remove-Item $apachePath -Recurse -Force
    } else {
        Write-Host "  → Removing existing item..." -ForegroundColor White
        Remove-Item $apachePath -Force
    }
} else {
    Write-Host "  ✓ No existing apache directory" -ForegroundColor Green
}

Write-Host ""
Write-Host "Step 3: Creating symbolic link..." -ForegroundColor Yellow

try {
    New-Item -ItemType SymbolicLink -Path $apachePath -Target $apacheTarget -Force | Out-Null
    Write-Host "  ✓ Symbolic link created successfully!" -ForegroundColor Green
} catch {
    Write-Host "  ✗ Failed to create symbolic link: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "  Trying alternative: Copying directory..." -ForegroundColor Yellow
    
    try {
        Copy-Item $apacheTarget -Destination $apachePath -Recurse -Force
        Write-Host "  ✓ Directory copied successfully!" -ForegroundColor Green
    } catch {
        Write-Host "  ✗ Failed to copy: $_" -ForegroundColor Red
        exit 1
    }
}

Write-Host ""
Write-Host "Step 4: Verifying installation..." -ForegroundColor Yellow

if (Test-Path "$apachePath\bin\httpd.exe") {
    Write-Host "  ✓ Apache httpd.exe found!" -ForegroundColor Green
    Write-Host "  ✓ Apache is ready to use!" -ForegroundColor Green
} else {
    Write-Host "  ✗ Apache httpd.exe not found!" -ForegroundColor Red
    Write-Host "  → Please check manually" -ForegroundColor Yellow
    exit 1
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "SUCCESS!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Close XAMPP Control Panel (if open)" -ForegroundColor White
Write-Host "2. Open XAMPP Control Panel again" -ForegroundColor White
Write-Host "3. Apache should now appear in the services list" -ForegroundColor White
Write-Host "4. Click 'Start' next to Apache" -ForegroundColor White
Write-Host "5. Access: http://localhost/phpmyadmin" -ForegroundColor White
Write-Host ""


