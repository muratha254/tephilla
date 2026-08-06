<?php

namespace App\Console\Commands;

use App\Http\Controllers\ProdukController;
use App\Models\Produk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReconcileProdukStockCommand extends Command
{
    protected $signature = 'produk:reconcile-stock
                            {--code= : Product code (kode_produk / item_code)}
                            {--id= : Product id_produk}
                            {--all : Reconcile every product that has stock-in history}
                            {--dry-run : Show changes without saving}
                            {--chunk=200 : Products per batch (single-product mode only)}
                            {--verbose-lines : Print each product change (slow on --all)}';

    protected $description = 'Align produk.stok with stock received (produk_history) minus completed sales';

    public function handle(): int
    {
        if (! Schema::hasTable('produk_history')) {
            $this->error('produk_history table is missing.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($this->option('all')) {
            return $this->reconcileAllBulk($dryRun);
        }

        /** @var ProdukController $controller */
        $controller = app(ProdukController::class);
        $fixed = 0;
        $scanned = 0;
        $verbose = (bool) $this->option('verbose-lines');

        $processRows = function ($rows) use ($controller, $dryRun, $verbose, &$fixed, &$scanned) {
            foreach ($rows as $produk) {
                $scanned++;
                $result = $controller->reconcileProdukStokFromLedger($produk, $dryRun);
                if ($result['changed']) {
                    if ($verbose) {
                        $this->line(sprintf(
                            '%s #%d %s: %d → %d (in: %d, sold: %d)',
                            $dryRun ? 'Would fix' : 'Fixed',
                            $produk->id_produk,
                            $produk->nama_produk,
                            $result['previous'],
                            $result['new'],
                            $result['total_in'],
                            $result['sold']
                        ));
                    }
                    $fixed++;
                }
            }
        };

        if ($this->option('id')) {
            $produk = Produk::find((int) $this->option('id'));
            if (! $produk) {
                $this->warn('Product not found.');

                return self::SUCCESS;
            }
            $processRows(collect([$produk]));
        } elseif ($this->option('code')) {
            $code = trim((string) $this->option('code'));
            $rows = Produk::query()
                ->where(function ($q) use ($code) {
                    $q->where('kode_produk', $code)->orWhere('item_code', $code);
                })
                ->orderBy('id_produk')
                ->get();
            if ($rows->isEmpty()) {
                $this->warn('No matching products.');

                return self::SUCCESS;
            }
            $processRows($rows);
        } else {
            $this->error('Specify --code=, --id=, or --all');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Scanned %d product(s); %s %d.',
            $scanned,
            $dryRun ? 'would fix' : 'fixed',
            $fixed
        ));

        return self::SUCCESS;
    }

    private function reconcileAllBulk(bool $dryRun): int
    {
        $historySub = DB::table('produk_history')
            ->select(
                'id_produk',
                DB::raw('SUM(GREATEST(0, current_stock - previous_stock)) as total_in')
            )
            ->groupBy('id_produk');

        $salesSub = DB::table('penjualan_detail as pd')
            ->select('pd.id_produk', DB::raw('SUM(pd.jumlah) as total_sold'));
        if (Schema::hasColumn('penjualan', 'status')) {
            $salesSub->join('penjualan as p', 'p.id_penjualan', '=', 'pd.id_penjualan')
                ->where('p.status', 'completed');
        }
        $salesSub->groupBy('pd.id_produk');

        $mismatchQuery = DB::table('produk')
            ->joinSub($historySub, 'hist', 'hist.id_produk', '=', 'produk.id_produk')
            ->leftJoinSub($salesSub, 'sales', 'sales.id_produk', '=', 'produk.id_produk')
            ->whereRaw('produk.stok <> GREATEST(0, hist.total_in - COALESCE(sales.total_sold, 0))')
            ->select([
                'produk.id_produk',
                'produk.kode_produk',
                'produk.nama_produk',
                'produk.stok',
                DB::raw('hist.total_in'),
                DB::raw('COALESCE(sales.total_sold, 0) as total_sold'),
                DB::raw('GREATEST(0, hist.total_in - COALESCE(sales.total_sold, 0)) as expected_stok'),
            ]);

        $mismatches = (clone $mismatchQuery)->count();
        $withHistory = (int) DB::table('produk_history')->distinct()->count('id_produk');

        if ($dryRun) {
            $this->info(sprintf(
                'Products with stock-in history: %s. Mismatched on-hand stock: %s.',
                number_format($withHistory),
                number_format($mismatches)
            ));

            if ($this->option('verbose-lines') && $mismatches > 0) {
                $mismatchQuery->orderBy('produk.id_produk')->chunk(500, function ($rows) {
                    foreach ($rows as $row) {
                        $this->line(sprintf(
                            'Would fix #%d %s (%s): %d → %d (in: %d, sold: %d)',
                            $row->id_produk,
                            $row->nama_produk,
                            $row->kode_produk ?? '',
                            (int) $row->stok,
                            (int) $row->expected_stok,
                            (int) $row->total_in,
                            (int) $row->total_sold
                        ));
                    }
                });
            }

            return self::SUCCESS;
        }

        $updated = DB::table('produk')
            ->joinSub($historySub, 'hist', 'hist.id_produk', '=', 'produk.id_produk')
            ->leftJoinSub($salesSub, 'sales', 'sales.id_produk', '=', 'produk.id_produk')
            ->whereRaw('produk.stok <> GREATEST(0, hist.total_in - COALESCE(sales.total_sold, 0))')
            ->update([
                'produk.stok' => DB::raw('GREATEST(0, hist.total_in - COALESCE(sales.total_sold, 0))'),
            ]);

        $this->info(sprintf(
            'Products with stock-in history: %s. Updated on-hand stock: %s.',
            number_format($withHistory),
            number_format($updated)
        ));

        return self::SUCCESS;
    }
}
