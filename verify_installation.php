<?php
/**
 * Receipt Auto-Print Installation Verification
 * 
 * Run this script to verify the installation is correct:
 * php verify_installation.php
 */

echo "\n";
echo "============================================================\n";
echo "  Receipt Auto-Print Installation Verification\n";
echo "============================================================\n";
echo "\n";

$errors = [];
$warnings = [];
$success = [];

// Check Laravel project
if (!file_exists('artisan')) {
    die("[ERROR] Not a Laravel project. Run from project root.\n\n");
}
$success[] = "Laravel project detected";

// Check receipt view
$viewFile = 'resources/views/penjualan/receipt_auto_print.blade.php';
if (file_exists($viewFile)) {
    $success[] = "Receipt view file exists";
    
    // Check for key content
    $viewContent = file_get_contents($viewFile);
    if (strpos($viewContent, 'window.print()') !== false) {
        $success[] = "Auto-print JavaScript found";
    } else {
        $errors[] = "Auto-print JavaScript not found in view";
    }
    
    if (strpos($viewContent, '@media print') !== false) {
        $success[] = "Print CSS found";
    } else {
        $warnings[] = "Print CSS may be missing";
    }
} else {
    $errors[] = "Receipt view file not found: {$viewFile}";
}

// Check controller
$controllerFile = 'app/Http/Controllers/PenjualanController.php';
if (file_exists($controllerFile)) {
    $success[] = "PenjualanController exists";
    
    $controllerContent = file_get_contents($controllerFile);
    
    // Check for autoPrintReceipt method
    if (strpos($controllerContent, 'function autoPrintReceipt') !== false) {
        $success[] = "autoPrintReceipt() method exists";
        
        // Check method signature
        if (preg_match('/function autoPrintReceipt\s*\(\s*\$id\s*\)/', $controllerContent)) {
            $success[] = "Method signature is correct";
        } else {
            $warnings[] = "Method signature may be incorrect";
        }
    } else {
        $errors[] = "autoPrintReceipt() method not found in controller";
    }
    
    // Check if store method redirects to auto-print
    if (strpos($controllerContent, 'penjualan.auto_print') !== false) {
        $success[] = "Controller redirects to auto-print route";
    } else {
        $warnings[] = "Controller may not redirect to auto-print (check store() method)";
    }
} else {
    $errors[] = "PenjualanController not found";
}

// Check routes
$routesFile = 'routes/web.php';
if (file_exists($routesFile)) {
    $success[] = "Routes file exists";
    
    $routesContent = file_get_contents($routesFile);
    
    // Check for route
    if (strpos($routesContent, 'penjualan.auto_print') !== false) {
        $success[] = "Auto-print route exists";
        
        // Check route definition
        if (preg_match('/Route::get\([^)]*penjualan.*auto-print[^)]*\)->name\([\'"]penjualan\.auto_print[\'"]\)/', $routesContent)) {
            $success[] = "Route definition is correct";
        } else {
            $warnings[] = "Route definition may be incorrect";
        }
    } else {
        $errors[] = "Auto-print route not found in routes/web.php";
    }
} else {
    $errors[] = "Routes file not found";
}

// Check Laravel cache
if (file_exists('bootstrap/cache/config.php')) {
    $warnings[] = "Config cache exists - run 'php artisan config:clear'";
}
if (file_exists('bootstrap/cache/routes.php')) {
    $warnings[] = "Route cache exists - run 'php artisan route:clear'";
}

// Display results
echo "VERIFICATION RESULTS:\n";
echo "============================================================\n\n";

if (!empty($success)) {
    echo "[SUCCESS] Passed Checks:\n";
    foreach ($success as $msg) {
        echo "  ✓ {$msg}\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "[WARNING] Warnings:\n";
    foreach ($warnings as $msg) {
        echo "  ⚠ {$msg}\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "[ERROR] Failed Checks:\n";
    foreach ($errors as $msg) {
        echo "  ✗ {$msg}\n";
    }
    echo "\n";
}

// Summary
echo "============================================================\n";
echo "SUMMARY:\n";
echo "============================================================\n";
echo "Passed: " . count($success) . "\n";
echo "Warnings: " . count($warnings) . "\n";
echo "Errors: " . count($errors) . "\n\n";

if (empty($errors)) {
    echo "[SUCCESS] Installation appears to be correct!\n";
    echo "You can test by completing a sale.\n\n";
    
    if (!empty($warnings)) {
        echo "[NOTE] Some warnings were found. Review them above.\n\n";
    }
} else {
    echo "[FAIL] Installation has errors. Please fix them before use.\n\n";
    echo "Run 'php install_auto_print.php' to fix issues automatically.\n\n";
}

echo "============================================================\n";
echo "\n";



