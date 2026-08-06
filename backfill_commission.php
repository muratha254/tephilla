<?php
/**
 * Script to backfill driver commission for existing sales
 * Run this file directly in browser or via command line: php backfill_commission.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Penjualan;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

try {
    // Check if column exists
    if (!Schema::hasColumn('penjualan', 'driver_commission')) {
        echo "ERROR: Column 'driver_commission' does not exist in penjualan table!\n";
        echo "Please run add_penjualan_commission_column.php first.\n";
        exit(1);
    }
    
    $setting = Setting::first();
    $commissionRate = $setting->driver_commission_rate ?? 0;
    
    if ($commissionRate <= 0) {
        echo "WARNING: Commission rate is 0 or not set. Please set it in Settings first.\n";
        echo "Current rate: " . $commissionRate . "%\n";
    }
    
    echo "Starting to backfill commission for existing sales...\n";
    echo "Commission rate: " . $commissionRate . "%\n\n";
    
    // Get all completed sales
    $sales = Penjualan::query();
    if (Schema::hasColumn('penjualan', 'status')) {
        $sales->where('status', 'completed');
    }
    $sales = $sales->get();
    
    $updated = 0;
    $skipped = 0;
    
    foreach ($sales as $sale) {
        // Skip if commission already calculated
        if ($sale->driver_commission > 0) {
            $skipped++;
            continue;
        }
        
        // Calculate commission
        $tax = $sale->tax ?? 0;
        $subtotalBeforeVat = $sale->bayar - $tax;
        
        if ($commissionRate > 0 && $subtotalBeforeVat > 0) {
            $driverCommission = round($subtotalBeforeVat * ($commissionRate / 100), 2);
            
            // Update the sale
            DB::table('penjualan')
                ->where('id_penjualan', $sale->id_penjualan)
                ->update(['driver_commission' => $driverCommission]);
            
            $updated++;
            echo "Updated sale #{$sale->id_penjualan} (Receipt: {$sale->receiptno}): Commission = Ksh " . number_format($driverCommission, 2) . "\n";
        } else {
            $skipped++;
        }
    }
    
    echo "\n";
    echo "Backfill completed!\n";
    echo "Updated: {$updated} sales\n";
    echo "Skipped: {$skipped} sales (already had commission or rate is 0)\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}






