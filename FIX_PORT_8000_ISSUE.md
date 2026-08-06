# Fix: Connection Refused on Port 8000

## Problem
Getting "127.0.0.1 refused to connect" when accessing `http://127.0.0.1:8000`

## Root Cause
1. Apache is configured for **port 80**, not port 8000
2. Port 8000 was likely running Laravel's development server (`php artisan serve`), which has stopped
3. Apache may not be running

## Solution Options

### Option 1: Use Port 80 (Recommended)
Apache is already configured for port 80. Simply use:
- **URL**: `http://127.0.0.1` or `http://localhost`

**Steps:**
1. Open XAMPP Control Panel
2. Make sure **Apache** is running (green)
3. Access: `http://127.0.0.1` or `http://localhost`

### Option 2: Configure Apache for Port 8000
If you need to use port 8000, you need to:

#### Step 1: Update Apache httpd.conf
1. Open `C:\xampp\apache\conf\httpd.conf`
2. Find the `Listen` directive
3. Add this line:
   ```apache
   Listen 8000
   ```
   Or change existing `Listen 80` to:
   ```apache
   Listen 80
   Listen 8000
   ```

#### Step 2: Create VirtualHost for Port 8000
1. Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
2. Add this configuration:
   ```apache
   <VirtualHost *:8000>
       ServerName 127.0.0.1:8000
       DocumentRoot "C:/xampp/htdocs/UTAMADUNI NEW SYSTEM/UPDATED UTAMADUNI/public"
       
       <Directory "C:/xampp/htdocs/UTAMADUNI NEW SYSTEM/UPDATED UTAMADUNI/public">
           Options Indexes FollowSymLinks
           AllowOverride All
           Require all granted
           RewriteEngine On
       </Directory>
       
       <FilesMatch \.php$>
           SetHandler application/x-httpd-php
       </FilesMatch>
   </VirtualHost>
   ```

#### Step 3: Restart Apache
1. Stop Apache in XAMPP Control Panel
2. Start Apache
3. Access: `http://127.0.0.1:8000`

### Option 3: Use Laravel Development Server
If you prefer Laravel's built-in server:

```powershell
cd "C:\xampp\htdocs\UTAMADUNI NEW SYSTEM\UPDATED UTAMADUNI"
php artisan serve --host=127.0.0.1 --port=8000
```

Then access: `http://127.0.0.1:8000`

**Note:** This server stops when you close the terminal.

## Quick Fix (Recommended)

**Just use port 80 instead:**

1. **Start Apache** in XAMPP Control Panel
2. **Access**: `http://127.0.0.1` or `http://localhost`
3. Make sure MySQL is also running

## Verification

### Check if Apache is running:
```powershell
netstat -an | findstr ":80 " | findstr "LISTENING"
```

You should see:
```
TCP    0.0.0.0:80             0.0.0.0:0              LISTENING
```

### Check if port 8000 is listening:
```powershell
netstat -an | findstr ":8000"
```

## Troubleshooting

### Apache won't start?
1. Check XAMPP Control Panel for error messages
2. Check if port 80 is already in use:
   ```powershell
   netstat -ano | findstr ":80 "
   ```
3. If another application is using port 80, either:
   - Stop that application
   - Use port 8000 (see Option 2 above)

### Still getting connection refused?
1. Check Windows Firewall isn't blocking
2. Verify Apache is actually running
3. Check Apache error log: `C:\xampp\apache\logs\error.log`



