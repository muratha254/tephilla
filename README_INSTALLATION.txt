═══════════════════════════════════════════════════════════════
  RECEIPT AUTO-PRINT - QUICK INSTALLATION
═══════════════════════════════════════════════════════════════

INSTALLATION OPTIONS:
───────────────────────────────────────────────────────────────

OPTION 1: AUTOMATED INSTALLATION (RECOMMENDED)
───────────────────────────────────────────────────────────────

Windows (XAMPP):
  1. Double-click: install_auto_print.bat
  2. Follow prompts
  3. Done!

Linux/Mac:
  1. Open terminal in project root
  2. Run: php install_auto_print.php
  3. Follow prompts
  4. Done!

───────────────────────────────────────────────────────────────

OPTION 2: MANUAL INSTALLATION
───────────────────────────────────────────────────────────────

1. Add autoPrintReceipt() method to PenjualanController
2. Update store() method to redirect to auto-print
3. Add route: penjualan.auto_print
4. Run: php artisan route:clear
5. Test by completing a sale

See INSTALLATION_GUIDE.md for detailed steps.

───────────────────────────────────────────────────────────────

VERIFICATION:
───────────────────────────────────────────────────────────────

After installation:
  ✓ Complete a test sale
  ✓ Receipt should open automatically
  ✓ Print dialog should appear
  ✓ Window should close after printing

───────────────────────────────────────────────────────────────

CONFIGURATION:
───────────────────────────────────────────────────────────────

1. Set thermal printer as default in browser
2. Allow popups for your POS URL
3. Adjust printer width (58mm/80mm) if needed

───────────────────────────────────────────────────────────────

SUPPORT:
───────────────────────────────────────────────────────────────

Documentation:
  - INSTALLATION_GUIDE.md (detailed guide)
  - RECEIPT_AUTO_PRINT_GUIDE.md (usage guide)
  - AUTO_PRINT_QUICK_REFERENCE.md (quick reference)

───────────────────────────────────────────────────────────────

═══════════════════════════════════════════════════════════════



