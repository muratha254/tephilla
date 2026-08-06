<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Penjualan;
use App\Services\EnsureSaleSupplierLedgerService;
use Illuminate\Support\Facades\Schema;

$ledger = app(EnsureSaleSupplierLedgerService::class);

$query = Penjualan::query();
if (Schema::hasColumn('penjualan', 'saledate')) {
    $query->where('saledate', '>=', EnsureSaleSupplierLedgerService::LEDGER_DEFER_FROM_DATE);
}
if (Schema::hasColumn('penjualan', 'status')) {
    $query->where('status', 'completed');
}
if (Schema::hasColumn('penjualan', 'confirmation_status')) {
    $query->where('confirmation_status', 'confirmed');
}

$processed = 0;
$headersCreated = 0;
$linesAppended = 0;

foreach ($query->orderBy('id_penjualan')->cursor() as $penjualan) {
    if (! $ledger->saleDefersLedgerUntilConfirmed($penjualan)) {
        continue;
    }

    $processed++;
    $headersCreated += $ledger->fillCashPembelianIfAbsentForPenjualan($penjualan);
    $linesAppended += $ledger->appendCashPembelianDetailsForPenjualan($penjualan);
}

$ledger->forgetUnconfirmedCashPembelianDetailIdsCache();

echo "Confirmed deferred sales processed: {$processed}\n";
echo "Cash pembelian headers created: {$headersCreated}\n";
echo "Cash pembelian detail lines appended: {$linesAppended}\n";
