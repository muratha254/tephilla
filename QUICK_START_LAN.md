# Quick Start: LAN Deployment

## 5-Minute Setup Guide

### Step 1: Find Server IP (30 seconds)
```cmd
ipconfig
```
Look for **IPv4 Address** → Write it down (e.g., `192.168.1.100`)

### Step 2: Configure Apache (2 minutes)

1. Open `C:\xampp\apache\conf\httpd.conf`
   - Uncomment: `LoadModule rewrite_module modules/mod_rewrite.so`
   - Uncomment: `Include conf/extra/httpd-vhosts.conf`

2. Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
   - Copy content from `apache-vhost.conf` (in project root)
   - Replace `192.168.1.100` with your server IP
   - Verify DocumentRoot path is correct

3. Restart Apache in XAMPP Control Panel

### Step 3: Update .env (1 minute)

```env
APP_URL=http://YOUR_SERVER_IP
APP_ENV=production
APP_DEBUG=false
DB_HOST=127.0.0.1
```

Replace `YOUR_SERVER_IP` with IP from Step 1.

### Step 4: Clear Cache (10 seconds)
```cmd
php artisan config:clear
php artisan cache:clear
```

### Step 5: Configure Firewall (1 minute)

**Command Prompt (as Administrator):**
```cmd
netsh advfirewall firewall add rule name="Apache HTTP Server" dir=in action=allow protocol=TCP localport=80
```

### Step 6: Test (30 seconds)

**On Server:**
- Browser: `http://YOUR_SERVER_IP`
- Health Check: `http://YOUR_SERVER_IP/ping`

**On Client:**
- Browser: `http://YOUR_SERVER_IP`

---

## Troubleshooting

**Can't access from client?**
1. Check firewall: `netsh advfirewall firewall show rule name="Apache HTTP Server"`
2. Verify Apache is running
3. Check IP address: `ipconfig`

**500 Error?**
- Check `storage/logs/laravel.log`
- Verify file permissions on `storage/` and `bootstrap/cache/`

**404 Error?**
- Verify `mod_rewrite` is enabled
- Check `.htaccess` exists in `public/` folder

---

## Full Documentation

See `LAN_DEPLOYMENT_GUIDE.md` for detailed instructions.



