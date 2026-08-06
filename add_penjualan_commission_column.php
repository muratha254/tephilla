<?php
/**
 * Script to add driver_commission column to penjualan table
 * Run this file directly in browser or via command line: php add_penjualan_commission_column.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    // Check if column already exists
    if (Schema::hasColumn('penjualan', 'driver_commission')) {
        echo "Column 'driver_commission' already exists in penjualan table!\n";
        exit(0);
    }
    
    // Add the column
    DB::statement("ALTER TABLE `penjualan` ADD COLUMN `driver_commission` DECIMAL(15,2) DEFAULT 0 AFTER `tax`");
    
    // Verify it was added
    if (Schema::hasColumn('penjualan', 'driver_commission')) {
        echo "SUCCESS: Column 'driver_commission' has been added to the 'penjualan' table!\n";
        echo "You can now backfill commission for existing sales if needed.\n";
    } else {
        echo "ERROR: Column was not added. Please check database permissions.\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}






