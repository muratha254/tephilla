<?php

namespace App\Console\Commands;

use App\Models\Penjualan;
use App\Services\EnsureSaleSupplierLedgerService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dry-run or fix consignment ledger coverage (penjualan_detail vs invoice_items per sale).
 */
class VerifyConsignmentLedgerCommand extends Command
{
    protected $signature = 'consignment:verify-ledger
                            {--dry-run : Only report gaps; do not create invoice_items}
                            {--fix : Create missing invoice_items (same as sale completion logic)}
                            {--receipt= : Limit to receipt number(s); supports UTAM123 or U123}
                            {--from= : Inclusive sale date Y-m-d (COALESCE(saledate, created_at))}
                            {--to= : Inclusive sale date Y-m-d}
                            {--supplier-id= : Only sales containing a line for this id_supplier}';

    protected $description = 'Audit consignment ledger vs POS lines; optional --fix to backfill';

    public function handle(EnsureSaleSupplierLedgerService $ledger): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fix = (bool) $this->option('fix');
        if ($fix && $dryRun) {
            $this->error('Use either --dry-run or --fix, not both.');

            return 1;
        }
        if (! $dryRun && ! $fix) {
            $dryRun = true;
            $this->warn('No --fix given: running in dry-run mode (report only). Use --fix to write.');
        }

        if (! Schema::hasColumn('invoice_items', 'penjualan_id')) {
            $this->error('invoice_items.penjualan_id is required.');

            return 1;
        }

        $from = $this->option('from') ? Carbon::parse($this->option('from'))->toDateString() : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->toDateString() : null;
        $receipt = $this->option('receipt') ? trim((string) $this->option('receipt')) : '';
        $supplierIdOpt = $this->option('supplier-id');
        $supplierIdInt = ($supplierIdOpt !== null && $supplierIdOpt !== '') ? (int) $supplierIdOpt : null;

        $q = Penjualan::query()->orderBy('id_penjualan');
        if (Schema::hasColumn('penjualan', 'status')) {
            $q->where('status', 'completed');
        }
        if ($from || $to) {
            $q->whereRaw(
                'DATE(COALESCE(saledate, created_at)) BETWEEN ? AND ?',
                [$from ?? '1970-01-01', $to ?? '2099-12-31']
            );
        }
        if ($receipt !== '') {
            $q->where(function ($w) use ($receipt) {
                $w->where('receiptno', $receipt)
                    ->orWhere('receiptno', 'like', $receipt.'|%')
                    ->orWhereRaw('UPPER(TRIM(receiptno)) = ?', [strtoupper($receipt)]);
            });
        }
        if ($supplierIdInt !== null && $supplierIdInt > 0) {
            $q->whereExists(function ($sub) use ($supplierIdInt) {
                $sub->select(DB::raw('1'))
                    ->from('penjualan_detail as pd')
                    ->join('produk as pr', 'pr.id_produk', '=', 'pd.id_produk')
                    ->whereColumn('pd.id_penjualan', 'penjualan.id_penjualan')
                    ->where('pr.id_supplier', $supplierIdInt);
            });
        }

        $sales = $q->get();
        $this->info('Sales to check: '.$sales->count());

        $reportShortfalls = 0;
        $fixedInvoiceItems = 0;

        foreach ($sales as $penjualan) {
            if ($dryRun) {
                $pre = $ledger->ensureConsignmentLedgerCompleteOnSaleComplete($penjualan, true);
                $would = (int) ($pre['would_create_invoice_items'] ?? 0);
                if (! ($pre['coverage_ok_before'] ?? true) || $would > 0) {
                    $this->line("Receipt {$penjualan->receiptno} (id {$penjualan->id_penjualan}): would create {$would} item(s); shortfalls: "
                        .json_encode($pre['shortfalls_before'] ?? []));
                    $reportShortfalls++;
                }
            } else {
                $result = $ledger->ensureConsignmentLedgerCompleteOnSaleComplete($penjualan, false);
                $n = (int) ($result['invoice_items_created_total'] ?? 0);
                $fixedInvoiceItems += $n;
                if ($n > 0 || empty($result['coverage_ok'])) {
                    $this->line("Receipt {$penjualan->receiptno}: created {$n} invoice_item(s); coverage_ok="
                        .(($result['coverage_ok'] ?? false) ? 'yes' : 'NO'));
                    if (! empty($result['remaining_shortfalls'])) {
                        $this->warn('  remaining: '.json_encode($result['remaining_shortfalls']));
                    }
                    if (! ($result['coverage_ok'] ?? true)) {
                        $reportShortfalls++;
                    }
                }
            }
        }

        if ($dryRun) {
            $this->info("Done (dry-run). Sales with gaps or missing lines: {$reportShortfalls}.");
        } else {
            $this->info("Done (--fix). Total invoice_items created: {$fixedInvoiceItems}. Sales still incomplete: {$reportShortfalls}.");
        }

        return 0;
    }
}
