# Quick Fix: Connection Refused on Port 8000

## The Problem
- Apache is **NOT running**
- You're trying to access port 8000, but Apache is configured for port 80

## EASIEST SOLUTION: Use Port 80

1. **Open XAMPP Control Panel**
2. **Click "Start" next to Apache** (wait until it shows green "Running")
3. **Click "Start" next to MySQL** (wait until it shows green "Running")
4. **Open browser**: `http://127.0.0.1` or `http://localhost`

That's it! No configuration needed.

---

## If You MUST Use Port 8000

### Manual Steps:

1. **Open**: `C:\xampp\apache\conf\httpd.conf`
2. **Find**: `Listen 80`
3. **Add below it**: `Listen 8000`
4. **Save** the file

5. **Open**: `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
6. **Add this at the end**:

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

7. **Restart Apache** in XAMPP Control Panel
8. **Access**: `http://127.0.0.1:8000`

---

## Check if Apache is Running

Run this command:
```powershell
netstat -an | findstr ":80 " | findstr "LISTENING"
```

If you see output, Apache is running. If not, start Apache in XAMPP.

---

## Summary

**Just start Apache and use port 80!** It's already configured and ready to go.



