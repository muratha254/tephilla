<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Penjualan;
use App\Services\EnsureSaleSupplierLedgerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Create missing invoice_items for completed sales where a line’s product supplier has MOP = Consignment.
 * Routing matches PenjualanController: supplier MOP decides consignment vs cash-generated purchase — not customer payment.
 */
class BackfillMissingConsignmentItems extends Command
{
    protected $signature = 'consignment:backfill-missing-items
                            {--dry-run : List what would be created without writing}
                            {--only-customer-consignment-sales : Legacy: restrict to penjualan.payment_method = Consignment only}
                            {--from= : Inclusive start date (Y-m-d) on COALESCE(saledate, created_at)}
                            {--to= : Inclusive end date (Y-m-d) on COALESCE(saledate, created_at)}
                            {--receipt= : Only this receipt number (e.g. UTAM301 or A51461)}
                            {--supplier-id= : Only sales that include a line for products of this supplier (id_supplier)}';

    protected $description = 'Backfill missing consignment invoice items (by supplier MOP = Consignment on each line)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $legacyCustomerFilter = $this->option('only-customer-consignment-sales');
        if ($dryRun) {
            $this->warn('DRY RUN – no changes will be written.');
        }

        if ($legacyCustomerFilter && ! Schema::hasColumn('penjualan', 'payment_method')) {
            $this->error('penjualan.payment_method column not found; cannot use --only-customer-consignment-sales.');

            return 1;
        }
        if (! Schema::hasColumn('invoice_items', 'penjualan_id')) {
            $this->error('invoice_items.penjualan_id column not found.');

            return 1;
        }

        $from = $this->option('from') ? Carbon::parse($this->option('from'))->toDateString() : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->toDateString() : null;
        $receipt = $this->option('receipt') ? trim((string) $this->option('receipt')) : '';
        $supplierIdOpt = $this->option('supplier-id');
        $restrictSupplier = $supplierIdOpt !== null && $supplierIdOpt !== '';
        $supplierIdInt = $restrictSupplier ? (int) $supplierIdOpt : null;

        $query = Penjualan::query()->orderBy('saledate');
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }
        if ($legacyCustomerFilter) {
            $query->whereRaw('UPPER(TRIM(COALESCE(payment_method, ""))) = ?', ['CONSIGNMENT']);
        }
        if ($receipt !== '') {
            $query->where(function ($q) use ($receipt) {
                if (str_contains($receipt, '|')) {
                    $q->where('receiptno', $receipt);
                } else {
                    $q->where('receiptno', 'like', $receipt.'|%')
                        ->orWhere('receiptno', $receipt);
                }
            });
        }
        if ($from || $to) {
            $query->whereRaw(
                'DATE(COALESCE(saledate, created_at)) BETWEEN ? AND ?',
                [
                    $from ?? '1970-01-01',
                    $to ?? '2099-12-31',
                ]
            );
        }

        if ($restrictSupplier && $supplierIdInt > 0) {
            $query->whereExists(function ($sub) use ($supplierIdInt) {
                $sub->select(DB::raw('1'))
                    ->from('penjualan_detail as pd')
                    ->join('produk as pr', 'pr.id_produk', '=', 'pd.id_produk')
                    ->whereColumn('pd.id_penjualan', 'penjualan.id_penjualan')
                    ->where('pr.id_supplier', $supplierIdInt);
            });
        }

        $sales = $query->get(['id_penjualan', 'saledate', 'receiptno', 'payment_method', 'created_at']);

        $mode = $legacyCustomerFilter
            ? 'legacy: only sales where customer payment_method = Consignment'
            : 'supplier MOP: all completed sales (consignment lines backfilled where supplier MOP = Consignment)';
        if ($restrictSupplier && $supplierIdInt > 0) {
            $mode .= "; supplier filter id_supplier={$supplierIdInt}";
        }
        $this->info('Mode: '.$mode.'. Found '.$sales->count().' sale(s).');

        $created = 0;
        $skipped = 0;
        $ledger = app(EnsureSaleSupplierLedgerService::class);
        $restrictId = ($restrictSupplier && $supplierIdInt > 0) ? $supplierIdInt : null;

        foreach ($sales as $penjualan) {
            if ($restrictId !== null) {
                $detailCount = \App\Models\PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)
                    ->whereIn('id_produk', function ($q) use ($restrictId) {
                        $q->select('id_produk')->from('produk')->where('id_supplier', $restrictId);
                    })
                    ->count();
                if ($detailCount === 0) {
                    $skipped++;

                    continue;
                }
            }

            if ($dryRun) {
                $gaps = $ledger->collectMissingConsignmentGaps($penjualan, $restrictId);
                foreach ($gaps as $gap) {
                    $p = $gap['produk'];
                    $qty = $gap['qty'];
                    $amount = $gap['amount'];
                    $this->line("Would create: receipt {$penjualan->receiptno} | supplier consignment | {$p->nama_produk}, qty {$qty}, amount {$amount} (harga_beli×qty)");
                    $created++;
                }
            } else {
                $n = $ledger->fillConsignmentGapsWithRetries($penjualan, $restrictId, 5);
                $created += $n;
                if ($n > 0) {
                    $this->line("Sale {$penjualan->receiptno}: created {$n} missing consignment line(s) (with retries).");
                }
            }
        }

        $this->info('Done. Created: '.$created.', Skipped: '.$skipped);

        return 0;
    }
}
