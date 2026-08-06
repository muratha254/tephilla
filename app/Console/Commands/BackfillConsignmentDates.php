<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillConsignmentDates extends Command
{
    protected $signature = 'consignment:backfill-dates
                            {--dry-run : Show what would be updated without writing}';

    protected $description = 'Set invoice and invoice_item dates from linked penjualan.saledate so consignment list date filter shows correct totals';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN – no changes will be written.');
        }

        if (!\Schema::hasColumn('invoice_items', 'penjualan_id')) {
            $this->error('invoice_items.penjualan_id column not found.');
            return 1;
        }

        $rows = DB::table('invoice_items')
            ->join('penjualan', 'invoice_items.penjualan_id', '=', 'penjualan.id_penjualan')
            ->select(
                'invoice_items.id as invoice_item_id',
                'invoice_items.invoice_id',
                'penjualan.saledate',
                'penjualan.created_at as penjualan_created_at'
            )
            ->get();

        $byDate = [];
        foreach ($rows as $row) {
            $dateSource = $row->saledate ?? $row->penjualan_created_at;
            if (!$dateSource) {
                continue;
            }
            $date = \Carbon\Carbon::parse($dateSource)->format('Y-m-d') . ' 00:00:00';
            $byDate[] = [
                'item_id' => $row->invoice_item_id,
                'invoice_id' => $row->invoice_id,
                'date' => $date,
            ];
        }

        $itemIds = array_column($byDate, 'item_id');
        $invoiceIds = array_unique(array_column($byDate, 'invoice_id'));
        $this->info('Found ' . count($itemIds) . ' invoice items and ' . count($invoiceIds) . ' invoices to align with sale dates.');

        if (empty($itemIds)) {
            $this->info('Nothing to do.');
            return 0;
        }

        if (!$dryRun) {
            foreach ($byDate as $row) {
                DB::table('invoice_items')->where('id', $row['item_id'])->update([
                    'created_at' => $row['date'],
                    'updated_at' => $row['date'],
                ]);
            }
            $this->info('Updated invoice_items.created_at/updated_at from penjualan.saledate.');

            foreach ($invoiceIds as $invoiceId) {
                $item = collect($byDate)->firstWhere('invoice_id', $invoiceId);
                if ($item) {
                    DB::table('invoices')->where('id', $invoiceId)->update([
                        'created_at' => $item['date'],
                        'updated_at' => $item['date'],
                    ]);
                }
            }
            $this->info('Updated invoices.created_at/updated_at to match first item sale date.');
        }

        $this->info('Done.');
        return 0;
    }
}
