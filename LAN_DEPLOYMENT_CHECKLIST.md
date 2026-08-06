# Laravel LAN Deployment Checklist
## Reusable Checklist for Future Installations

**Project:** _________________________  
**Server IP:** _________________________  
**Deployment Date:** _________________________  
**Deployed By:** _________________________  

---

## PRE-DEPLOYMENT PREPARATION

### Server Requirements
- [ ] Windows OS installed
- [ ] XAMPP installed and tested
- [ ] PHP version compatible with Laravel (7.4+ or 8.x)
- [ ] MySQL/MariaDB running in XAMPP
- [ ] Server computer connected to LAN
- [ ] Server has static IP or DHCP reservation configured

### Application Requirements
- [ ] Laravel application code deployed to server
- [ ] Composer dependencies installed (`composer install`)
- [ ] NPM dependencies installed (`npm install`) - if applicable
- [ ] Assets compiled (`npm run build` or `npm run dev`) - if applicable
- [ ] Database created in MySQL
- [ ] Database migrations run (`php artisan migrate`)
- [ ] Database seeded (if needed) (`php artisan db:seed`)

---

## STEP 1: NETWORK CONFIGURATION

### 1.1 Find Server IP Address
- [ ] Open Command Prompt on server
- [ ] Run: `ipconfig`
- [ ] Locate IPv4 Address under active network adapter
- [ ] **Record IP Address:** `_________________________`
- [ ] Verify IP is on same network as client computers
- [ ] (Optional) Configure static IP to prevent changes

**Verification:**
```cmd
ping [SERVER_IP]
```
- [ ] Ping successful from server
- [ ] IP address documented

---

## STEP 2: APACHE CONFIGURATION

### 2.1 Enable Required Apache Modules
- [ ] Open `C:\xampp\apache\conf\httpd.conf` (as Administrator)
- [ ] Locate: `#LoadModule rewrite_module modules/mod_rewrite.so`
- [ ] Remove `#` to uncomment: `LoadModule rewrite_module modules/mod_rewrite.so`
- [ ] Locate: `#Include conf/extra/httpd-vhosts.conf`
- [ ] Remove `#` to uncomment: `Include conf/extra/httpd-vhosts.conf`
- [ ] Save file

**Verification:**
- [ ] mod_rewrite enabled
- [ ] VirtualHosts enabled

### 2.2 Configure VirtualHost
- [ ] Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
- [ ] Add VirtualHost configuration:
  ```apache
  <VirtualHost *:80>
      ServerName [SERVER_IP]
      DocumentRoot "[PROJECT_PATH]/public"
      
      <Directory "[PROJECT_PATH]/public">
          Options Indexes FollowSymLinks
          AllowOverride All
          Require all granted
          RewriteEngine On
      </Directory>
      
      ErrorLog "C:/xampp/apache/logs/[PROJECT_NAME]_error.log"
      CustomLog "C:/xampp/apache/logs/[PROJECT_NAME]_access.log" common
  </VirtualHost>
  ```
- [ ] Replace `[SERVER_IP]` with actual server IP
- [ ] Replace `[PROJECT_PATH]` with full path to Laravel project
- [ ] Replace `[PROJECT_NAME]` with project identifier
- [ ] Verify DocumentRoot path uses forward slashes (`/`) or escaped backslashes
- [ ] Save file

**Verification:**
- [ ] VirtualHost syntax correct
- [ ] Paths verified
- [ ] ServerName matches server IP

### 2.3 Restart Apache
- [ ] Open XAMPP Control Panel
- [ ] Stop Apache (if running)
- [ ] Start Apache
- [ ] Verify Apache starts without errors
- [ ] Check for errors in: `C:\xampp\apache\logs\error.log`

**Verification:**
- [ ] Apache running (green in XAMPP)
- [ ] No errors in error log
- [ ] VirtualHost active

---

## STEP 3: LARAVEL ENVIRONMENT CONFIGURATION

### 3.1 Configure .env File
- [ ] Open `.env` file in project root
- [ ] Set `APP_URL=http://[SERVER_IP]` (replace with actual IP)
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Verify `APP_KEY` is set (if not, run `php artisan key:generate`)
- [ ] Set `DB_CONNECTION=mysql`
- [ ] Set `DB_HOST=127.0.0.1` (NOT server IP)
- [ ] Set `DB_PORT=3306`
- [ ] Set `DB_DATABASE=[DATABASE_NAME]`
- [ ] Set `DB_USERNAME=[MYSQL_USER]`
- [ ] Set `DB_PASSWORD=[MYSQL_PASSWORD]`
- [ ] Set `SESSION_DRIVER=file`
- [ ] Set `CACHE_DRIVER=file`
- [ ] (Optional) Add server IP to `SANCTUM_STATEFUL_DOMAINS` if using Sanctum
- [ ] Save file

