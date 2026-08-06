# Laravel LAN Deployment Guide
## Running UTAMADUNI POS System on Local Network

This guide provides step-by-step instructions to deploy your Laravel application on a local network (LAN) where one computer acts as the server and other computers access it via web browser.

---

## Prerequisites

- **Server Computer**: Windows with XAMPP installed
- **Client Computers**: Any device with a web browser (Windows, Mac, Linux, tablets, phones)
- **Network**: All devices connected to the same local network (LAN)

---

## STEP 1: Find Your Server's IP Address

1. On the **server computer**, open **Command Prompt** (CMD)
2. Run: `ipconfig`
3. Look for **IPv4 Address** under your active network adapter
   - Example: `192.168.1.100` or `192.168.0.50`
4. **Write down this IP address** - you'll need it for configuration

**Alternative method:**
- Open **Network Settings** → **View network properties**
- Find **IPv4 address**

---

## STEP 2: Configure Apache VirtualHost

### 2.1 Enable mod_rewrite in Apache

1. Open `C:\xampp\apache\conf\httpd.conf` in a text editor (as Administrator)
2. Find the line: `#LoadModule rewrite_module modules/mod_rewrite.so`
3. Remove the `#` to uncomment it:
   ```
   LoadModule rewrite_module modules/mod_rewrite.so
   ```
4. Find: `#Include conf/extra/httpd-vhosts.conf`
5. Remove the `#` to uncomment it:
   ```
   Include conf/extra/httpd-vhosts.conf
   ```

### 2.2 Configure VirtualHost

1. Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
2. Add the VirtualHost configuration (see `apache-vhost.conf` file in project root)
3. **IMPORTANT**: Update these values:
   - `ServerName`: Replace with your server's IP address (from Step 1)
   - `DocumentRoot`: Verify the path matches your Laravel `public` folder
   - `Directory`: Same path as DocumentRoot

**Example VirtualHost:**
```apache
<VirtualHost *:80>
    ServerName 192.168.1.100
    DocumentRoot "C:/xampp/htdocs/UTAMADUNI POS SYSTEM/UTAMADUNI LATEST SYSTEM/UPDATED UTAMADUNI/public"
    
    <Directory "C:/xampp/htdocs/UTAMADUNI POS SYSTEM/UTAMADUNI LATEST SYSTEM/UPDATED UTAMADUNI/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        RewriteEngine On
    </Directory>
</VirtualHost>
```

### 2.3 Restart Apache

1. Open **XAMPP Control Panel**
2. Click **Stop** for Apache
3. Click **Start** for Apache
4. Verify Apache starts without errors

---

## STEP 3: Configure Laravel .env File

1. Open `.env` file in your Laravel project root
2. Update the following settings:

```env
# Application URL - Use your server's IP address
APP_URL=http://192.168.1.100

# Or if using a different port:
# APP_URL=http://192.168.1.100:8080

# Environment
APP_ENV=production
APP_DEBUG=false

# Database - Must run on server only
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=root
DB_PASSWORD=

# Session - Important for LAN access
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Cache
CACHE_DRIVER=file
```

**Important Notes:**
- `APP_URL` must use your server's IP address (not `localhost`)
- `DB_HOST` must be `127.0.0.1` (database runs only on server)
- Replace `192.168.1.100` with your actual server IP

### 3.1 Clear Laravel Cache

After updating `.env`, run these commands in the project directory:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

---

## STEP 4: Set Laravel Permissions

Ensure Laravel can write to storage and cache directories:

### On Windows (XAMPP):

1. Right-click the `storage` folder → **Properties** → **Security** tab
2. Click **Edit** → **Add** → Type `Everyone` → **OK**
3. Check **Full control** → **OK**
4. Repeat for `bootstrap/cache` folder

**Or use Command Prompt (as Administrator):**
```cmd
icacls "storage" /grant Everyone:(OI)(CI)F /T
icacls "bootstrap\cache" /grant Everyone:(OI)(CI)F /T
```

---

## STEP 5: Configure Windows Firewall

Allow Apache through Windows Firewall so clients can access the server:

### Method 1: Using Windows Firewall GUI

1. Open **Windows Defender Firewall**
2. Click **Allow an app or feature through Windows Defender Firewall**
3. Click **Change settings** → **Allow another app**
4. Browse to: `C:\xampp\apache\bin\httpd.exe`
5. Check both **Private** and **Public** networks
6. Click **OK**

### Method 2: Using Command Prompt (as Administrator)

```cmd
netsh advfirewall firewall add rule name="Apache HTTP Server" dir=in action=allow protocol=TCP localport=80
```

**If using a different port (e.g., 8080):**
```cmd
netsh advfirewall firewall add rule name="Apache HTTP Server 8080" dir=in action=allow protocol=TCP localport=8080
```

### Verify Firewall Rule

1. Open **Windows Defender Firewall** → **Advanced settings**
2. Click **Inbound Rules**
3. Verify "Apache HTTP Server" rule exists and is **Enabled**

---

## STEP 6: Test Server Access

### 6.1 Test from Server Computer

