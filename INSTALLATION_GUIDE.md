# Receipt Auto-Print Installation Guide

## Quick Installation

### Option 1: Automated Installation (Recommended)

#### For Windows (XAMPP):

1. **Double-click** `install_auto_print.bat`
   - Or right-click → Run as Administrator

2. **Follow the prompts**
   - The script will:
     - Check prerequisites
     - Backup existing files
     - Install components
     - Clear Laravel cache

3. **Done!** Test by completing a sale

#### For Linux/Mac or Command Line:

1. **Open terminal** in your Laravel project root

2. **Run installation script:**
   ```bash
   php install_auto_print.php
   ```

3. **Follow the prompts**
   - Answer yes/no questions
   - Script will install automatically

4. **Done!** Test by completing a sale

---

### Option 2: Manual Installation

If you prefer manual installation or the automated script doesn't work:

#### Step 1: Verify Files

Ensure these files exist:
- ✅ `resources/views/penjualan/receipt_auto_print.blade.php`
- ✅ `app/Http/Controllers/PenjualanController.php`
- ✅ `routes/web.php`

#### Step 2: Add Controller Method

**File:** `app/Http/Controllers/PenjualanController.php`

Add this method (after `reprintReceipt` method):

```php
/**
 * Auto-print receipt after sale completion
 */
public function autoPrintReceipt($id)
{
    $setting = Setting::first();
    $penjualan = Penjualan::find($id);
    
    if (!$penjualan) {
        abort(404, 'Sale not found');
    }
    
    $detail = PenjualanDetail::with('produk')
        ->where('id_penjualan', $id)
        ->get();
    
    if ($detail->isEmpty()) {
        abort(404, 'Sale details not found');
    }
    
    return view('penjualan.receipt_auto_print', compact('setting', 'penjualan', 'detail'));
}
```

#### Step 3: Update Store Method

**File:** `app/Http/Controllers/PenjualanController.php`

Find the `store()` method and change the redirect:

**Find:**
```php
return redirect()->route('transaksi.selesai');
```

**Replace with:**
```php
// Redirect to auto-print receipt page
return redirect()->route('penjualan.auto_print', $penjualan->id_penjualan);
```

#### Step 4: Add Route

**File:** `routes/web.php`

Find the line with `penjualan.reprint` and add after it:

```php
Route::get('/penjualan/{id}/reprint', [PenjualanController::class, 'reprintReceipt'])->name('penjualan.reprint');
Route::get('/penjualan/{id}/auto-print', [PenjualanController::class, 'autoPrintReceipt'])->name('penjualan.auto_print');
```

#### Step 5: Clear Cache

Run these commands:

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

#### Step 6: Test

Complete a test sale and verify:
- ✅ Receipt opens automatically
- ✅ Print dialog appears
- ✅ Window closes after printing

---

## Installation Verification

### Checklist

After installation, verify:

- [ ] `receipt_auto_print.blade.php` exists in `resources/views/penjualan/`
- [ ] `autoPrintReceipt()` method exists in `PenjualanController`
- [ ] Route `penjualan.auto_print` exists in `routes/web.php`
- [ ] `store()` method redirects to auto-print route
- [ ] Laravel cache cleared
- [ ] Test sale completes successfully
- [ ] Receipt prints automatically

### Quick Test

1. **Complete a test sale**
2. **Verify:**
   - Receipt page opens in new window
   - Print dialog appears automatically
   - Receipt content is correct
   - Window closes after printing

---

## Troubleshooting Installation

### Script Fails to Run

**Windows:**
- Right-click → Run as Administrator
- Check if file is blocked (Properties → Unblock)

**Linux/Mac:**
- Make executable: `chmod +x install_auto_print.php`
- Run: `php install_auto_print.php`

### Files Not Found

**Error:** "Laravel project not detected"
- **Solution:** Run script from Laravel project root directory
- Ensure `artisan` file exists in current directory

**Error:** "Controller not found"
- **Solution:** Verify `PenjualanController.php` exists
- Check file path is correct

### Route Already Exists

**Warning:** "Route already exists"
- **Solution:** This is OK - installation will skip
- Verify route works by testing

### Cache Clear Fails

**Warning:** "Could not clear cache"
- **Solution:** Run manually:
  ```bash
  php artisan config:clear
  php artisan route:clear
  php artisan view:clear
  ```

---

## Post-Installation Configuration

### 1. Browser Setup

**Chrome/Edge:**
1. Settings → Advanced → Printing
2. Set thermal printer as default

**Firefox:**
1. Settings → General → Print
2. Select thermal printer

### 2. Printer Width

**For 58mm printers:**

Edit `resources/views/penjualan/receipt_auto_print.blade.php`:

Find:
```css
@page {
    size: 80mm auto;
}
```

Change to:
```css
@page {
    size: 58mm auto;
}
```

### 3. Test Configuration

1. Complete a test sale
2. Verify print quality
3. Adjust CSS if needed

---

## Uninstallation

### Remove Auto-Print Feature

1. **Restore from backup:**
   - Find backup directory: `backup_auto_print_YYYYMMDD_HHMMSS`
   - Restore files:
     ```bash
     copy backup_auto_print_*\PenjualanController.php.backup app\Http\Controllers\PenjualanController.php
     copy backup_auto_print_*\web.php.backup routes\web.php
     ```

2. **Or manually remove:**
   - Remove `autoPrintReceipt()` method from controller
   - Remove `penjualan.auto_print` route
   - Change `store()` redirect back to `transaksi.selesai`
   - Delete `receipt_auto_print.blade.php` (optional)

3. **Clear cache:**
   ```bash
   php artisan route:clear
   php artisan view:clear
   ```

---

## Installation Files

### Provided Files

- ✅ `install_auto_print.bat` - Windows installation script
- ✅ `install_auto_print.php` - Cross-platform installation script
- ✅ `receipt_auto_print.blade.php` - Receipt view (should exist)
- ✅ `INSTALLATION_GUIDE.md` - This file
- ✅ `RECEIPT_AUTO_PRINT_GUIDE.md` - Usage guide
- ✅ `AUTO_PRINT_QUICK_REFERENCE.md` - Quick reference

---

## Support

### Common Issues

**Q: Installation script doesn't run**  
A: Check file permissions, run as administrator

**Q: Files not found during installation**  
A: Ensure you're in Laravel project root

**Q: Route conflict**  
A: Check if route already exists, remove duplicate

**Q: Controller method conflict**  
A: Check if method already exists, remove duplicate

### Getting Help

1. Check installation logs
2. Review error messages
3. Verify file paths
4. Check Laravel logs: `storage/logs/laravel.log`

---

## Summary

### Quick Install (3 steps):

1. **Run:** `install_auto_print.bat` (Windows) or `php install_auto_print.php` (Linux/Mac)
2. **Follow prompts**
3. **Test** by completing a sale

### Manual Install (6 steps):

1. Add controller method
2. Update store() redirect
3. Add route
4. Clear cache
5. Test
6. Configure browser

**That's it!** The system is ready to use.

---

**Last Updated:** 2024  
**Version:** 1.0



