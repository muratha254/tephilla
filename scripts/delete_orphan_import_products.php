<?php

/**
 * Delete orphan products created by a bad sales import (e.g. IMP-A44576-1 … Imported Item N).
 * Usage: php scripts/delete_orphan_import_products.php A44576
 *        php scripts/delete_orphan_import_products.php --code-prefix=IMP-A44576-
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PembelianDetail;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\ProdukHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$prefix = 'IMP-A44576-';
$receipt = '';

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--code-prefix=')) {
        $prefix = substr($arg, strlen('--code-prefix='));
    } elseif ($receipt === '' && ! str_starts_with($arg, '--')) {
        $receipt = strtoupper(trim($arg));
    }
}

if ($receipt !== '') {
    $prefix = 'IMP-' . $receipt . '-';
}

if ($prefix === '') {
    fwrite(STDERR, "Usage: php scripts/delete_orphan_import_products.php [RECEIPT]\n");
    fwrite(STDERR, "   or: php scripts/delete_orphan_import_products.php --code-prefix=IMP-A44576-\n");
    exit(1);
}

$products = Produk::query()
    ->where('kode_produk', 'like', $prefix . '%')
    ->orderBy('kode_produk')
    ->get();

if ($products->isEmpty()) {
    fwrite(STDERR, "No products found with kode_produk like {$prefix}%.\n");
    exit(1);
}

echo 'Found ' . $products->count() . " product(s) matching {$prefix}%\n";

$blocked = [];
$deleted = 0;

DB::transaction(function () use ($products, &$blocked, &$deleted) {
    foreach ($products as $produk) {
        $id = (int) $produk->id_produk;

        if (PenjualanDetail::where('id_produk', $id)->exists()) {
            $blocked[] = "{$produk->kode_produk} (id {$id}): still on sale lines";

            continue;
        }

        if (Schema::hasTable('pembelian_detail')) {
            PembelianDetail::where('id_produk', $id)->delete();
        }

        if (Schema::hasTable('produk_history')) {
            ProdukHistory::where('id_produk', $id)->delete();
        }

        if (Schema::hasTable('produk_edit_log')) {
            DB::table('produk_edit_log')->where('id_produk', $id)->delete();
        }

        if (Schema::hasTable('supplier_withdrawals')) {
            DB::table('supplier_withdrawals')->where('produk_id', $id)->delete();
        }

        if (Schema::hasTable('consignment_gap_suppressions')) {
            DB::table('consignment_gap_suppressions')->where('produk_id', $id)->delete();
        }

        $produk->delete();
        $deleted++;
        echo "Deleted {$produk->kode_produk} (id {$id}) — {$produk->nama_produk}\n";
    }
});

if ($blocked !== []) {
    fwrite(STDERR, "\nSkipped (still referenced on sales):\n");
    foreach ($blocked as $line) {
        fwrite(STDERR, "  - {$line}\n");
    }
}

echo "\nDeleted {$deleted} product(s).\n";

if ($blocked !== []) {
    exit(2);
}

exit(0);
