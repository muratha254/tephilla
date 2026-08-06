# Fix: Connection Refused on 192.168.1.118

## Problem
Getting "This site can't be reached - 192.168.1.118 refused to connect"

## Root Cause
Apache is not listening on port 80 for network connections. It may only be listening on localhost (127.0.0.1).

## Solution Steps

### Step 1: Check Apache Main Configuration
Open `C:\xampp\apache\conf\httpd.conf` and find the `Listen` directive.

**Current (WRONG):**
```apache
Listen 127.0.0.1:80
# OR
Listen localhost:80
```

**Change to (CORRECT):**
```apache
Listen *:80
# OR
Listen 192.168.1.118:80
```

The `*:80` means listen on ALL network interfaces (recommended).
The `192.168.1.118:80` means listen only on that specific IP.

### Step 2: Verify VirtualHosts is Included
In the same `httpd.conf` file, make sure this line is NOT commented:
```apache
Include conf/extra/httpd-vhosts.conf
```

If it has a `#` in front, remove it:
```apache
# Include conf/extra/httpd-vhosts.conf  ← WRONG
Include conf/extra/httpd-vhosts.conf    ← CORRECT
```

### Step 3: Copy VirtualHost Configuration
1. Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
2. Copy the content from `apache-vhost.conf` (in your project root)
3. Paste it into `httpd-vhosts.conf`
4. Make sure `ServerName 192.168.1.118` is set correctly

### Step 4: Configure Windows Firewall
Run this command in PowerShell (as Administrator):
```powershell
netsh advfirewall firewall add rule name="Apache HTTP Server" dir=in action=allow protocol=TCP localport=80
```

### Step 5: Restart Apache
1. Open XAMPP Control Panel
2. Stop Apache
3. Start Apache
4. Check for any errors in the Apache log

### Step 6: Verify Apache is Listening
Run this command:
```cmd
netstat -an | findstr ":80"
```

You should see:
```
TCP    0.0.0.0:80             0.0.0.0:0              LISTENING
```

If you see `127.0.0.1:80` instead, Apache is still only listening on localhost.

### Step 7: Test Connection
1. On the server: Open browser → `http://192.168.1.118`
2. On another device: Open browser → `http://192.168.1.118`

## Quick Fix Script

Run these commands in PowerShell (as Administrator):

```powershell
# 1. Add firewall rule
netsh advfirewall firewall add rule name="Apache HTTP Server" dir=in action=allow protocol=TCP localport=80

# 2. Check if Apache is running
Get-Service | Where-Object {$_.DisplayName -like "*Apache*"}

# 3. Check what's listening on port 80
netstat -an | Select-String ":80"
```

## Common Issues

### Issue 1: Port 80 Already in Use
If another application is using port 80:
- Check: `netstat -ano | findstr ":80"`
- Find the PID and stop that application
- OR change Apache to use port 8080 (update VirtualHost accordingly)

### Issue 2: Apache Won't Start
- Check Apache error log: `C:\xampp\apache\logs\error.log`
- Verify all paths in `httpd.conf` are correct
- Make sure no syntax errors in VirtualHost configuration

### Issue 3: Can Access Locally but Not from Network
- Firewall is blocking (see Step 4)
- Apache only listening on localhost (see Step 1)
- Router blocking incoming connections (check router settings)

## Verification Checklist
- [ ] `Listen *:80` in httpd.conf
- [ ] VirtualHosts file included in httpd.conf
- [ ] VirtualHost configuration copied to httpd-vhosts.conf
- [ ] ServerName set to 192.168.1.118
- [ ] Firewall rule added
- [ ] Apache restarted
- [ ] Port 80 shows `0.0.0.0:80` in netstat
- [ ] Can access from server browser
- [ ] Can access from network device





