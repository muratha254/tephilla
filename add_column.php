<?php
/**
 * Script to add driver_commission_rate column to setting table
 * Run this file directly in browser or via command line: php add_column.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    // Check if column already exists
    if (Schema::hasColumn('setting', 'driver_commission_rate')) {
        echo "Column 'driver_commission_rate' already exists!\n";
        exit(0);
    }
    
    // Add the column
    DB::statement("ALTER TABLE `setting` ADD COLUMN `driver_commission_rate` DECIMAL(5,2) DEFAULT 0 AFTER `diskon`");
    
    // Verify it was added
    if (Schema::hasColumn('setting', 'driver_commission_rate')) {
        echo "SUCCESS: Column 'driver_commission_rate' has been added to the 'setting' table!\n";
    } else {
        echo "ERROR: Column was not added. Please check database permissions.\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}






