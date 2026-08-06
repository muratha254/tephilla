<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$deferFrom = App\Services\EnsureSaleSupplierLedgerService::LEDGER_DEFER_FROM_DATE;
$start = microtime(true);
$n = DB::table('pembelian as pb')
    ->join('supplier as s', 'pb.id_supplier', '=', 's.id_supplier')
    ->where('pb.purchasedate2', '>=', $deferFrom)
    ->whereRaw('pb.total_harga - COALESCE(pb.bayar, 0) > 0')
    ->where(function ($cash) {
        $cash->whereRaw('UPPER(TRIM(s.mop)) = ?', ['CASH'])
            ->orWhereRaw("s.nama LIKE '% (Cash)'");
    })
    ->count();
echo "Outstanding cash pembelian since defer: {$n} in " . round(microtime(true) - $start, 3) . "s\n";
