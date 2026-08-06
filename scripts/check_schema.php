<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo Schema::hasColumn('pembelian', 'penjualan_id') ? "pembelian.penjualan_id=yes\n" : "pembelian.penjualan_id=no\n";
