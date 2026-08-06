<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$receipt = strtoupper(trim($argv[1] ?? 'U1483'));
$date = trim($argv[2] ?? '2026-05-22');

echo "=== Search receipt: {$receipt} ===\n\n";

$exact = Penjualan::query()
    ->whereRaw('UPPER(TRIM(receiptno)) = ?', [$receipt])
    ->get(['id_penjualan', 'receiptno', 'saledate', 'created_at', 'status', 'bayar', 'total_harga', 'sale_type']);

echo 'Exact match: ' . $exact->count() . "\n";
foreach ($exact as $s) {
    echo "  id={$s->id_penjualan} receipt={$s->receiptno} saledate={$s->saledate} created={$s->created_at} status={$s->status} bayar={$s->bayar} type={$s->sale_type}\n";
}

$like = Penjualan::query()
    ->whereRaw('UPPER(TRIM(receiptno)) LIKE ?', ['%' . preg_replace('/[^A-Z0-9]/', '', $receipt) . '%'])
    ->orderByDesc('saledate')
    ->limit(15)
    ->get(['id_penjualan', 'receiptno', 'saledate', 'status', 'bayar']);

echo "\nLike match (1483): " . $like->count() . "\n";
foreach ($like as $s) {
    echo "  id={$s->id_penjualan} receipt={$s->receiptno} saledate={$s->saledate} status={$s->status}\n";
}

if ($date !== '') {
    $onDate = Penjualan::query()
        ->whereDate('saledate', $date)
        ->whereRaw('UPPER(TRIM(receiptno)) LIKE ?', ['%1483%'])
        ->get(['id_penjualan', 'receiptno', 'saledate', 'status']);
    echo "\nOn date {$date} with 1483: " . $onDate->count() . "\n";
    foreach ($onDate as $s) {
        echo "  id={$s->id_penjualan} receipt={$s->receiptno}\n";
    }

    $uOnDate = Penjualan::query()
        ->whereDate('saledate', $date)
        ->whereRaw('UPPER(TRIM(receiptno)) LIKE ?', ['U%'])
        ->orderBy('receiptno')
        ->get(['id_penjualan', 'receiptno', 'status', 'bayar']);
    echo "\nAll U* receipts on {$date}: " . $uOnDate->count() . "\n";
    foreach ($uOnDate as $s) {
        echo "  {$s->receiptno} id={$s->id_penjualan} status={$s->status} bayar={$s->bayar}\n";
    }
}

// Nearby U receipts
$near = Penjualan::query()
    ->whereRaw('UPPER(TRIM(receiptno)) REGEXP ?', ['^U148[0-9]$'])
    ->orderBy('receiptno')
    ->get(['id_penjualan', 'receiptno', 'saledate', 'status']);
echo "\nU1480-U1489 receipts in DB: " . $near->count() . "\n";
foreach ($near as $s) {
    echo "  {$s->receiptno} date={$s->saledate} status={$s->status} id={$s->id_penjualan}\n";
}

// Orphan details (sale header deleted but lines remain)
$orphanDetails = PenjualanDetail::query()
    ->whereIn('id_penjualan', function ($q) use ($receipt) {
        $q->select('id_penjualan')->from('penjualan')->whereRaw('UPPER(TRIM(receiptno)) = ?', [$receipt]);
    })
    ->count();
echo "\nDetail lines for exact receipt sale ids: {$orphanDetails}\n";

// Active/suspended incomplete with that receipt
if (Schema::hasColumn('penjualan', 'status')) {
    $incomplete = Penjualan::query()
        ->whereRaw('UPPER(TRIM(receiptno)) = ?', [$receipt])
        ->whereIn('status', ['active', 'suspended', 'edit_initiated'])
        ->get(['id_penjualan', 'status', 'bayar', 'created_at']);
    echo "\nIncomplete status exact: " . $incomplete->count() . "\n";
    foreach ($incomplete as $s) {
        echo "  id={$s->id_penjualan} status={$s->status} bayar={$s->bayar}\n";
    }
}

// Laravel log hints (last lines mentioning U1483)
$logPath = storage_path('logs/laravel.log');
if (is_readable($logPath)) {
    echo "\n=== Log lines mentioning U1483 (last 5) ===\n";
    $lines = [];
    $fh = fopen($logPath, 'r');
    if ($fh) {
        while (($line = fgets($fh)) !== false) {
            if (stripos($line, 'U1483') !== false || stripos($line, '1483') !== false) {
                $lines[] = trim($line);
                if (count($lines) > 20) {
                    array_shift($lines);
                }
            }
        }
        fclose($fh);
    }
    foreach (array_slice($lines, -5) as $line) {
        echo substr($line, 0, 300) . "\n";
    }
}
