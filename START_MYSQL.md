# Fix: MySQL Connection Refused Error

## Problem
```
SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it
```

## Root Cause
**MySQL is NOT running** in XAMPP.

## Solution

### Step 1: Start MySQL in XAMPP
1. **Open XAMPP Control Panel**
2. Find **MySQL** in the services list
3. Click the **"Start"** button next to MySQL
4. **Wait** until it shows **"Running"** (green status)

### Step 2: Verify MySQL is Running
After starting MySQL, run this command:
```powershell
netstat -an | findstr ":3306"
```

You should see:
```
TCP    127.0.0.1:3306         0.0.0.0:0              LISTENING
```

### Step 3: Test Database Connection
Run:
```powershell
php test-db-connection.php
```

You should see:
```
✓ SUCCESS: Database connection successful!
```

### Step 4: Refresh Your Browser
After MySQL is running, refresh your browser at:
- `http://127.0.0.1` or
- `http://localhost`

The error should be gone!

---

## If MySQL Won't Start

### Check for Port Conflicts
If MySQL won't start, another application might be using port 3306:

```powershell
netstat -ano | findstr ":3306"
```

If you see a different PID, that application is blocking MySQL.

### Common Solutions:

1. **Stop conflicting service:**
   - Check if WAMP or another MySQL service is running
   - Stop it from Services (services.msc)

2. **Check XAMPP Error Log:**
   - Look at XAMPP Control Panel for error messages
   - Check: `C:\xampp\mysql\data\*.err` files

3. **Try starting MySQL manually:**
   ```powershell
   C:\xampp\mysql\bin\mysqld.exe --console
   ```

---

## Quick Checklist

- [ ] XAMPP Control Panel is open
- [ ] MySQL shows "Running" (green) in XAMPP
- [ ] Port 3306 is listening (check with netstat)
- [ ] Database `solkadat_inventory` exists in phpMyAdmin
- [ ] Can access phpMyAdmin: http://localhost/phpmyadmin
- [ ] Browser refreshed after starting MySQL

---

## Verify Database Exists

1. Open **phpMyAdmin**: http://localhost/phpmyadmin
2. Check if database `solkadat_inventory` exists
3. If it doesn't exist:
   - Click "New" → Create database
   - Name: `solkadat_inventory`
   - Import: `database/solkadat_inventory (7).sql`

---

## Summary

**Just start MySQL in XAMPP Control Panel!** That's all you need to do.

Once MySQL is running, your application will work.



