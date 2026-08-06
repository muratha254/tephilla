<?php

/**
 * Undo a bad import/reimport for one receipt + sales date (deletes sale, restores stock, removes supplier ledger).
 * Usage: php scripts/undo_reimport_sale.php A44576 2025-08-07
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Account;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Services\EnsureSaleSupplierLedgerService;
use App\Services\SaleSupplierLedgerRemovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$receipt = strtoupper(trim($argv[1] ?? ''));
$date = trim($argv[2] ?? '');

if ($receipt === '' || $date === '') {
    fwrite(STDERR, "Usage: php scripts/undo_reimport_sale.php RECEIPT YYYY-MM-DD\n");
    exit(1);
}

$penjualan = Penjualan::query()
    ->whereRaw('UPPER(TRIM(receiptno)) = ?', [$receipt])
    ->whereDate('saledate', $date)
    ->first();

if (! $penjualan) {
    fwrite(STDERR, "No sale found for receipt {$receipt} on {$date}.\n");
    exit(1);
}

$ledger = app(EnsureSaleSupplierLedgerService::class);
$removal = app(SaleSupplierLedgerRemovalService::class);
$preview = $removal->ledgerImpactForEntirePenjualan($penjualan, $ledger);

if ($preview['consignment_paid'] || $preview['cash_purchase_paid']) {
    fwrite(STDERR, ($preview['message'] ?? 'Cannot delete: supplier payments recorded.')."\n");
    exit(1);
}

$id = (int) $penjualan->id_penjualan;
$lineCount = PenjualanDetail::where('id_penjualan', $id)->count();

echo "Will delete sale #{$id} receipt={$penjualan->receiptno} date={$penjualan->saledate} total={$penjualan->total_harga} lines={$lineCount}\n";

DB::transaction(function () use ($penjualan, $removal) {
    if (Schema::hasColumn('penjualan', 'status') && $penjualan->status === 'completed') {
        $paymentMethod = $penjualan->payment_method ?? 'Cash';
        $account = Account::getByName($paymentMethod);
        if ($account) {
            $account->deductAmount((float) $penjualan->bayar);
        }
    }

    $removal->removeAllUnpaidLedgerForPenjualan($penjualan);

    $details = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
    foreach ($details as $item) {
        $produk = Produk::find($item->id_produk);
        if ($produk) {
            $produk->stok += (int) $item->jumlah;
            $produk->save();
        }
        $idProduk = (int) $item->id_produk;
        $item->delete();
        Produk::deleteOrphanPosQuickAddProduct($idProduk);
    }

    $penjualan->delete();
});

echo "Deleted sale #{$id} ({$receipt} on {$date}). Stock restored; unpaid consignment/cash links removed.\n";
echo "You can reimport this receipt with the corrected commodity mapping.\n";
