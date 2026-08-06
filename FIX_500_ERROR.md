# Fix: 500 Server Error - Database Connection Issue

## Problem Identified
The 500 error was caused by a **database connection failure**. The application couldn't connect to MySQL.

## Root Causes Found
1. ✅ **FIXED**: `.env` file was missing/empty - Now created with correct configuration
2. ⚠️ **ACTION NEEDED**: MySQL is not running in XAMPP

## What I Fixed

### 1. Created `.env` File
Created a complete `.env` file with:
- Database name: `solkadat_inventory`
- Database host: `127.0.0.1`
- Database user: `root` (XAMPP default)
- Database password: (empty - XAMPP default)
- APP_KEY: Generated
- APP_URL: `http://127.0.0.1:8000`

### 2. Cleared Laravel Cache
- Configuration cache cleared
- Application cache cleared

## What You Need to Do

### Step 1: Start MySQL in XAMPP
1. Open **XAMPP Control Panel**
2. Find **MySQL** in the list
3. Click **Start** button next to MySQL
4. Wait until it shows "Running" (green)

### Step 2: Verify Database Exists
1. Open **phpMyAdmin** (http://localhost/phpmyadmin)
2. Check if database `solkadat_inventory` exists
3. If it doesn't exist:
   - Click "New" to create database
   - Name: `solkadat_inventory`
   - Collation: `utf8mb4_unicode_ci`
   - Click "Create"
   - Import the SQL file: `database/solkadat_inventory (7).sql`

### Step 3: Test Database Connection
Run this command in PowerShell:
```powershell
cd "C:\xampp\htdocs\UTAMADUNI NEW SYSTEM\UPDATED UTAMADUNI"
php test-db-connection.php
```

You should see:
```
✓ SUCCESS: Database connection successful!
```

### Step 4: Test the Application
1. Make sure **Apache** is running in XAMPP
2. Make sure **MySQL** is running in XAMPP
3. Open browser: `http://127.0.0.1:8000`
4. The 500 error should be gone!

## If Database Connection Still Fails

### Option 1: MySQL Has a Password
If your MySQL `root` user has a password:
1. Open `.env` file
2. Update `DB_PASSWORD=your_password_here`
3. Run: `php artisan config:clear`

### Option 2: Different Database User
If you need to use a different MySQL user:
1. Open `.env` file
2. Update `DB_USERNAME=your_username`
3. Update `DB_PASSWORD=your_password`
4. Run: `php artisan config:clear`

### Option 3: Database Doesn't Exist
1. Open phpMyAdmin
2. Create database: `solkadat_inventory`
3. Import: `database/solkadat_inventory (7).sql`

## Verification Checklist
- [ ] MySQL is running in XAMPP Control Panel
- [ ] Database `solkadat_inventory` exists in phpMyAdmin
- [ ] `.env` file has correct database credentials
- [ ] `test-db-connection.php` shows "SUCCESS"
- [ ] Apache is running in XAMPP
- [ ] Can access `http://127.0.0.1:8000` without 500 error

## Quick Test Commands

```powershell
# Test database connection
php test-db-connection.php

# Clear Laravel cache
php artisan config:clear
php artisan cache:clear

# Check if MySQL port is listening
netstat -an | findstr ":3306"
```

## Common Issues

### Issue: "Access denied for user"
- **Solution**: Check username/password in `.env` file
- Verify credentials in phpMyAdmin

### Issue: "Database doesn't exist"
- **Solution**: Create database in phpMyAdmin or import SQL file

### Issue: "Connection refused"
- **Solution**: Start MySQL in XAMPP Control Panel

### Issue: Still getting 500 error
- Check Laravel logs: `storage/logs/laravel.log`
- Check Apache error log: `C:\xampp\apache\logs\error.log`
- Make sure file permissions are correct on `storage/` and `bootstrap/cache/`



