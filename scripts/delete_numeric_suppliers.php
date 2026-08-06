<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $argv, true);
$placeholderName = 'IMPORT PLACEHOLDER (numeric suppliers removed)';

$numericQuery = Supplier::query()->whereRaw("TRIM(nama) REGEXP '^[0-9]+$'");
$total = (int) $numericQuery->count();
echo "Numeric-name suppliers: {$total}\n";

if ($total === 0) {
    exit(0);
}

$withProducts = DB::table('supplier as s')
    ->whereRaw("TRIM(s.nama) REGEXP '^[0-9]+$'")
    ->whereExists(function ($q) {
        $q->select(DB::raw(1))->from('produk')->whereColumn('produk.id_supplier', 's.id_supplier');
    })
    ->pluck('s.id_supplier')
    ->map(fn ($id) => (int) $id)
    ->all();

$withoutProducts = DB::table('supplier as s')
    ->whereRaw("TRIM(s.nama) REGEXP '^[0-9]+$'")
    ->whereNotExists(function ($q) {
        $q->select(DB::raw(1))->from('produk')->whereColumn('produk.id_supplier', 's.id_supplier');
    })
    ->pluck('s.id_supplier')
    ->map(fn ($id) => (int) $id)
    ->all();

echo 'Without products (safe direct delete): ' . count($withoutProducts) . PHP_EOL;
echo 'With products (reassign produk first): ' . count($withProducts) . PHP_EOL;

if ($dryRun) {
    exit(0);
}

$placeholder = Supplier::query()->firstOrCreate(
    ['nama' => $placeholderName],
    [
        'alamat' => '',
        'telepon' => '',
        'mop' => 'Cash',
        'opening_balance' => 0,
        'opening_balance_paid' => 0,
    ]
);
$placeholderId = (int) $placeholder->id_supplier;
echo "Placeholder supplier #{$placeholderId}\n";

$deleted = 0;

foreach (array_chunk($withoutProducts, 200) as $i => $chunk) {
    $chunk = array_values(array_filter($chunk, fn ($id) => $id !== $placeholderId));
    if ($chunk === []) {
        continue;
    }
    $count = Supplier::query()->whereIn('id_supplier', $chunk)->delete();
    $deleted += $count;
    echo 'Deleted chunk ' . ($i + 1) . " (no products): {$count}\n";
}

if ($withProducts !== []) {
    $withProducts = array_values(array_filter($withProducts, fn ($id) => $id !== $placeholderId));
    $moved = 0;
    foreach (array_chunk($withProducts, 50) as $i => $chunk) {
        $moved += DB::table('produk')->whereIn('id_supplier', $chunk)->update(['id_supplier' => $placeholderId]);
        echo 'Moved products chunk ' . ($i + 1) . PHP_EOL;
    }
    echo "Moved {$moved} product(s) to placeholder.\n";

    foreach (array_chunk($withProducts, 200) as $i => $chunk) {
        $count = Supplier::query()->whereIn('id_supplier', $chunk)->delete();
        $deleted += $count;
        echo 'Deleted chunk ' . ($i + 1) . " (had products): {$count}\n";
    }
}

echo "Total deleted: {$deleted}\n";
$remaining = (int) Supplier::query()->whereRaw("TRIM(nama) REGEXP '^[0-9]+$'")->count();
echo "Remaining numeric-name suppliers: {$remaining}\n";
