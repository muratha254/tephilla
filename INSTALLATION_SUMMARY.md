# Receipt Auto-Print - Installation System

## 🚀 One-Click Installation

The receipt auto-print system now includes **automated installation scripts** that make setup as simple as running a single file!

---

## 📦 Installation Files

### For End Users (Simple Installation)

| File | Purpose | How to Use |
|------|---------|------------|
| **`SETUP_WIZARD.bat`** | ⭐ **One-click installer** | Double-click to install everything |
| **`install_auto_print.bat`** | Windows installer | Double-click or run manually |
| **`install_auto_print.php`** | Cross-platform installer | Run: `php install_auto_print.php` |
| **`verify_installation.php`** | Verification tool | Run: `php verify_installation.php` |

### Documentation Files

| File | Purpose |
|------|---------|
| **`INSTALLATION_GUIDE.md`** | Complete installation instructions |
| **`README_INSTALLATION.txt`** | Quick reference |
| **`RECEIPT_AUTO_PRINT_GUIDE.md`** | Usage and configuration guide |
| **`AUTO_PRINT_QUICK_REFERENCE.md`** | Quick troubleshooting |

---

## 🎯 Quick Start (3 Steps)

### Step 1: Run Installer

**Windows:**
```
Double-click: SETUP_WIZARD.bat
```

**Linux/Mac:**
```bash
php install_auto_print.php
```

### Step 2: Follow Prompts

The installer will:
- ✅ Check prerequisites
- ✅ Backup existing files
- ✅ Install components
- ✅ Clear Laravel cache
- ✅ Verify installation

### Step 3: Test

Complete a test sale and verify receipt prints automatically!

---

## 📋 What Gets Installed

### 1. Receipt View
- **File:** `resources/views/penjualan/receipt_auto_print.blade.php`
- **Purpose:** Thermal printer-optimized receipt template
- **Features:** Auto-print, auto-close, print CSS

### 2. Controller Method
- **File:** `app/Http/Controllers/PenjualanController.php`
- **Method:** `autoPrintReceipt($id)`
- **Purpose:** Handles receipt display and printing

### 3. Route
- **File:** `routes/web.php`
- **Route:** `penjualan.auto_print`
- **URL:** `/penjualan/{id}/auto-print`

### 4. Controller Update
- **File:** `app/Http/Controllers/PenjualanController.php`
- **Change:** `store()` method redirects to auto-print

---

## 🔧 Installation Methods

### Method 1: One-Click Setup (Easiest)

**Windows:**
1. Double-click `SETUP_WIZARD.bat`
2. Done!

**Features:**
- Fully automated
- Creates backups
- Verifies installation
- Shows next steps

### Method 2: Automated Script

**Windows:**
```batch
install_auto_print.bat
```

**Linux/Mac:**
```bash
php install_auto_print.php
```

**Features:**
- Interactive prompts
- File backups
- Automatic updates
- Cache clearing

### Method 3: Manual Installation

See `INSTALLATION_GUIDE.md` for step-by-step manual instructions.

---

## ✅ Verification

### Automatic Verification

After installation, run:
```bash
php verify_installation.php
```

**Checks:**
- ✅ Receipt view file exists
- ✅ Controller method exists
- ✅ Route exists
- ✅ JavaScript present
- ✅ CSS present
- ✅ Cache status

### Manual Verification

**Checklist:**
- [ ] `receipt_auto_print.blade.php` exists
- [ ] `autoPrintReceipt()` method in controller
- [ ] Route `penjualan.auto_print` in routes
- [ ] `store()` redirects to auto-print
- [ ] Test sale works

---

## 🛠️ Installation Scripts Explained

### SETUP_WIZARD.bat

**Purpose:** One-click complete installation

**What it does:**
1. Runs `install_auto_print.bat`
2. Runs `verify_installation.php`
3. Shows summary and next steps

**Usage:**
```
Double-click the file
```

### install_auto_print.bat (Windows)

**Purpose:** Windows-specific installation

**What it does:**
1. Checks prerequisites
2. Creates backups
3. Installs files
4. Updates controller
5. Updates routes
6. Clears cache

**Usage:**
```
Double-click or run from command prompt
```

### install_auto_print.php (Cross-platform)

**Purpose:** Works on Windows, Linux, Mac

**What it does:**
1. Checks Laravel project
2. Creates backups
3. Verifies files
4. Updates code
5. Clears cache
6. Interactive prompts

**Usage:**
```bash
php install_auto_print.php
```

