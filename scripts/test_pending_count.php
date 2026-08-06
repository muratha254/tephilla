<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$deferFrom = App\Services\EnsureSaleSupplierLedgerService::LEDGER_DEFER_FROM_DATE;

$start = microtime(true);
$pending = DB::table('penjualan_detail as pd')
    ->join('penjualan as pj', 'pd.id_penjualan', '=', 'pj.id_penjualan')
    ->whereRaw('DATE(COALESCE(pj.saledate, pj.created_at)) >= ?', [$deferFrom])
    ->where(function ($iq) {
        $iq->whereNull('pd.item_confirmation_status')
            ->orWhere('pd.item_confirmation_status', 'pending')
            ->orWhere('pd.item_confirmation_status', 'defect');
    })
    ->count();
echo "Pending sale lines since defer: {$pending} in " . round(microtime(true) - $start, 3) . "s\n";
