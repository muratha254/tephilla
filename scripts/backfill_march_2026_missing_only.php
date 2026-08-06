<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$from = '2026-03-01';
$to = '2026-03-31';

$missingSuppliers = DB::table('penjualan as p')
    ->join('penjualan_detail as pd', 'pd.id_penjualan', '=', 'p.id_penjualan')
    ->join('produk as pr', 'pr.id_produk', '=', 'pd.id_produk')
    ->join('supplier as s', 's.id_supplier', '=', 'pr.id_supplier')
    ->leftJoin('invoice_items as ii', function ($join) {
        $join->on('ii.penjualan_id', '=', 'p.id_penjualan')
            ->on('ii.produk_id', '=', 'pr.id_produk')
            ->on('ii.quantity', '=', 'pd.jumlah');
    })
    ->where('p.status', 'completed')
    ->whereRaw('DATE(COALESCE(p.saledate, p.created_at)) BETWEEN ? AND ?', [$from, $to])
    ->whereRaw("UPPER(TRIM(COALESCE(s.mop, ''))) = 'CONSIGNMENT'")
    ->whereRaw('COALESCE(pr.is_incomplete, 0) = 0')
    ->where('pd.jumlah', '>', 0)
    ->whereRaw('(COALESCE(pr.harga_beli, 0) * pd.jumlah) > 0')
    ->whereNull('ii.id')
    ->groupBy('s.id_supplier')
    ->orderBy('s.id_supplier')
    ->select('s.id_supplier', DB::raw('COUNT(*) as missing_lines'))
    ->get();

echo 'Suppliers with missing March lines: '.$missingSuppliers->count().PHP_EOL;

$totalCreated = 0;
$totalSkipped = 0;
$processed = 0;

foreach ($missingSuppliers as $row) {
    $supplierId = (int) $row->id_supplier;
    if ($supplierId <= 0) {
        continue;
    }
    $processed++;
    echo PHP_EOL."=== Supplier {$supplierId} ({$processed}/".$missingSuppliers->count()."; initial missing {$row->missing_lines}) ===".PHP_EOL;

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

$remaining = DB::table('penjualan as p')
    ->join('penjualan_detail as pd', 'pd.id_penjualan', '=', 'p.id_penjualan')
    ->join('produk as pr', 'pr.id_produk', '=', 'pd.id_produk')
    ->join('supplier as s', 's.id_supplier', '=', 'pr.id_supplier')
    ->leftJoin('invoice_items as ii', function ($join) {
        $join->on('ii.penjualan_id', '=', 'p.id_penjualan')
            ->on('ii.produk_id', '=', 'pr.id_produk')
            ->on('ii.quantity', '=', 'pd.jumlah');
    })
    ->where('p.status', 'completed')
    ->whereRaw('DATE(COALESCE(p.saledate, p.created_at)) BETWEEN ? AND ?', [$from, $to])
    ->whereRaw("UPPER(TRIM(COALESCE(s.mop, ''))) = 'CONSIGNMENT'")
    ->whereRaw('COALESCE(pr.is_incomplete, 0) = 0')
    ->where('pd.jumlah', '>', 0)
    ->whereRaw('(COALESCE(pr.harga_beli, 0) * pd.jumlah) > 0')
    ->whereNull('ii.id')
    ->count();

echo PHP_EOL.'=== Missing-only Backfill Summary ==='.PHP_EOL;
echo "Suppliers processed: {$processed}".PHP_EOL;
echo "Total created: {$totalCreated}".PHP_EOL;
echo "Total skipped: {$totalSkipped}".PHP_EOL;
echo "Remaining missing lines: {$remaining}".PHP_EOL;
