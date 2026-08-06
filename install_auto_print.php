<?php
/**
 * Receipt Auto-Print Installation Script
 * Laravel POS System - Automated Installation
 * 
 * Run this script from your Laravel project root:
 * php install_auto_print.php
 */

echo "\n";
echo "============================================================\n";
echo "  Receipt Auto-Print Installation Wizard\n";
echo "  Laravel POS System\n";
echo "============================================================\n";
echo "\n";

// Check if running from Laravel root
if (!file_exists('artisan')) {
    die("[ERROR] This doesn't appear to be a Laravel project!\nPlease run this script from your Laravel project root directory.\n\n");
}

echo "[1/7] Checking prerequisites...\n\n";

// Check Laravel version
$composerJson = json_decode(file_get_contents('composer.json'), true);
$laravelVersion = $composerJson['require']['laravel/framework'] ?? 'unknown';
echo "[OK] Laravel project detected\n";
echo "[INFO] Laravel version: {$laravelVersion}\n";

// Check required files
$requiredFiles = [
    'app/Http/Controllers/PenjualanController.php',
    'routes/web.php',
    'resources/views/penjualan'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file) || is_dir($file)) {
        echo "[OK] {$file} found\n";
    } else {
        echo "[WARNING] {$file} not found\n";
    }
}

echo "\n[2/7] Creating backup...\n\n";

// Create backup directory
$backupDir = 'backup_auto_print_' . date('Ymd_His');
if (!mkdir($backupDir, 0755, true)) {
    die("[ERROR] Could not create backup directory\n\n");
}
echo "[OK] Backup directory created: {$backupDir}\n";

// Backup existing files
$filesToBackup = [
    'app/Http/Controllers/PenjualanController.php',
    'routes/web.php'
];

foreach ($filesToBackup as $file) {
    if (file_exists($file)) {
        $backupFile = $backupDir . '/' . basename($file) . '.backup';
        if (copy($file, $backupFile)) {
            echo "[OK] Backed up {$file}\n";
        }
    }
}

echo "\n[3/7] Installing receipt view...\n\n";

// Create views directory if needed
$viewDir = 'resources/views/penjualan';
if (!is_dir($viewDir)) {
    mkdir($viewDir, 0755, true);
    echo "[OK] Created {$viewDir} directory\n";
}

// Check if view already exists
$viewFile = $viewDir . '/receipt_auto_print.blade.php';
if (file_exists($viewFile)) {
    echo "[INFO] receipt_auto_print.blade.php already exists\n";
    echo "Overwrite? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) !== 'y') {
        echo "[SKIP] Keeping existing file\n";
    } else {
        copy($viewFile, $backupDir . '/receipt_auto_print.blade.php.backup');
        echo "[OK] Backed up existing view\n";
    }
} else {
    echo "[INFO] receipt_auto_print.blade.php will be created\n";
    echo "[NOTE] Please ensure the view file is created manually or copied\n";
}

echo "\n[4/7] Checking controller...\n\n";

$controllerFile = 'app/Http/Controllers/PenjualanController.php';
if (file_exists($controllerFile)) {
    $controllerContent = file_get_contents($controllerFile);
    
    // Check if autoPrintReceipt method exists
    if (strpos($controllerContent, 'function autoPrintReceipt') !== false) {
        echo "[OK] autoPrintReceipt() method found in controller\n";
    } else {
        echo "[INFO] autoPrintReceipt() method not found\n";
        echo "[NOTE] Method needs to be added manually\n";
        echo "\nAdd this method to PenjualanController:\n";
        echo "----------------------------------------\n";
        echo getControllerMethodTemplate();
        echo "----------------------------------------\n";
    }
    
    // Check if store method redirects to auto-print
    if (strpos($controllerContent, 'penjualan.auto_print') !== false) {
        echo "[OK] Controller redirects to auto-print route\n";
    } else {
        echo "[INFO] Controller may need update to redirect to auto-print\n";
    }
} else {
    echo "[ERROR] PenjualanController.php not found!\n";
    exit(1);
}

