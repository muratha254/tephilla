<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;

$receipt = $argv[1] ?? 'A44576';
$date = $argv[2] ?? '2025-08-07';

$sales = Penjualan::query()
    ->whereRaw('UPPER(TRIM(receiptno)) = ?', [strtoupper($receipt)])
    ->whereDate('saledate', $date)
    ->orderBy('id_penjualan')
    ->get();

if ($sales->isEmpty()) {
    $sales = Penjualan::query()
        ->whereRaw('UPPER(TRIM(receiptno)) = ?', [strtoupper($receipt)])
        ->orderBy('saledate')
        ->get();
}

foreach ($sales as $s) {
    echo "SALE id={$s->id_penjualan} receipt={$s->receiptno} date={$s->saledate} total={$s->total_harga} items={$s->total_item} status={$s->status}\n";
    $details = PenjualanDetail::with('produk')->where('id_penjualan', $s->id_penjualan)->orderBy('id_penjualan_detail')->get();
    foreach ($details as $d) {
        $name = $d->produk->nama_produk ?? '?';
        $code = $d->produk->kode_produk ?? '?';
        echo "  line {$d->id_penjualan_detail}: qty={$d->jumlah} subtotal={$d->subtotal} produk={$code} | {$name}\n";
    }
}