**Critical Checks:**
- [ ] `APP_URL` uses `http://[SERVER_IP]` (not localhost)
- [ ] `DB_HOST` is `127.0.0.1` (not server IP)
- [ ] Database credentials correct
- [ ] `APP_DEBUG=false` for production

### 3.2 Clear Laravel Cache
- [ ] Open Command Prompt in project directory
- [ ] Run: `php artisan config:clear`
- [ ] Run: `php artisan cache:clear`
- [ ] Run: `php artisan route:clear`
- [ ] Run: `php artisan view:clear`
- [ ] (If config cached) Run: `php artisan config:cache`

**Verification:**
- [ ] All cache cleared
- [ ] No errors during cache clear

### 3.3 Verify Database Connection
- [ ] Run: `php artisan tinker`
- [ ] Test: `DB::connection()->getPdo();`
- [ ] Should return PDO object without errors
- [ ] Exit tinker: `exit`

**Verification:**
- [ ] Database connection successful
- [ ] No connection errors

---

## STEP 4: FILE PERMISSIONS

### 4.1 Set Storage Permissions
- [ ] Navigate to `storage` folder
- [ ] Right-click → Properties → Security tab
- [ ] Click Edit → Add → Type `Everyone` → OK
- [ ] Check "Full control" → OK → OK
- [ ] Verify subfolders have permissions:
  - [ ] `storage/app`
  - [ ] `storage/framework`
  - [ ] `storage/framework/cache`
  - [ ] `storage/framework/sessions`
  - [ ] `storage/framework/views`
  - [ ] `storage/logs`

**Alternative (Command Prompt as Administrator):**
```cmd
icacls "storage" /grant Everyone:(OI)(CI)F /T
```
- [ ] Command executed successfully

### 4.2 Set Bootstrap Cache Permissions
- [ ] Navigate to `bootstrap/cache` folder
- [ ] Right-click → Properties → Security tab
- [ ] Click Edit → Add → Type `Everyone` → OK
- [ ] Check "Full control" → OK → OK

**Alternative (Command Prompt as Administrator):**
```cmd
icacls "bootstrap\cache" /grant Everyone:(OI)(CI)F /T
```
- [ ] Command executed successfully

### 4.3 Create Storage Link (if needed)
- [ ] Run: `php artisan storage:link`
- [ ] Verify symlink created: `public/storage` → `storage/app/public`

**Verification:**
- [ ] Storage folder writable
- [ ] Bootstrap cache folder writable
- [ ] Storage link created (if applicable)

---

## STEP 5: WINDOWS FIREWALL CONFIGURATION

### 5.1 Add Firewall Rule (Method 1: GUI)
- [ ] Open Windows Defender Firewall
- [ ] Click "Allow an app or feature through Windows Defender Firewall"
- [ ] Click "Change settings" → "Allow another app"
- [ ] Browse to: `C:\xampp\apache\bin\httpd.exe`
- [ ] Check both "Private" and "Public" networks
- [ ] Click OK → OK

**Verification:**
- [ ] Apache in allowed apps list
- [ ] Both Private and Public checked

### 5.2 Add Firewall Rule (Method 2: Command Line)
- [ ] Open Command Prompt as Administrator
- [ ] Run: `netsh advfirewall firewall add rule name="Apache HTTP Server" dir=in action=allow protocol=TCP localport=80`
- [ ] (If using different port) Run: `netsh advfirewall firewall add rule name="Apache HTTP Server [PORT]" dir=in action=allow protocol=TCP localport=[PORT]`

**Verification:**
- [ ] Command executed successfully
- [ ] Rule created

### 5.3 Verify Firewall Rule
- [ ] Open Windows Defender Firewall → Advanced settings
- [ ] Click "Inbound Rules"
- [ ] Locate "Apache HTTP Server" rule
- [ ] Verify rule is Enabled
- [ ] Verify Action is "Allow"
- [ ] Verify Protocol is TCP, Local Port is 80

**Verification:**
- [ ] Firewall rule exists
- [ ] Rule is enabled
- [ ] Port 80 allowed

---

## STEP 6: TESTING & VERIFICATION

### 6.1 Test from Server Computer
- [ ] Open browser on server
- [ ] Navigate to: `http://[SERVER_IP]`
- [ ] Verify application loads (login page or home page)
- [ ] Test health check: `http://[SERVER_IP]/ping`
  - [ ] Should return JSON: `{"status":"OK","message":"Server OK",...}`
- [ ] Test login functionality (if applicable)
- [ ] Verify static assets load (CSS, JS, images)
- [ ] Check browser console for errors (F12)

**Verification:**
- [ ] Application accessible from server
- [ ] Health check working
- [ ] No console errors
- [ ] Static assets loading

