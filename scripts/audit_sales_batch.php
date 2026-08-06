<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use Illuminate\Support\Facades\DB;

$receipts = [];
for ($i = 1471; $i <= 1486; $i++) {
    $receipts[] = 'U' . $i;
}

$expected = [
    'U1486' => ['items' => 21, 'bayar' => 26520, 'payment' => 'Cash', 'discount' => 0],
    'U1485' => ['items' => 9, 'bayar' => 15660, 'payment' => 'Card', 'discount' => 0],
    'U1484' => ['items' => 3, 'bayar' => 6540, 'payment' => 'Card', 'discount' => 0],
    'U1483' => ['items' => 10, 'bayar' => 11710, 'payment' => 'Cash', 'discount' => 0],
    'U1482' => ['items' => 1, 'bayar' => 780, 'payment' => 'Cash', 'discount' => 0],
    'U1481' => ['items' => 6, 'bayar' => 8110, 'payment' => 'Cash', 'discount' => 0],
    'U1480' => ['items' => 7, 'bayar' => 3730, 'payment' => 'Cash', 'discount' => 0],
    'U1479' => ['items' => 2, 'bayar' => 1170, 'payment' => 'Cash', 'discount' => 0],
    'U1478' => ['items' => 1, 'bayar' => 5224, 'payment' => 'Mpesa', 'discount' => 20],
    'U1477' => ['items' => 9, 'bayar' => 13350, 'payment' => 'Card', 'discount' => 0],
    'U1476' => ['items' => 1, 'bayar' => 4830, 'payment' => 'Card', 'discount' => 0],
    'U1475' => ['items' => 8, 'bayar' => 41980, 'payment' => 'Card', 'discount' => 0],
    'U1474' => ['items' => 1, 'bayar' => 1830, 'payment' => 'Card', 'discount' => 0],
    'U1473' => ['items' => 4, 'bayar' => 14090, 'payment' => 'Card', 'discount' => 0],
    'U1472' => ['items' => 5, 'bayar' => 12160, 'payment' => 'Cash', 'discount' => 390],
    'U1486' => ['items' => 21, 'bayar' => 26520, 'payment' => 'Cash', 'discount' => 0],
    'U1471' => ['items' => 1, 'bayar' => 3920, 'payment' => 'Card', 'discount' => 0],
];

// fix duplicate U1486 - rebuild expected cleanly
$expected = [
    'U1471' => ['items' => 1, 'bayar' => 3920, 'payment' => 'Card'],
    'U1472' => ['items' => 5, 'bayar' => 12160, 'payment' => 'Cash'],
    'U1473' => ['items' => 4, 'bayar' => 14090, 'payment' => 'Card'],
    'U1474' => ['items' => 1, 'bayar' => 1830, 'payment' => 'Card'],
    'U1475' => ['items' => 8, 'bayar' => 41980, 'payment' => 'Card'],
    'U1476' => ['items' => 1, 'bayar' => 4830, 'payment' => 'Card'],
    'U1477' => ['items' => 9, 'bayar' => 13350, 'payment' => 'Card'],
    'U1478' => ['items' => 1, 'bayar' => 5224, 'payment' => 'Mpesa'],
    'U1479' => ['items' => 2, 'bayar' => 1170, 'payment' => 'Cash'],
    'U1480' => ['items' => 7, 'bayar' => 3730, 'payment' => 'Cash'],
    'U1481' => ['items' => 6, 'bayar' => 8110, 'payment' => 'Cash'],
    'U1482' => ['items' => 1, 'bayar' => 780, 'payment' => 'Cash'],
    'U1483' => ['items' => 10, 'bayar' => 11710, 'payment' => 'Cash'],
    'U1484' => ['items' => 3, 'bayar' => 6540, 'payment' => 'Card'],
    'U1485' => ['items' => 9, 'bayar' => 15660, 'payment' => 'Card'],
    'U1486' => ['items' => 21, 'bayar' => 26520, 'payment' => 'Cash'],
];

$sales = Penjualan::query()
    ->whereIn('receiptno', $receipts)
    ->orderBy('receiptno')
    ->get();

echo "=== Sales audit: U1471–U1486 (user list 22/06/2026) ===\n\n";

$totalBayar = 0;
$issues = [];
$missing = [];

