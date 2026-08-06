<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$from = '2026-03-01';
$to = '2026-03-31';

$supplierIds = DB::table('penjualan as p')
    ->join('penjualan_detail as pd', 'pd.id_penjualan', '=', 'p.id_penjualan')
    ->join('produk as pr', 'pr.id_produk', '=', 'pd.id_produk')
    ->join('supplier as s', 's.id_supplier', '=', 'pr.id_supplier')
    ->whereRaw('DATE(COALESCE(p.saledate, p.created_at)) BETWEEN ? AND ?', [$from, $to])
    ->whereRaw("UPPER(TRIM(COALESCE(s.mop, ''))) = 'CONSIGNMENT'")
    ->distinct()
    ->orderBy('s.id_supplier')
    ->pluck('s.id_supplier')
    ->map(fn ($id) => (int) $id)
    ->filter(fn ($id) => $id > 0)
    ->values();

echo "March consignment suppliers in sales window: ".$supplierIds->count().PHP_EOL;

$totalCreated = 0;
$totalSkipped = 0;
$processed = 0;

foreach ($supplierIds as $supplierId) {
    $processed++;
    echo PHP_EOL."=== Supplier {$supplierId} ({$processed}/".$supplierIds->count().") ===".PHP_EOL;

    $status = Artisan::call('consignment:backfill-missing-items', [
        '--from' => $from,
        '--to' => $to,
        '--supplier-id' => $supplierId,
    ]);

    $output = Artisan::output();
    echo $output;

    if (preg_match('/Done\.\s+Created:\s*(\d+),\s*Skipped:\s*(\d+)/i', $output, $m)) {
        $totalCreated += (int) $m[1];
        $totalSkipped += (int) $m[2];
    }

    if ((int) $status !== 0) {
        echo "Supplier {$supplierId} ended with non-zero exit code: {$status}".PHP_EOL;
    }
}

echo PHP_EOL.'=== Batch Backfill Summary ==='.PHP_EOL;
echo "Suppliers processed: {$processed}".PHP_EOL;
echo "Total created: {$totalCreated}".PHP_EOL;
echo "Total skipped: {$totalSkipped}".PHP_EOL;