### 6.2 Test from Client Computer
- [ ] On different computer (same network)
- [ ] Open browser
- [ ] Navigate to: `http://[SERVER_IP]`
- [ ] Verify application loads
- [ ] Test health check: `http://[SERVER_IP]/ping`
- [ ] Test login functionality
- [ ] Verify static assets load
- [ ] Check browser console for errors

**Verification:**
- [ ] Application accessible from client
- [ ] Health check working
- [ ] No console errors
- [ ] Static assets loading

### 6.3 Network Connectivity Test
- [ ] From client computer, open Command Prompt
- [ ] Run: `ping [SERVER_IP]`
- [ ] Verify ping successful (no packet loss)
- [ ] (Optional) Test port: `telnet [SERVER_IP] 80`
  - [ ] Connection successful

**Verification:**
- [ ] Network connectivity confirmed
- [ ] Port 80 accessible

### 6.4 Functional Testing
- [ ] Test user login/logout
- [ ] Test main application features
- [ ] Test database operations (create, read, update)
- [ ] Test file uploads (if applicable)
- [ ] Test session persistence
- [ ] Test multiple concurrent users (if possible)

**Verification:**
- [ ] All critical features working
- [ ] Database operations successful
- [ ] Sessions working correctly

---

## STEP 7: LOGGING & MONITORING

### 7.1 Verify Logging
- [ ] Check Apache error log: `C:\xampp\apache\logs\error.log`
- [ ] Check Laravel log: `storage/logs/laravel.log`
- [ ] Verify logs are being written
- [ ] Test error logging (trigger a test error)

**Verification:**
- [ ] Logs accessible
- [ ] Logs being written
- [ ] No permission errors

### 7.2 Set Up Monitoring (Optional)
- [ ] Document log file locations
- [ ] Set up log rotation (if needed)
- [ ] Document monitoring procedures
- [ ] Create backup schedule

---

## STEP 8: DOCUMENTATION

### 8.1 Document Configuration
- [ ] Record server IP address
- [ ] Document database credentials (securely)
- [ ] Document Apache configuration location
- [ ] Document .env key settings
- [ ] Document any custom configurations

### 8.2 Create Access Instructions
- [ ] Document client access URL: `http://[SERVER_IP]`
- [ ] Create user guide (if needed)
- [ ] Document health check URL: `http://[SERVER_IP]/ping`
- [ ] Document troubleshooting steps

### 8.3 Backup Information
- [ ] Document backup procedures
- [ ] Document restore procedures
- [ ] Create backup schedule
- [ ] Test backup/restore process

---

## POST-DEPLOYMENT

### Final Checks
- [ ] All checklist items completed
- [ ] Application accessible from server
- [ ] Application accessible from clients
- [ ] All features tested and working
- [ ] Documentation complete
- [ ] Team notified of deployment
- [ ] Access credentials distributed (if needed)

### Maintenance Schedule
- [ ] Schedule regular backups
- [ ] Schedule log review
- [ ] Schedule security updates
- [ ] Document maintenance procedures

---

## TROUBLESHOOTING QUICK REFERENCE

### Common Issues

**Cannot access from client:**
- [ ] Verify server IP address
- [ ] Check Windows Firewall
- [ ] Verify Apache is running
- [ ] Check network connectivity (ping)
- [ ] Verify VirtualHost configuration

**500 Internal Server Error:**
- [ ] Check `storage/logs/laravel.log`
- [ ] Verify file permissions
- [ ] Check `.env` configuration
- [ ] Verify database connection
- [ ] Clear Laravel cache

**404 Not Found:**
- [ ] Verify `mod_rewrite` enabled
- [ ] Check `.htaccess` file exists
- [ ] Verify `AllowOverride All` in VirtualHost
- [ ] Check Apache error log

**Static assets not loading:**
- [ ] Verify `APP_URL` in `.env`
- [ ] Clear Laravel cache
- [ ] Check browser console for 404 errors
- [ ] Verify `public/storage` symlink exists

**Database connection errors:**
- [ ] Verify MySQL is running
- [ ] Check database credentials in `.env`
- [ ] Verify `DB_HOST=127.0.0.1`
- [ ] Test connection with `php artisan tinker`

---

## DEPLOYMENT SIGN-OFF

**Deployment Completed By:** _________________________  
**Date:** _________________________  
**Time:** _________________________  

**Verified By:** _________________________  
**Date:** _________________________  

**Notes:**
```
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
```

---

## VERSION HISTORY

| Version | Date | Changes | Updated By |
|---------|------|---------|------------|
| 1.0 | [Date] | Initial checklist | [Name] |

---

**Checklist Template Version:** 1.0  
**Last Updated:** 2024