echo "\n[5/7] Checking routes...\n\n";

$routesFile = 'routes/web.php';
if (file_exists($routesFile)) {
    $routesContent = file_get_contents($routesFile);
    
    // Check if route exists
    if (strpos($routesContent, 'penjualan.auto_print') !== false) {
        echo "[OK] Auto-print route found\n";
    } else {
        echo "[INFO] Auto-print route not found\n";
        echo "[NOTE] Route needs to be added\n";
        echo "\nAdd this route to routes/web.php:\n";
        echo "----------------------------------------\n";
        echo "Route::get('/penjualan/{id}/auto-print', [PenjualanController::class, 'autoPrintReceipt'])->name('penjualan.auto_print');\n";
        echo "----------------------------------------\n";
        
        echo "\nAdd route automatically? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($line) === 'y') {
            // Find a good place to add the route (after reprint route)
            if (strpos($routesContent, 'penjualan.reprint') !== false) {
                $routesContent = str_replace(
                    "->name('penjualan.reprint');",
                    "->name('penjualan.reprint');\n        Route::get('/penjualan/{id}/auto-print', [PenjualanController::class, 'autoPrintReceipt'])->name('penjualan.auto_print');",
                    $routesContent
                );
                file_put_contents($routesFile, $routesContent);
                echo "[OK] Route added to routes/web.php\n";
            } else {
                echo "[WARNING] Could not find insertion point. Please add route manually.\n";
            }
        }
    }
} else {
    echo "[ERROR] routes/web.php not found!\n";
    exit(1);
}

echo "\n[6/7] Running Laravel commands...\n\n";

// Clear caches
$commands = [
    'config:clear',
    'route:clear',
    'view:clear',
    'cache:clear'
];

foreach ($commands as $command) {
    echo "[INFO] Running: php artisan {$command}\n";
    $output = [];
    $returnVar = 0;
    exec("php artisan {$command} 2>&1", $output, $returnVar);
    
    if ($returnVar === 0) {
        echo "[OK] {$command} completed\n";
    } else {
        echo "[WARNING] {$command} may have failed\n";
    }
}

echo "\n[7/7] Verification...\n\n";

// Verify installation
$checks = [
    'View file exists' => file_exists($viewFile),
    'Controller has method' => strpos(file_get_contents($controllerFile), 'function autoPrintReceipt') !== false,
    'Route exists' => strpos(file_get_contents($routesFile), 'penjualan.auto_print') !== false,
];

$allPassed = true;
foreach ($checks as $check => $passed) {
    if ($passed) {
        echo "[OK] {$check}\n";
    } else {
        echo "[FAIL] {$check}\n";
        $allPassed = false;
    }
}

echo "\n";
echo "============================================================\n";
echo "  Installation Summary\n";
echo "============================================================\n";
echo "\n";

if ($allPassed) {
    echo "[SUCCESS] All checks passed!\n\n";
} else {
    echo "[WARNING] Some checks failed. Please review the output above.\n\n";
}

echo "NEXT STEPS:\n";
echo "1. Test the installation:\n";
echo "   - Complete a test sale\n";
echo "   - Verify receipt opens and prints automatically\n\n";
echo "2. Configure browser:\n";
echo "   - Set thermal printer as default\n";
echo "   - Allow popups for your POS URL\n\n";
echo "3. Review documentation:\n";
echo "   - RECEIPT_AUTO_PRINT_GUIDE.md\n";
echo "   - AUTO_PRINT_QUICK_REFERENCE.md\n\n";
echo "4. Backup location: {$backupDir}\n\n";
echo "============================================================\n";
echo "\n";

function getControllerMethodTemplate() {
    return <<<'METHOD'
    /**
     * Auto-print receipt after sale completion
     * Opens in new window and automatically triggers print
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
METHOD;
}