foreach ($receipts as $rcpt) {
    $sale = $sales->first(fn ($s) => strtoupper($s->receiptno) === $rcpt);
    if (! $sale) {
        $missing[] = $rcpt;
        echo "MISSING: {$rcpt}\n";
        continue;
    }

    $lineCount = PenjualanDetail::where('id_penjualan', $sale->id_penjualan)->count();
    $lineQty = (int) PenjualanDetail::where('id_penjualan', $sale->id_penjualan)->sum('jumlah');
    $detailSubtotal = (float) PenjualanDetail::where('id_penjualan', $sale->id_penjualan)->sum('subtotal');
    $exp = $expected[$rcpt] ?? null;

    $saledate = $sale->saledate ? (string) $sale->saledate : '';
    $created = $sale->created_at ? $sale->created_at->format('Y-m-d H:i:s') : '';
    $createdDay = $sale->created_at ? $sale->created_at->format('Y-m-d') : '';
    $status = $sale->status ?? 'n/a';
    $confirm = $sale->confirmation_status ?? 'n/a';
    $payment = $sale->payment_method ?? '';
    $bayar = (float) ($sale->bayar ?? 0);
    $totalItem = (int) ($sale->total_item ?? 0);
    $totalHarga = (float) ($sale->total_harga ?? 0);
    $diskon = (float) ($sale->diskon ?? 0);
    $discountAmt = (float) ($sale->discount_amount ?? 0);

    $totalBayar += $bayar;

    $flags = [];
    if ($saledate !== '2026-06-22') {
        $flags[] = "saledate={$saledate} (not 2026-06-22)";
    }
    if ($createdDay !== '' && $createdDay !== $saledate) {
        $flags[] = "created_day={$createdDay}≠saledate";
    }
    if ($exp && $totalItem !== $exp['items']) {
        $flags[] = "qty={$totalItem} expected {$exp['items']}";
    }
    if ($exp && abs($bayar - $exp['bayar']) > 0.01) {
        $flags[] = 'bayar=' . number_format($bayar, 2) . ' expected ' . number_format($exp['bayar'], 2);
    }
    if ($exp && strcasecmp($payment, $exp['payment']) !== 0) {
        $flags[] = "payment={$payment} expected {$exp['payment']}";
    }
    if ($status !== 'completed') {
        $flags[] = "status={$status}";
    }
    if ($lineCount === 0) {
        $flags[] = 'NO LINE ITEMS';
    }
    if (abs($detailSubtotal - $totalHarga) > 1 && $totalHarga > 0) {
        $flags[] = 'detail_sum=' . number_format($detailSubtotal, 0) . ' vs total_harga=' . number_format($totalHarga, 0);
    }

    $flagStr = $flags ? ' *** ' . implode('; ', $flags) : ' OK';
    echo sprintf(
        "%s id=%d saledate=%s created=%s status=%s confirm=%s pay=%s items=%d bayar=%s lines=%d line_qty=%d%s\n",
        $rcpt,
        $sale->id_penjualan,
        $saledate,
        $created,
        $status,
        $confirm,
        $payment,
        $totalItem,
        number_format($bayar, 2),
        $lineCount,
        $lineQty,
        $flagStr
    );

    if ($flags) {
        $issues[$rcpt] = $flags;
    }
}

echo "\n--- Summary ---\n";
echo 'Found: ' . $sales->count() . ' / ' . count($receipts) . " sales\n";
echo 'Total bayar (sum): Ksh ' . number_format($totalBayar, 2) . "\n";
echo 'Expected total:    Ksh 171,604.00' . (abs($totalBayar - 171604) < 1 ? ' ✓' : ' MISMATCH') . "\n";

if ($missing) {
    echo 'Missing receipts: ' . implode(', ', $missing) . "\n";
}

if ($issues) {
    echo "\nIssues (" . count($issues) . "):\n";
    foreach ($issues as $rcpt => $flags) {
        echo "  {$rcpt}: " . implode('; ', $flags) . "\n";
    }
} else {
    echo "\nAll 16 sales match the list (date, totals, payment, completed).\n";
}

// Same receipts on other dates?
echo "\n--- Duplicate receipt numbers on OTHER dates ---\n";
$dupes = DB::table('penjualan')
    ->whereIn('receiptno', $receipts)
    ->select('receiptno', 'saledate', 'id_penjualan', 'bayar')
    ->orderBy('receiptno')
    ->orderBy('saledate')
    ->get();
$grouped = $dupes->groupBy('receiptno');
foreach ($grouped as $rcpt => $rows) {
    if ($rows->count() > 1) {
        echo "{$rcpt}: multiple rows\n";
        foreach ($rows as $r) {
            echo "  id={$r->id_penjualan} saledate={$r->saledate} bayar={$r->bayar}\n";
        }
    }
}
if ($grouped->every(fn ($rows) => $rows->count() === 1)) {
    echo "None — each receipt exists once only.\n";
}

// Dan's sales same calendar day May 22?
echo "\n--- Same receipts: any also filed under 2026-05-22? ---\n";
$may = Penjualan::whereIn('receiptno', $receipts)->whereDate('saledate', '2026-05-22')->count();
echo "Count on 2026-05-22: {$may} (should be 0 for this batch)\n";

echo "\n--- Cashier / user ---\n";
$userIds = $sales->pluck('id_user')->unique();
foreach ($userIds as $uid) {
    $name = DB::table('users')->where('id', $uid)->value('name');
    $cnt = $sales->where('id_user', $uid)->count();
    echo "  user id={$uid} ({$name}): {$cnt} sales\n";
}

echo "\n--- Confirmation status breakdown ---\n";
foreach ($sales->groupBy('confirmation_status') as $st => $grp) {
    echo "  " . ($st ?: 'null') . ': ' . $grp->count() . "\n";
}
