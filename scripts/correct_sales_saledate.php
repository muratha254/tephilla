<?php

/**
 * Correct sales date for a batch of receipts (e.g. U1471–U1486 → 2026-05-22).
 * Usage: php scripts/correct_sales_saledate.php --from=2026-06-22 --to=2026-05-22 U1471 U1472 ... U1486
 *        php scripts/correct_sales_saledate.php --from=2026-06-22 --to=2026-05-22 --range=1471-1486
 *        php scripts/correct_sales_saledate.php ... --dry-run
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dryRun = in_array('--dry-run', $argv, true);
$fromDate = '2026-06-22';
$toDate = '2026-05-22';
$receipts = [];

foreach ($argv as $i => $arg) {
    if ($i === 0) {
        continue;
    }
    if ($arg === '--dry-run') {
        continue;
    }
    if (str_starts_with($arg, '--from=')) {
        $fromDate = substr($arg, 7);
        continue;
    }
    if (str_starts_with($arg, '--to=')) {
        $toDate = substr($arg, 5);
        continue;
    }
    if (str_starts_with($arg, '--range=')) {
        $range = substr($arg, 8);
        if (preg_match('/^(\d+)-(\d+)$/', $range, $m)) {
            for ($n = (int) $m[1]; $n <= (int) $m[2]; $n++) {
                $receipts[] = 'U' . $n;
            }
        }
        continue;
    }
    $receipts[] = strtoupper(trim($arg));
}

if ($receipts === []) {
    fwrite(STDERR, "Usage: php scripts/correct_sales_saledate.php [--from=Y-m-d] [--to=Y-m-d] [--range=1471-1486] [--dry-run] RECEIPT...\n");
    exit(1);
}

$toDateTime = $toDate . ' 00:00:00';

$sales = Penjualan::query()
    ->whereIn('receiptno', $receipts)
    ->orderBy('receiptno')
    ->get();

if ($sales->count() !== count($receipts)) {
    $found = $sales->pluck('receiptno')->map(fn ($r) => strtoupper($r))->all();
    $missing = array_diff($receipts, $found);
    fwrite(STDERR, 'Missing receipts: ' . implode(', ', $missing) . "\n");
    exit(1);
}

$wrongDate = $sales->filter(fn ($s) => ($s->saledate ? (string) $s->saledate : '') !== $fromDate);
if ($wrongDate->isNotEmpty()) {
    fwrite(STDERR, "These receipts are not on {$fromDate}:\n");
    foreach ($wrongDate as $s) {
        fwrite(STDERR, "  {$s->receiptno} saledate={$s->saledate}\n");
    }
    exit(1);
}

$ids = $sales->pluck('id_penjualan')->all();
$productIds = PenjualanDetail::whereIn('id_penjualan', $ids)->pluck('id_produk')->unique()->all();

echo ($dryRun ? '[DRY RUN] ' : '') . "Correcting " . count($receipts) . " sales: {$fromDate} → {$toDate}\n\n";

foreach ($sales as $s) {
    echo "  {$s->receiptno} id={$s->id_penjualan} bayar={$s->bayar}\n";
}

$invoiceItemCount = 0;
if (Schema::hasColumn('invoice_items', 'penjualan_id')) {
    $invoiceItemCount = DB::table('invoice_items')->whereIn('penjualan_id', $ids)->count();
}
echo "\nLinked invoice_items: {$invoiceItemCount}\n";

$pembelianIds = [];
if (Schema::hasColumn('pembelian', 'purchasedate2') && $productIds !== []) {
    $pembelianIds = DB::table('pembelian_detail')
        ->join('pembelian', 'pembelian.id_pembelian', '=', 'pembelian_detail.id_pembelian')
        ->whereIn('pembelian_detail.id_produk', $productIds)
        ->whereDate('pembelian.purchasedate2', $fromDate)
        ->distinct()
        ->pluck('pembelian.id_pembelian')
        ->all();
}
echo 'Pembelian headers with purchasedate2=' . $fromDate . ' touching these products: ' . count($pembelianIds) . "\n";

if ($dryRun) {
    echo "\nDry run complete — no changes written.\n";
    exit(0);
}

DB::transaction(function () use ($sales, $ids, $toDate, $toDateTime, $fromDate, $productIds, &$pembelianIds) {
    foreach ($sales as $s) {
        DB::table('penjualan')->where('id_penjualan', $s->id_penjualan)->update([
            'saledate' => $toDate,
            'created_at' => $toDateTime,
            'updated_at' => now(),
        ]);
    }

    if (Schema::hasColumn('invoice_items', 'penjualan_id')) {
        DB::table('invoice_items')
            ->whereIn('penjualan_id', $ids)
            ->update([
                'created_at' => $toDateTime,
                'updated_at' => $toDateTime,
            ]);

        $invoiceIds = DB::table('invoice_items')
            ->whereIn('penjualan_id', $ids)
            ->pluck('invoice_id')
            ->unique()
            ->all();

        foreach ($invoiceIds as $invId) {
            DB::table('invoices')->where('id', $invId)->update([
                'created_at' => $toDateTime,
                'updated_at' => $toDateTime,
            ]);
        }
    }

    if ($pembelianIds !== [] && Schema::hasColumn('pembelian', 'purchasedate2')) {
        DB::table('pembelian')
            ->whereIn('id_pembelian', $pembelianIds)
            ->whereDate('purchasedate2', $fromDate)
            ->update([
                'purchasedate2' => $toDate,
                'created_at' => $toDateTime,
                'updated_at' => now(),
            ]);
    }
});

echo "\nDone. Updated penjualan.saledate + created_at for " . count($ids) . " sales.\n";
if ($invoiceItemCount > 0) {
    echo "Updated {$invoiceItemCount} invoice_items (and related invoices) to {$toDate}.\n";
}
if ($pembelianIds !== []) {
    echo 'Updated ' . count($pembelianIds) . " pembelian purchasedate2 from {$fromDate} to {$toDate}.\n";
}

// Verify
$check = Penjualan::whereIn('receiptno', $sales->pluck('receiptno'))->get(['receiptno', 'saledate']);
$bad = $check->filter(fn ($s) => (string) $s->saledate !== $toDate);
if ($bad->isNotEmpty()) {
    fwrite(STDERR, "Verification failed for: " . $bad->pluck('receiptno')->implode(', ') . "\n");
    exit(2);
}
echo "Verified: all receipts now saledate={$toDate}.\n";
