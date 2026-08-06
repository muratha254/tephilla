<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Penjualan;

foreach (['2026-05-22', '2026-06-22'] as $d) {
    $sales = Penjualan::whereDate('saledate', $d)
        ->whereRaw("receiptno REGEXP '^U[0-9]+$'")
        ->orderBy('receiptno')
        ->get(['id_penjualan', 'receiptno', 'saledate', 'created_at', 'bayar']);

    echo "=== {$d} ({$sales->count()} U sales) ===\n";
    foreach ($sales as $s) {
        $created = $s->created_at ? $s->created_at->format('Y-m-d H:i:s') : '';
        $createdDay = $s->created_at ? $s->created_at->format('Y-m-d') : '';
        $mismatch = ($createdDay !== '' && $createdDay !== $s->saledate) ? ' CREATED≠SALEDATE' : '';
        echo "  {$s->receiptno} id={$s->id_penjualan} saledate={$s->saledate} created={$created} bayar={$s->bayar}{$mismatch}\n";
    }
    echo "\n";
}

// U1480-U1486 cluster (June in DB from earlier)
echo "=== U1480-U1489 detail ===\n";
$cluster = Penjualan::whereRaw("receiptno REGEXP '^U148[0-9]$'")
    ->orderBy('receiptno')
    ->get(['receiptno', 'saledate', 'created_at']);
foreach ($cluster as $s) {
    echo "{$s->receiptno} saledate={$s->saledate} created_date={$s->created_at->format('Y-m-d')}\n";
}
