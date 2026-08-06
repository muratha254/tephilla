<?php

/**
 * Dry run: completed sales in a date range vs consignment (invoice_items) and cash-generated (pembelian).
 *
 * Consignment: same rules as consignment:backfill-missing-items (CONSIGNMENT MOP, complete product, qty match).
 * Cash: per sale, multiset of (supplier_id, produk_id, jumlah) must be coverable by pembelian_detail rows
 *       on pembelian with same supplier and purchasedate2 = sale day (heuristic when no penjualan_id on pembelian).
 *
 * Usage:
 *   php scripts/dry_run_missing_consignment_cash_range.php
 *   php scripts/dry_run_missing_consignment_cash_range.php 2026-04-01 2026-04-13
 *   php scripts/dry_run_missing_consignment_cash_range.php 2026-04-01 2026-04-13 2026-04-12
 *       (third arg = focus day: list all missing consignment lines for that sale_day only)
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$from = $argv[1] ?? '2026-04-01';
$to = $argv[2] ?? '2026-04-13';
$from = Carbon::parse($from)->toDateString();
$to = Carbon::parse($to)->toDateString();
$focusDay = isset($argv[3]) ? Carbon::parse($argv[3])->toDateString() : null;

echo "=== Dry run: missing consignment + cash-generated coverage ===\n";
echo "Sale date range (COALESCE(saledate, created_at)): {$from} to {$to}\n\n";

if (! Schema::hasColumn('invoice_items', 'penjualan_id')) {
    echo "ERROR: invoice_items.penjualan_id missing — cannot check consignment linkage.\n";
    exit(1);
}

// --- Consignment: count + by day (same join logic as dry_run_march script) ---
$sqlConsignmentMissingCount = <<<'SQL'
SELECT COUNT(*) AS c
FROM penjualan AS p
INNER JOIN penjualan_detail AS pd ON pd.id_penjualan = p.id_penjualan
INNER JOIN produk AS pr ON pr.id_produk = pd.id_produk
INNER JOIN supplier AS s ON s.id_supplier = pr.id_supplier
LEFT JOIN invoice_items AS ii
  ON ii.penjualan_id = p.id_penjualan
  AND ii.produk_id = pr.id_produk
  AND ii.quantity = pd.jumlah
WHERE p.status = 'completed'
  AND DATE(COALESCE(p.saledate, p.created_at)) BETWEEN ? AND ?
  AND UPPER(TRIM(COALESCE(s.mop, ''))) = 'CONSIGNMENT'
  AND COALESCE(pr.is_incomplete, 0) = 0
  AND pd.jumlah > 0
  AND (COALESCE(pr.harga_beli, 0) * pd.jumlah) > 0
  AND ii.id IS NULL
SQL;

$consignmentMissing = (int) (DB::selectOne($sqlConsignmentMissingCount, [$from, $to])->c ?? 0);
echo "Consignment (supplier MOP=CONSIGNMENT): missing invoice_item lines: {$consignmentMissing}\n";

$sqlByDay = <<<'SQL'
SELECT DATE(COALESCE(p.saledate, p.created_at)) AS sale_day, COUNT(*) AS missing_lines
FROM penjualan AS p
INNER JOIN penjualan_detail AS pd ON pd.id_penjualan = p.id_penjualan
INNER JOIN produk AS pr ON pr.id_produk = pd.id_produk
INNER JOIN supplier AS s ON s.id_supplier = pr.id_supplier
LEFT JOIN invoice_items AS ii
  ON ii.penjualan_id = p.id_penjualan
  AND ii.produk_id = pr.id_produk
  AND ii.quantity = pd.jumlah
WHERE p.status = 'completed'
  AND DATE(COALESCE(p.saledate, p.created_at)) BETWEEN ? AND ?
  AND UPPER(TRIM(COALESCE(s.mop, ''))) = 'CONSIGNMENT'
  AND COALESCE(pr.is_incomplete, 0) = 0
  AND pd.jumlah > 0
  AND (COALESCE(pr.harga_beli, 0) * pd.jumlah) > 0
  AND ii.id IS NULL
GROUP BY sale_day
ORDER BY sale_day
SQL;

$byDay = DB::select($sqlByDay, [$from, $to]);
echo "  By sale day:\n";
foreach ($byDay as $row) {
    echo "    {$row->sale_day}: {$row->missing_lines} missing line(s)\n";
}
if (count($byDay) === 0) {
    echo "    (none)\n";
}

$sqlSample = <<<'SQL'
SELECT p.id_penjualan, p.receiptno,
       DATE(COALESCE(p.saledate, p.created_at)) AS sale_day,
       pr.kode_produk, pr.nama_produk, s.nama AS supplier, pd.jumlah,
       ROUND(COALESCE(pr.harga_beli, 0) * pd.jumlah, 2) AS line_cost
FROM penjualan AS p
INNER JOIN penjualan_detail AS pd ON pd.id_penjualan = p.id_penjualan
INNER JOIN produk AS pr ON pr.id_produk = pd.id_produk
INNER JOIN supplier AS s ON s.id_supplier = pr.id_supplier
LEFT JOIN invoice_items AS ii
  ON ii.penjualan_id = p.id_penjualan
  AND ii.produk_id = pr.id_produk
  AND ii.quantity = pd.jumlah
WHERE p.status = 'completed'
  AND DATE(COALESCE(p.saledate, p.created_at)) BETWEEN ? AND ?
  AND UPPER(TRIM(COALESCE(s.mop, ''))) = 'CONSIGNMENT'
  AND COALESCE(pr.is_incomplete, 0) = 0
  AND pd.jumlah > 0
  AND (COALESCE(pr.harga_beli, 0) * pd.jumlah) > 0
  AND ii.id IS NULL
ORDER BY sale_day, p.id_penjualan
LIMIT 40
SQL;

$samples = DB::select($sqlSample, [$from, $to]);
echo "\n  First 40 missing consignment lines (id, receipt, day, product, supplier, qty, est cost):\n";
foreach ($samples as $r) {
    echo "    #{$r->id_penjualan} {$r->receiptno} {$r->sale_day} | {$r->kode_produk} | {$r->supplier} | qty {$r->jumlah} | Ksh {$r->line_cost}\n";
}
if ($consignmentMissing > 40) {
    echo "    ... and ".($consignmentMissing - 40)." more (run with narrower --from/--to or SQL LIMIT)\n";
}

// --- Cash: per sale multiset match ---
$sales = DB::table('penjualan as p')
    ->where('p.status', 'completed')
    ->whereRaw('DATE(COALESCE(p.saledate, p.created_at)) BETWEEN ? AND ?', [$from, $to])
    ->orderBy('p.id_penjualan')
    ->get(['p.id_penjualan', 'p.receiptno', 'p.saledate', 'p.created_at']);

$cashMissingSales = 0;
$cashMissingLines = 0;
$cashMissingSamples = [];

foreach ($sales as $sale) {
    $saleDay = $sale->saledate
        ? Carbon::parse($sale->saledate)->toDateString()
        : Carbon::parse($sale->created_at)->toDateString();

    $rows = DB::select(
        'SELECT pd.id_penjualan_detail, pd.id_produk, pd.jumlah, pr.id_supplier
         FROM penjualan_detail pd
         INNER JOIN produk pr ON pr.id_produk = pd.id_produk
         INNER JOIN supplier s ON s.id_supplier = pr.id_supplier
         WHERE pd.id_penjualan = ?
           AND COALESCE(pr.is_incomplete, 0) = 0
           AND pd.jumlah > 0
           AND (COALESCE(pr.harga_beli, 0) * pd.jumlah) > 0
           AND (UPPER(TRIM(COALESCE(s.mop, ""))) = "CASH" OR s.nama LIKE "% (Cash)")',
        [$sale->id_penjualan]
    );

    if (count($rows) === 0) {
        continue;
    }

    $supplierIds = collect($rows)->pluck('id_supplier')->unique()->values()->all();
    $placeholders = implode(',', array_fill(0, count($supplierIds), '?'));
    $params = array_merge([$saleDay], $supplierIds);

    $pedPool = DB::select(
        "SELECT ped.id_pembelian_detail, ped.id_pembelian, ped.id_produk, ped.jumlah, pe.id_supplier
         FROM pembelian pe
         INNER JOIN pembelian_detail ped ON ped.id_pembelian = pe.id_pembelian
         WHERE DATE(pe.purchasedate2) = ?
           AND pe.id_supplier IN ({$placeholders})",
        $params
    );

    $pool = [];
    foreach ($pedPool as $ped) {
        $key = (int) $ped->id_supplier.'|'.(int) $ped->id_produk.'|'.(int) $ped->jumlah;
        $pool[$key] = ($pool[$key] ?? 0) + 1;
    }

    $saleMissing = [];
    foreach ($rows as $line) {
        $key = (int) $line->id_supplier.'|'.(int) $line->id_produk.'|'.(int) $line->jumlah;
        if (($pool[$key] ?? 0) > 0) {
            $pool[$key]--;
        } else {
            $saleMissing[] = $line;
        }
    }

    if (count($saleMissing) > 0) {
        $cashMissingSales++;
        $cashMissingLines += count($saleMissing);
        if (count($cashMissingSamples) < 25) {
            foreach ($saleMissing as $m) {
                $cashMissingSamples[] = (object) [
                    'id_penjualan' => $sale->id_penjualan,
                    'receiptno' => $sale->receiptno,
                    'sale_day' => $saleDay,
                    'id_produk' => $m->id_produk,
                    'jumlah' => $m->jumlah,
                    'id_supplier' => $m->id_supplier,
                ];
            }
        }
    }
}

echo "\nCash-generated (supplier MOP=CASH or name like \"% (Cash)\"):\n";
echo "  Sales in range with at least one unmatched cash line (heuristic): {$cashMissingSales}\n";
echo "  Total unmatched cash detail lines: {$cashMissingLines}\n";
echo "  (Matching: same calendar purchasedate2 as sale day + supplier; consumes pembelian_detail rows from pool.)\n";

if (count($cashMissingSamples) > 0) {
    echo "\n  Sample unmatched cash lines (up to 25):\n";
    foreach (array_slice($cashMissingSamples, 0, 25) as $s) {
        echo "    sale #{$s->id_penjualan} {$s->receiptno} day {$s->sale_day} | supplier {$s->id_supplier} | produk {$s->id_produk} qty {$s->jumlah}\n";
    }
}

if ($focusDay !== null) {
    $sqlFocus = <<<'SQL'
SELECT p.id_penjualan, p.receiptno,
       DATE(COALESCE(p.saledate, p.created_at)) AS sale_day,
       pr.kode_produk, pr.nama_produk, s.nama AS supplier, pd.jumlah,
       ROUND(COALESCE(pr.harga_beli, 0) * pd.jumlah, 2) AS line_cost
FROM penjualan AS p
INNER JOIN penjualan_detail AS pd ON pd.id_penjualan = p.id_penjualan
INNER JOIN produk AS pr ON pr.id_produk = pd.id_produk
INNER JOIN supplier AS s ON s.id_supplier = pr.id_supplier
LEFT JOIN invoice_items AS ii
  ON ii.penjualan_id = p.id_penjualan
  AND ii.produk_id = pr.id_produk
  AND ii.quantity = pd.jumlah
WHERE p.status = 'completed'
  AND DATE(COALESCE(p.saledate, p.created_at)) = ?
  AND UPPER(TRIM(COALESCE(s.mop, ''))) = 'CONSIGNMENT'
  AND COALESCE(pr.is_incomplete, 0) = 0
  AND pd.jumlah > 0
  AND (COALESCE(pr.harga_beli, 0) * pd.jumlah) > 0
  AND ii.id IS NULL
ORDER BY p.id_penjualan, pd.id_penjualan_detail
SQL;
    $focusRows = DB::select($sqlFocus, [$focusDay]);
    echo "\n=== Focus day {$focusDay}: all missing consignment lines (".count($focusRows).") ===\n";
    foreach ($focusRows as $r) {
        echo "  #{$r->id_penjualan} {$r->receiptno} | {$r->kode_produk} | {$r->supplier} | qty {$r->jumlah} | Ksh {$r->line_cost}\n";
    }
}

echo "\n--- Fix consignment gaps (writes DB) ---\n";
echo "  php artisan consignment:backfill-missing-items --from={$from} --to={$to}\n";
echo "  (Add --dry-run to preview only.)\n";