1. Open browser on **server computer**
2. Navigate to: `http://192.168.1.100` (use your IP)
3. You should see the Laravel login page
4. Test health check: `http://192.168.1.100/ping`
   - Should return: `{"status":"OK","message":"Server OK",...}`

### 6.2 Test from Client Computer

1. On a **client computer** (different device on same network)
2. Open browser
3. Navigate to: `http://192.168.1.100` (server's IP address)
4. You should see the Laravel login page

**If you see an error:**
- Verify server IP address is correct
- Check Windows Firewall settings
- Ensure Apache is running on server
- Check Apache error logs: `C:\xampp\apache\logs\error.log`

---

## STEP 7: Client Access Instructions

### For End Users (Client Computers)

**No installation required!** Clients only need:

1. **Web browser** (Chrome, Firefox, Edge, Safari, etc.)
2. **Network connection** to the same LAN as the server
3. **Server IP address** from the administrator

**Access URL:**
```
http://[SERVER_IP_ADDRESS]
```

**Example:**
```
http://192.168.1.100
```

**Health Check URL:**
```
http://192.168.1.100/ping
```

---

## Troubleshooting

### Problem: Cannot access from client computers

**Solutions:**
1. **Verify server IP address**
   - Run `ipconfig` on server
   - Ensure IP hasn't changed (if using DHCP)

2. **Check Windows Firewall**
   - Ensure Apache is allowed through firewall
   - Try temporarily disabling firewall to test

3. **Verify Apache is running**
   - Check XAMPP Control Panel
   - Check Apache error logs

4. **Check network connectivity**
   - Ping server IP from client: `ping 192.168.1.100`
   - Ensure both devices on same network

5. **Verify VirtualHost configuration**
   - Check `httpd-vhosts.conf` syntax
   - Ensure DocumentRoot path is correct

### Problem: "500 Internal Server Error"

**Solutions:**
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify `.env` file configuration
3. Check file permissions on `storage/` and `bootstrap/cache/`
4. Run: `php artisan config:clear`

### Problem: "404 Not Found" or routes not working

**Solutions:**
1. Verify `mod_rewrite` is enabled
2. Check `.htaccess` file exists in `public/` folder
3. Verify `AllowOverride All` in VirtualHost
4. Check Apache error logs

### Problem: Database connection errors

**Solutions:**
1. Verify MySQL is running in XAMPP
2. Check database credentials in `.env`
3. Ensure `DB_HOST=127.0.0.1` (not `localhost` or IP address)
4. Test MySQL connection: `php artisan tinker` → `DB::connection()->getPdo();`

### Problem: Static assets (CSS/JS) not loading

**Solutions:**
1. Verify `APP_URL` in `.env` matches server IP
2. Run: `php artisan config:clear`
3. Check browser console for 404 errors
4. Verify `public/storage` symlink exists: `php artisan storage:link`

---

## Security Considerations

### For Production LAN Deployment:

1. **Change default MySQL password**
   - Update `DB_PASSWORD` in `.env`
   - Use strong password

2. **Set `APP_DEBUG=false`**
   - Prevents exposing sensitive information

3. **Use HTTPS (Optional but Recommended)**
   - Configure SSL certificate for Apache
   - Update `APP_URL` to use `https://`

4. **Restrict database access**
   - MySQL should only accept connections from `127.0.0.1`
   - Do NOT bind MySQL to `0.0.0.0` or server IP

5. **Regular backups**
   - Use Laravel backup feature
   - Backup database regularly

---

## Maintenance

### Daily Operations:

1. **Start XAMPP services** (if server restarts)
   - Apache
   - MySQL

2. **Monitor logs**
   - Apache: `C:\xampp\apache\logs\`
   - Laravel: `storage/logs/laravel.log`

3. **Backup database**
   - Use built-in backup feature in application
   - Or use phpMyAdmin

### If Server IP Changes:

1. Update `APP_URL` in `.env`
2. Update `ServerName` in `httpd-vhosts.conf`
3. Restart Apache
4. Clear Laravel cache: `php artisan config:clear`
5. Notify users of new IP address

---

## Quick Reference

| Item | Value/Command |
|------|--------------|
| Find Server IP | `ipconfig` (look for IPv4 Address) |
| Apache Config | `C:\xampp\apache\conf\httpd.conf` |
| VirtualHost Config | `C:\xampp\apache\conf\extra\httpd-vhosts.conf` |
| Laravel .env | Project root `.env` file |
| Health Check | `http://[SERVER_IP]/ping` |
| Clear Cache | `php artisan config:clear` |
| Apache Logs | `C:\xampp\apache\logs\error.log` |
| Laravel Logs | `storage/logs/laravel.log` |

---

## Support

If you encounter issues not covered in this guide:

1. Check Apache error logs
2. Check Laravel logs
3. Verify all configuration files
4. Test network connectivity between devices

---

**Last Updated:** 2024
**Laravel Version:** Compatible with Laravel 8.x and above
**XAMPP Version:** Compatible with XAMPP 7.4+ and 8.x