### verify_installation.php

**Purpose:** Verify installation is correct

**What it checks:**
- File existence
- Method presence
- Route presence
- Code correctness
- Cache status

**Usage:**
```bash
php verify_installation.php
```

---

## 🔄 Uninstallation

### Automatic Uninstall

1. **Restore from backup:**
   - Find backup directory: `backup_auto_print_YYYYMMDD_HHMMSS`
   - Restore files manually

2. **Or remove manually:**
   - Remove `autoPrintReceipt()` method
   - Remove route
   - Change redirect back
   - Delete view file (optional)

3. **Clear cache:**
   ```bash
   php artisan route:clear
   php artisan view:clear
   ```

---

## 📊 Installation Flow

```
User runs installer
    ↓
Check prerequisites
    ↓
Create backups
    ↓
Install/update files
    ↓
Update controller
    ↓
Update routes
    ↓
Clear cache
    ↓
Verify installation
    ↓
Show summary
    ↓
Done!
```

---

## 🐛 Troubleshooting Installation

### Script Won't Run

**Windows:**
- Right-click → Run as Administrator
- Check file isn't blocked (Properties → Unblock)

**Linux/Mac:**
- Make executable: `chmod +x install_auto_print.php`
- Run: `php install_auto_print.php`

### Files Not Found

**Error:** "Laravel project not detected"
- **Solution:** Run from Laravel project root
- Ensure `artisan` file exists

**Error:** "Controller not found"
- **Solution:** Verify `PenjualanController.php` exists
- Check file path

### Installation Fails

**Error:** "Could not update files"
- **Solution:** Check file permissions
- Run as administrator (Windows)
- Check write permissions (Linux/Mac)

### Verification Fails

**Error:** "Method not found"
- **Solution:** Run installer again
- Or add method manually

**Error:** "Route not found"
- **Solution:** Run installer again
- Or add route manually

---

## 📝 Post-Installation

### Required Configuration

1. **Browser Setup:**
   - Set thermal printer as default
   - Allow popups for POS URL

2. **Printer Width:**
   - Adjust CSS for 58mm or 80mm
   - Edit `receipt_auto_print.blade.php`

3. **Testing:**
   - Complete test sale
   - Verify print quality
   - Adjust if needed

### Optional Configuration

1. **Chrome Kiosk Mode:**
   - For silent printing setup
   - See `RECEIPT_AUTO_PRINT_GUIDE.md`

2. **Custom Receipt Layout:**
   - Edit `receipt_auto_print.blade.php`
   - Customize CSS

---

## 📚 Documentation

### Installation Docs

- **`INSTALLATION_GUIDE.md`** - Complete installation guide
- **`README_INSTALLATION.txt`** - Quick reference
- **`INSTALLATION_SUMMARY.md`** - This file

### Usage Docs

- **`RECEIPT_AUTO_PRINT_GUIDE.md`** - Complete usage guide
- **`AUTO_PRINT_QUICK_REFERENCE.md`** - Quick reference

---

## ✨ Features

### Installation Features

✅ **One-click installation** - Just run SETUP_WIZARD.bat  
✅ **Automatic backups** - Files backed up before changes  
✅ **Cross-platform** - Works on Windows, Linux, Mac  
✅ **Verification** - Checks installation automatically  
✅ **Interactive** - Prompts for user input when needed  
✅ **Safe** - Creates backups, verifies before changes  

### System Features

✅ **Auto-print** - Triggers automatically  
✅ **Auto-close** - Closes window after printing  
✅ **Thermal optimized** - CSS for 58mm/80mm printers  
✅ **No dependencies** - Pure browser printing  
✅ **POS integrated** - Seamless workflow  

---

## 🎉 Summary

### For End Users

**Installation:** Just double-click `SETUP_WIZARD.bat`!

**That's it!** The system installs automatically.

### For Developers

**Installation:** Run `php install_auto_print.php`

**Verification:** Run `php verify_installation.php`

**Manual:** See `INSTALLATION_GUIDE.md`

---

## 🚀 Ready to Install!

1. **Choose your method:**
   - One-click: `SETUP_WIZARD.bat`
   - Automated: `install_auto_print.bat` or `.php`
   - Manual: See `INSTALLATION_GUIDE.md`

2. **Run the installer**

3. **Test by completing a sale**

4. **Done!** Receipts print automatically!

---

**Last Updated:** 2024  
**Version:** 1.0  
**Installation System:** Complete ✅



