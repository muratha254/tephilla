<?php

/**
 * One-off report: consignment supplier sale lines in March 2026 with no matching invoice_item.
 * Run: php scripts/dry_run_march_2026_missing_consignment.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$from = '2026-03-01';
$to = '2026-03-31';

$sqlCount = <<<'SQL'
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

$t0 = microtime(true);
$count = (int) (DB::selectOne($sqlCount, [$from, $to])->c ?? 0);
$t1 = microtime(true);

echo "March 2026 ({$from} to {$to}) — consignment lines missing invoice_item (penjualan_id+produk_id+qty match)\n";
echo "Missing line count: {$count}  (query ".round($t1 - $t0, 2)."s)\n\n";

$sqlBySupplier = <<<'SQL'
SELECT s.id_supplier, s.nama,
       COUNT(*) AS missing_lines,
       SUM(ROUND(COALESCE(pr.harga_beli, 0) * pd.jumlah, 2)) AS est_amount
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
GROUP BY s.id_supplier, s.nama
ORDER BY missing_lines DESC
LIMIT 30
SQL;

$bySupplier = DB::select($sqlBySupplier, [$from, $to]);
echo "Top suppliers by missing line count:\n";
foreach ($bySupplier as $row) {
    echo "  supplier {$row->id_supplier} {$row->nama}: {$row->missing_lines} lines, est Ksh {$row->est_amount}\n";
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
ORDER BY p.id_penjualan DESC
LIMIT 25
SQL;

$sample = DB::select($sqlSample, [$from, $to]);
echo "\nSample (up to 25 most recent sales with a gap):\n";
foreach ($sample as $r) {
    echo "  {$r->receiptno} id={$r->id_penjualan} day={$r->sale_day} | {$r->supplier} | {$r->nama_produk} x{$r->jumlah} = {$r->line_cost}\n";
}

echo "\nFix (writes DB): php artisan consignment:backfill-missing-items --from={$from} --to={$to}\n";
echo "Dry-run same:     php artisan consignment:backfill-missing-items --dry-run --from={$from} --to={$to}\n";
